<?php

use App\Actions\Books\ChangeBookStatus;
use App\Actions\Books\DeleteBook;
use App\BookStatus;
use App\Models\Book;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Book Details')] class extends Component
{
    public Book $book;
    public bool $showConfirmModal = false;
    public string $pendingAction = '';

    public function mount(Book $book): void
    {
        Gate::authorize('view', $book);
        abort_if($book->trashed(), 404);
        $this->book = $book->load(['cover', 'leadership', 'speaker', 'creator:id,name', 'updater:id,name']);
    }

    public function confirm(string $action): void
    {
        $ability = match ($action) {
            'publish', 'unpublish' => 'publish',
            'feature' => 'feature',
            'archive' => 'archive',
            'restore' => 'restore',
            'delete' => 'delete',
            default => abort(404),
        };
        Gate::authorize($ability, $this->book);
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(ChangeBookStatus $changeStatus, DeleteBook $deleteBook): void
    {
        $actor = Auth::user();
        match ($this->pendingAction) {
            'publish' => $changeStatus->publish($actor, $this->book),
            'unpublish' => $changeStatus->unpublish($actor, $this->book),
            'feature' => $changeStatus->toggleFeatured($actor, $this->book),
            'archive' => $changeStatus->archive($actor, $this->book),
            'restore' => $changeStatus->restore($actor, $this->book),
            'delete' => $deleteBook->delete($actor, $this->book),
            default => abort(404),
        };

        if ($this->pendingAction === 'delete') {
            session()->flash('success', __('Book moved to trash.'));
            $this->redirectRoute('books.index', navigate: true);

            return;
        }

        $this->book = $this->book->refresh()->load(['cover', 'leadership', 'speaker', 'creator:id,name', 'updater:id,name']);
        $this->reset(['showConfirmModal', 'pendingAction']);
        Flux::toast(variant: 'success', text: __('Book action completed successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('books.index')" wire:navigate>{{ __('Books') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ $book->title }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="$book->title" :description="$book->subtitle ?: $book->short_description" :eyebrow="__('Book details')"><x-slot:actions>
        @can('update', $book)<flux:button :href="route('books.edit', $book)" icon="pencil-square" wire:navigate>{{ __('Edit book') }}</flux:button>@endcan
        @can('publish', $book)<flux:button icon="globe-alt" wire:click="confirm('{{ $book->status === BookStatus::Published ? 'unpublish' : 'publish' }}')">{{ $book->status === BookStatus::Published ? __('Unpublish') : __('Publish') }}</flux:button>@endcan
        @can('feature', $book)<flux:button icon="star" wire:click="confirm('feature')">{{ $book->is_featured ? __('Unfeature') : __('Feature') }}</flux:button>@endcan
        @if ($book->status === BookStatus::Archived) @can('restore', $book)<flux:button icon="arrow-path" wire:click="confirm('restore')">{{ __('Restore') }}</flux:button>@endcan @else @can('archive', $book)<flux:button icon="archive-box" wire:click="confirm('archive')">{{ __('Archive') }}</flux:button>@endcan @endif
        @can('delete', $book)<flux:button variant="danger" icon="trash" wire:click="confirm('delete')">{{ __('Delete') }}</flux:button>@endcan
    </x-slot:actions></x-admin.page-header>
    <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)_20rem]">
        <aside class="space-y-4">
            @if ($book->coverUrl())<img src="{{ $book->coverUrl() }}" alt="{{ __('Cover of :title', ['title' => $book->title]) }}" class="w-full rounded-xl border border-slate-200 object-cover shadow-lg dark:border-zinc-800">@else<div class="flex aspect-[2/3] items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800"><flux:icon.book-open class="size-12 text-slate-400" /></div>@endif
            <div class="flex flex-wrap gap-2"><flux:badge :color="$book->status->color()">{{ $book->status->label() }}</flux:badge><flux:badge :color="$book->availability_status->color()">{{ $book->availability_status->label() }}</flux:badge>@if ($book->is_featured)<flux:badge color="amber">{{ __('Featured') }}</flux:badge>@endif</div>
        </aside>
        <article class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div><p class="text-sm uppercase tracking-wide text-slate-500">{{ __('By :author', ['author' => $book->author_name]) }}</p><flux:heading size="xl" class="mt-2">{{ $book->short_description }}</flux:heading></div>
            @if ($book->description)<section><flux:heading>{{ __('Description') }}</flux:heading><p class="mt-3 whitespace-pre-line text-slate-600 dark:text-zinc-300">{{ $book->description }}</p></section>@endif
            <section><flux:heading>{{ __('Access') }}</flux:heading><div class="mt-3 flex flex-wrap gap-3">@if ($book->purchase_url)<flux:button :href="$book->purchase_url" target="_blank" rel="noopener noreferrer" icon="shopping-bag">{{ __('Purchase') }}</flux:button>@endif @if ($book->download_url)<flux:button :href="$book->download_url" target="_blank" rel="noopener noreferrer" icon="arrow-down-tray">{{ __('Download') }}</flux:button>@endif</div></section>
        </article>
        <aside class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Publication') }}</flux:heading><dl class="mt-4 space-y-3 text-sm">@foreach ([__('Author') => $book->author_name, __('Publisher') => $book->publisher, __('ISBN') => $book->isbn, __('Edition') => $book->edition, __('Language') => $book->language, __('Pages') => $book->page_count, __('Publication date') => $book->publication_date?->format('M j, Y'), __('Format') => $book->format->label()] as $label => $value)@if (filled($value))<div><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>@endif @endforeach</dl></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Commercial details') }}</flux:heading><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">{{ __('Price') }}</dt><dd class="font-semibold">{{ $book->displayPrice() }}</dd></div><div><dt class="text-slate-500">{{ __('Stock') }}</dt><dd>{{ $book->stock_quantity ?? __('Not tracked') }}</dd></div></dl></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Record history') }}</flux:heading><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">{{ __('Created by') }}</dt><dd>{{ $book->creator?->name ?? __('Unknown') }}</dd></div><div><dt class="text-slate-500">{{ __('Updated by') }}</dt><dd>{{ $book->updater?->name ?? __('Unknown') }}</dd></div><div><dt class="text-slate-500">{{ __('Created') }}</dt><dd>{{ $book->created_at?->format('M j, Y g:i A') }}</dd></div><div><dt class="text-slate-500">{{ __('Updated') }}</dt><dd>{{ $book->updated_at?->format('M j, Y g:i A') }}</dd></div><div><dt class="text-slate-500">{{ __('Published') }}</dt><dd>{{ $book->published_at?->format('M j, Y g:i A') ?? __('Not published') }}</dd></div></dl></section>
        </aside>
    </div>
    <flux:modal wire:model="showConfirmModal" class="max-w-md"><flux:heading size="lg">{{ __('Confirm book action') }}</flux:heading><flux:text class="mt-2">{{ __('This action will be applied immediately. Continue?') }}</flux:text><div class="mt-6 flex justify-end gap-3"><flux:button wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="danger" wire:click="executeConfirmed" wire:loading.attr="disabled" wire:target="executeConfirmed">{{ __('Continue') }}</flux:button></div></flux:modal>
</div>
