<?php

use App\Actions\Books\ChangeBookStatus;
use App\Actions\Books\DeleteBook;
use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\Models\Book;
use App\PermissionName;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Books')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $format = '';
    #[Url] public string $availability = '';
    #[Url] public string $featured = '';
    #[Url] public string $pricing = '';
    #[Url] public string $author = '';
    #[Url] public string $year = '';
    #[Url] public string $sort = 'updated_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;
    public bool $showConfirmModal = false;
    public ?int $targetBookId = null;
    public string $pendingAction = '';
    /** @var list<int|string> */
    public array $selected = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Book::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['showConfirmModal', 'targetBookId', 'pendingAction'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function books(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, ['title', 'author_name', 'price', 'publication_date', 'created_at', 'updated_at'], true) ? $this->sort : 'updated_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return Book::query()->withTrashed()
            ->select(['id', 'title', 'slug', 'author_name', 'media_id', 'format', 'price', 'currency', 'availability_status', 'is_featured', 'is_free', 'status', 'published_at', 'updated_at', 'deleted_at'])
            ->with('cover:id,disk,path,visibility')
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->status !== '', fn (Builder $query): Builder => $this->status === 'deleted' ? $query->onlyTrashed() : $query->where('status', $this->status))
            ->when($this->format !== '', fn (Builder $query): Builder => $query->where('format', $this->format))
            ->when($this->availability !== '', fn (Builder $query): Builder => $query->where('availability_status', $this->availability))
            ->when($this->featured !== '', fn (Builder $query): Builder => $query->where('is_featured', $this->featured === '1'))
            ->when($this->pricing !== '', fn (Builder $query): Builder => $query->where('is_free', $this->pricing === 'free'))
            ->when($this->author !== '', fn (Builder $query): Builder => $query->where('author_name', 'like', '%'.$this->author.'%'))
            ->when(ctype_digit($this->year), fn (Builder $query): Builder => $query->whereYear('publication_date', (int) $this->year))
            ->orderBy($sort, $direction)
            ->orderBy('title')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Book::withTrashed()->count(),
            'published' => Book::query()->published()->count(),
            'featured' => Book::query()->published()->featured()->count(),
            'free' => Book::query()->where('is_free', true)->count(),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'format', 'availability', 'featured', 'pricing', 'author', 'year']);
        $this->resetPage();
    }

    public function selectPage(): void
    {
        $this->selected = collect($this->books->items())
            ->map(fn (Book $book): int => $book->id)
            ->values()
            ->all();
    }

    public function confirmBulk(string $action): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1', 'max:100'],
            'selected.*' => ['integer', 'distinct'],
        ]);

        if ($action === 'feature' && count($this->selected) !== 1) {
            $this->addError('selected', __('Only one book can be featured. Select exactly one book.'));

            return;
        }

        abort_unless(in_array($action, ['publish', 'unpublish', 'feature', 'unfeature', 'archive', 'restore', 'delete'], true), 404);
        $this->pendingAction = "bulk:{$action}";
        $this->targetBookId = null;
        $this->showConfirmModal = true;
    }

    public function confirm(int $id, string $action): void
    {
        $book = Book::withTrashed()->findOrFail($id);
        $ability = match ($action) {
            'delete' => 'delete',
            'restore-deleted', 'restore' => 'restore',
            'publish', 'unpublish' => 'publish',
            'archive' => 'archive',
            'feature' => 'feature',
            default => abort(404),
        };
        Gate::authorize($ability, $book);
        $this->targetBookId = $id;
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(DeleteBook $deleteBook, ChangeBookStatus $changeStatus): void
    {
        if (str_starts_with($this->pendingAction, 'bulk:')) {
            $this->executeBulk($deleteBook, $changeStatus);

            return;
        }

        $book = Book::withTrashed()->findOrFail($this->targetBookId);
        $actor = Auth::user();

        match ($this->pendingAction) {
            'delete' => $deleteBook->delete($actor, $book),
            'restore-deleted' => $deleteBook->restore($actor, $book),
            'publish' => $changeStatus->publish($actor, $book),
            'unpublish' => $changeStatus->unpublish($actor, $book),
            'archive' => $changeStatus->archive($actor, $book),
            'restore' => $changeStatus->restore($actor, $book),
            'feature' => $changeStatus->toggleFeatured($actor, $book),
            default => abort(404),
        };

        $this->reset(['showConfirmModal', 'targetBookId', 'pendingAction']);
        unset($this->books, $this->stats);
        Flux::toast(variant: 'success', text: __('Book action completed successfully.'));
    }

    private function executeBulk(DeleteBook $deleteBook, ChangeBookStatus $changeStatus): void
    {
        $action = str($this->pendingAction)->after('bulk:')->toString();
        $ids = collect($this->selected)->map(fn (int|string $id): int => (int) $id)->unique()->values();
        $actor = Auth::user();
        $completed = 0;
        $failed = 0;

        foreach (Book::query()->withTrashed()->whereKey($ids)->lazyById() as $book) {
            try {
                match ($action) {
                    'publish' => $changeStatus->publish($actor, $book),
                    'unpublish' => $changeStatus->unpublish($actor, $book),
                    'feature' => $changeStatus->toggleFeatured($actor, $book),
                    'unfeature' => $book->is_featured ? $changeStatus->toggleFeatured($actor, $book) : $book,
                    'archive' => $changeStatus->archive($actor, $book),
                    'restore' => $book->trashed() ? $deleteBook->restore($actor, $book) : $changeStatus->restore($actor, $book),
                    'delete' => $deleteBook->delete($actor, $book),
                    default => abort(404),
                };
                $completed++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('A bulk book action failed.', [
                    'book_id' => $book->id,
                    'action' => $action,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->reset(['selected', 'showConfirmModal', 'targetBookId', 'pendingAction']);
        unset($this->books, $this->stats);
        Flux::toast(
            variant: $failed > 0 ? 'warning' : 'success',
            text: $failed > 0
                ? __('Completed :completed book actions; :failed could not be applied.', ['completed' => $completed, 'failed' => $failed])
                : trans_choice(':count book updated successfully.|:count books updated successfully.', $completed, ['count' => $completed]),
        );
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Books') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Books')" :description="__('Manage church books, authors, publication formats, pricing, availability, and public visibility.')" :eyebrow="__('Content management')"><x-slot:actions>@can('create', Book::class)<flux:button :href="route('books.create')" variant="primary" icon="plus" wire:navigate>{{ __('Create book') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card :label="__('Total books')" :value="$this->stats['total']" :caption="__('Including deleted records')" icon="book-open" />
        <x-admin.stat-card :label="__('Published')" :value="$this->stats['published']" :caption="__('Currently visible')" icon="globe-alt" />
        <x-admin.stat-card :label="__('Featured')" :value="$this->stats['featured']" :caption="__('Homepage-ready highlight')" icon="star" />
        <x-admin.stat-card :label="__('Free')" :value="$this->stats['free']" :caption="__('No purchase price')" icon="gift" />
    </div>
    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-100 p-4 sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search title, author, ISBN, or description')" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (BookStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="format" :label="__('Format')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (BookFormat::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="availability" :label="__('Availability')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (BookAvailabilityStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="pricing" :label="__('Price')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="free">{{ __('Free') }}</flux:select.option><flux:select.option value="paid">{{ __('Paid') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="featured" :label="__('Featured')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="1">{{ __('Featured') }}</flux:select.option><flux:select.option value="0">{{ __('Not featured') }}</flux:select.option></flux:select>
                <flux:input wire:model.live.debounce.350ms="author" :label="__('Author')" />
                <flux:input wire:model.live.debounce.350ms="year" :label="__('Publication year')" inputmode="numeric" />
                <flux:select wire:model.live="sort" :label="__('Sort by')"><flux:select.option value="updated_at">{{ __('Last updated') }}</flux:select.option><flux:select.option value="title">{{ __('Title') }}</flux:select.option><flux:select.option value="author_name">{{ __('Author') }}</flux:select.option><flux:select.option value="price">{{ __('Price') }}</flux:select.option><flux:select.option value="publication_date">{{ __('Publication date') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="direction" :label="__('Direction')"><flux:select.option value="desc">{{ __('Descending') }}</flux:select.option><flux:select.option value="asc">{{ __('Ascending') }}</flux:select.option></flux:select>
                <div class="flex items-end"><flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button type="button" size="sm" variant="ghost" wire:click="selectPage">{{ __('Select this page') }}</flux:button>
                @if ($selected !== [])
                    <flux:badge color="amber">{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</flux:badge>
                    @can(PermissionName::BooksPublish->value)
                        <flux:button type="button" size="sm" wire:click="confirmBulk('publish')">{{ __('Publish') }}</flux:button>
                        <flux:button type="button" size="sm" wire:click="confirmBulk('unpublish')">{{ __('Unpublish') }}</flux:button>
                        <flux:button type="button" size="sm" wire:click="confirmBulk('feature')">{{ __('Feature') }}</flux:button>
                        <flux:button type="button" size="sm" wire:click="confirmBulk('unfeature')">{{ __('Unfeature') }}</flux:button>
                    @endcan
                    @can(PermissionName::BooksArchive->value)<flux:button type="button" size="sm" wire:click="confirmBulk('archive')">{{ __('Archive') }}</flux:button>@endcan
                    @can(PermissionName::BooksRestore->value)<flux:button type="button" size="sm" wire:click="confirmBulk('restore')">{{ __('Restore') }}</flux:button>@endcan
                    @can(PermissionName::BooksDelete->value)<flux:button type="button" size="sm" variant="danger" wire:click="confirmBulk('delete')">{{ __('Delete') }}</flux:button>@endcan
                    <flux:button type="button" size="sm" variant="ghost" wire:click="$set('selected', [])">{{ __('Clear') }}</flux:button>
                @endif
            </div>
            <flux:error name="selected" />
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[72rem] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60 dark:text-zinc-400"><tr><th class="px-4 py-3"><span class="sr-only">{{ __('Select') }}</span></th><th class="px-4 py-3">{{ __('Book') }}</th><th class="px-4 py-3">{{ __('Author') }}</th><th class="px-4 py-3">{{ __('Format') }}</th><th class="px-4 py-3">{{ __('Price') }}</th><th class="px-4 py-3">{{ __('Availability') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Updated') }}</th><th class="px-6 py-3 text-right">{{ __('Actions') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($this->books as $book)
                        <tr wire:key="book-{{ $book->id }}">
                            <td class="px-4 py-4"><flux:checkbox wire:model.live="selected" :value="$book->id" :aria-label="__('Select :title', ['title' => $book->title])" /></td>
                            <td class="px-4 py-4"><div class="flex items-center gap-3">@if ($book->coverUrl())<img src="{{ $book->coverUrl() }}" alt="" class="h-14 w-10 rounded object-cover">@else<div class="flex h-14 w-10 items-center justify-center rounded bg-slate-100 text-slate-400 dark:bg-zinc-800"><flux:icon.book-open class="size-5" /></div>@endif<div><p class="font-semibold">{{ $book->title }}</p>@if ($book->is_featured)<flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>@endif</div></div></td>
                            <td class="px-4 py-4">{{ $book->author_name }}</td>
                            <td class="px-4 py-4">{{ $book->format->label() }}</td>
                            <td class="px-4 py-4">{{ $book->displayPrice() }}</td>
                            <td class="px-4 py-4"><flux:badge :color="$book->availability_status->color()">{{ $book->availability_status->label() }}</flux:badge></td>
                            <td class="px-4 py-4"><flux:badge :color="$book->trashed() ? 'red' : $book->status->color()">{{ $book->trashed() ? __('Deleted') : $book->status->label() }}</flux:badge></td>
                            <td class="px-4 py-4">{{ $book->updated_at->diffForHumans() }}</td>
                            <td class="px-6 py-4"><div class="flex justify-end gap-2">
                                @if (! $book->trashed())
                                    <flux:button size="sm" :href="route('books.show', $book)" icon="eye" wire:navigate />
                                    @can('update', $book)<flux:button size="sm" :href="route('books.edit', $book)" icon="pencil-square" wire:navigate />@endcan
                                    @can('publish', $book)<flux:button size="sm" icon="globe-alt" wire:click="confirm({{ $book->id }}, '{{ $book->status === BookStatus::Published ? 'unpublish' : 'publish' }}')" />@endcan
                                    @can('feature', $book)<flux:button size="sm" icon="star" wire:click="confirm({{ $book->id }}, 'feature')" />@endcan
                                    @if ($book->status === BookStatus::Archived) @can('restore', $book)<flux:button size="sm" icon="arrow-path" wire:click="confirm({{ $book->id }}, 'restore')" />@endcan @else @can('archive', $book)<flux:button size="sm" icon="archive-box" wire:click="confirm({{ $book->id }}, 'archive')" />@endcan @endif
                                    @can('delete', $book)<flux:button size="sm" variant="danger" icon="trash" wire:click="confirm({{ $book->id }}, 'delete')" />@endcan
                                @else
                                    @can('restore', $book)<flux:button size="sm" icon="arrow-path" wire:click="confirm({{ $book->id }}, 'restore-deleted')" />@endcan
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-admin.empty-state icon="book-open" :title="__('No books found')" :description="__('Create a book or clear the current filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->books->hasPages())<div class="border-t border-slate-100 px-6 py-4 dark:border-zinc-800">{{ $this->books->links() }}</div>@endif
    </section>
    <flux:modal wire:model="showConfirmModal" class="max-w-md"><flux:heading size="lg">{{ __('Confirm book action') }}</flux:heading><flux:text class="mt-2">{{ __('This action will be applied immediately. Continue?') }}</flux:text><div class="mt-6 flex justify-end gap-3"><flux:button wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="danger" wire:click="executeConfirmed" wire:loading.attr="disabled" wire:target="executeConfirmed">{{ __('Continue') }}</flux:button></div></flux:modal>
</div>
