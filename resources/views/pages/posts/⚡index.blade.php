<?php

use App\Actions\Blog\ChangePostStatus;
use App\Actions\Blog\DeletePost;
use App\Actions\Blog\DuplicatePost;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\PostStatus;
use App\PostVisibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Blog Posts')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $category = '';
    #[Url] public string $author = '';
    #[Url] public string $visibility = '';
    #[Url] public string $featured = '';
    #[Url] public string $dateFrom = '';
    #[Url] public string $dateTo = '';
    #[Url] public string $sort = 'updated_at';
    #[Url] public string $direction = 'desc';
    #[Url] public int $perPage = 15;
    public bool $showConfirmModal = false;
    public ?int $targetPostId = null;
    public string $pendingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Post::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['showConfirmModal', 'targetPostId', 'pendingAction'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, ['title', 'status', 'published_at', 'created_at', 'updated_at'], true) ? $this->sort : 'updated_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return Post::query()->withTrashed()
            ->select([
                'id', 'author_id', 'post_category_id', 'title', 'slug', 'featured_image',
                'status', 'visibility', 'is_featured', 'published_at', 'scheduled_for',
                'created_at', 'updated_at', 'deleted_at',
            ])
            ->with(['author:id,name', 'category:id,name'])
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->status !== '', fn (Builder $query): Builder => $this->status === 'deleted' ? $query->onlyTrashed() : $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query): Builder => $query->where('post_category_id', $this->category))
            ->when($this->author !== '', fn (Builder $query): Builder => $query->where('author_id', $this->author))
            ->when($this->visibility !== '', fn (Builder $query): Builder => $query->where('visibility', $this->visibility))
            ->when($this->featured !== '', fn (Builder $query): Builder => $query->where('is_featured', $this->featured === '1'))
            ->when($this->dateFrom !== '', fn (Builder $query): Builder => $query->whereRaw('DATE(COALESCE(published_at, scheduled_for)) >= ?', [$this->dateFrom]))
            ->when($this->dateTo !== '', fn (Builder $query): Builder => $query->whereRaw('DATE(COALESCE(published_at, scheduled_for)) <= ?', [$this->dateTo]))
            ->orderBy($sort, $direction)->orderBy('id', $direction)
            ->paginate(in_array($this->perPage, [15, 30, 50], true) ? $this->perPage : 15);
    }

    #[Computed] public function categories() { return PostCategory::query()->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function authors() { return User::query()->whereHas('posts')->orderBy('name')->get(['id', 'name']); }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Post::withTrashed()->count(),
            'published' => Post::query()->published()->count(),
            'scheduled' => Post::query()->scheduled()->count(),
            'drafts' => Post::query()->drafts()->count(),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'author', 'visibility', 'featured', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return collect([$this->search, $this->status, $this->category, $this->author, $this->visibility, $this->featured, $this->dateFrom, $this->dateTo])
            ->contains(fn (string $value): bool => $value !== '');
    }

    public function confirm(int $postId, string $action): void
    {
        $post = Post::withTrashed()->findOrFail($postId);
        Gate::authorize(match ($action) {
            'delete' => 'delete', 'restore' => 'restore', 'force-delete' => 'forceDelete',
            'duplicate' => 'duplicate', 'archive' => 'archive', default => 'publish',
        }, $post);
        $this->targetPostId = $postId;
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(DeletePost $deletePost, DuplicatePost $duplicatePost, ChangePostStatus $changeStatus): void
    {
        $post = Post::withTrashed()->findOrFail($this->targetPostId);
        $actor = Auth::user();
        match ($this->pendingAction) {
            'delete' => $deletePost->delete($actor, $post),
            'restore' => $deletePost->restore($actor, $post),
            'force-delete' => $deletePost->forceDelete($actor, $post),
            'duplicate' => $duplicatePost->handle($actor, $post),
            'publish' => $changeStatus->publish($actor, $post),
            'draft' => $changeStatus->draft($actor, $post),
            'archive' => $changeStatus->archive($actor, $post),
            default => abort(404),
        };
        $this->reset(['showConfirmModal', 'targetPostId', 'pendingAction']);
        unset($this->posts, $this->stats);
        Flux::toast(variant: 'success', text: __('Post action completed successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Blog posts') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Blog posts')" :description="__('Create, schedule, publish, archive, and organize church news and articles.')" :eyebrow="__('Content management')">
        <x-slot:actions>
            @can('viewAny', \App\Models\PostCategory::class)<flux:button :href="route('post-categories.index')" icon="folder" wire:navigate>{{ __('Categories') }}</flux:button>@endcan
            @can('viewAny', \App\Models\Tag::class)<flux:button :href="route('post-tags.index')" icon="tag" wire:navigate>{{ __('Tags') }}</flux:button>@endcan
            @can('create', \App\Models\Post::class)<flux:button :href="route('posts.create')" variant="primary" icon="plus" wire:navigate>{{ __('Add post') }}</flux:button>@endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card :label="__('All posts')" :value="$this->stats['total']" :caption="__('Including deleted')" icon="document-text" />
        <x-admin.stat-card :label="__('Published')" :value="$this->stats['published']" :caption="__('Published records')" icon="globe-alt" tone="green" />
        <x-admin.stat-card :label="__('Scheduled')" :value="$this->stats['scheduled']" :caption="__('Waiting to publish')" icon="clock" tone="blue" />
        <x-admin.stat-card :label="__('Drafts')" :value="$this->stats['drafts']" :caption="__('Work in progress')" icon="pencil-square" tone="gold" />
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-100 p-4 sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search posts')" :placeholder="__('Title, excerpt, content, author, category, or tag')" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach(PostStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="category" :label="__('Category')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach($this->categories as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="author" :label="__('Author')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach($this->authors as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="visibility" :label="__('Visibility')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach(PostVisibility::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="featured" :label="__('Featured')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="1">{{ __('Featured') }}</flux:select.option><flux:select.option value="0">{{ __('Not featured') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="sort" :label="__('Sort')"><flux:select.option value="updated_at">{{ __('Updated') }}</flux:select.option><flux:select.option value="published_at">{{ __('Published') }}</flux:select.option><flux:select.option value="title">{{ __('Title') }}</flux:select.option><flux:select.option value="status">{{ __('Status') }}</flux:select.option></flux:select>
                <flux:input type="date" wire:model.live="dateFrom" :label="__('Created from')" />
                <flux:input type="date" wire:model.live="dateTo" :label="__('Created to')" />
                <flux:select wire:model.live="direction" :label="__('Direction')"><flux:select.option value="desc">{{ __('Newest first') }}</flux:select.option><flux:select.option value="asc">{{ __('Oldest first') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="perPage" :label="__('Per page')"><flux:select.option value="15">15</flux:select.option><flux:select.option value="30">30</flux:select.option><flux:select.option value="50">50</flux:select.option></flux:select>
            </div>
            <div class="flex items-center justify-end gap-3">@if($this->hasActiveFilters())<flux:badge color="blue">{{ __('Filters active') }}</flux:badge>@endif<flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button></div>
        </div>
        <div class="relative p-4 sm:p-6">
            <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 dark:bg-zinc-900/75"><flux:icon.arrow-path class="size-5 animate-spin" /></div>
            @if($this->posts->isEmpty())
                <x-admin.empty-state icon="document-text" :title="__('No posts found')" :description="__('Adjust the filters or create the first blog post.')" />
            @else
                <div class="space-y-3">
                    @foreach($this->posts as $post)
                        <article wire:key="post-{{ $post->id }}" class="flex flex-col gap-4 rounded-xl border border-slate-200 p-4 lg:flex-row lg:items-center dark:border-zinc-700">
                            <div class="flex min-w-0 flex-1 gap-4">
                                <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-church-maroon-950 text-church-gold-400">@if($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<flux:icon.document-text class="size-7" />@endif</div>
                                <div class="min-w-0"><a href="{{ route('posts.show', $post) }}" class="font-semibold hover:underline" wire:navigate>{{ $post->title }}</a><p class="mt-1 text-sm text-slate-500">{{ $post->category?->name ?? __('Uncategorized') }} · {{ $post->author->name }}</p><div class="mt-2 flex flex-wrap gap-1"><flux:badge :color="$post->deleted_at ? 'red' : match($post->status) { PostStatus::Published => 'green', PostStatus::Scheduled => 'blue', PostStatus::Archived => 'zinc', default => 'amber' }">{{ $post->deleted_at ? __('Deleted') : $post->status->label() }}</flux:badge>@if($post->is_featured)<flux:badge color="amber">{{ __('Featured') }}</flux:badge>@endif@if($post->visibility === PostVisibility::Private)<flux:badge color="zinc">{{ __('Private') }}</flux:badge>@endif</div></div>
                            </div>
                            <p class="text-xs text-slate-500">@if($post->status === PostStatus::Scheduled){{ __('Scheduled :date', ['date' => $post->scheduled_for?->format('M j, Y g:i A')]) }}@elseif($post->published_at){{ __('Published :date', ['date' => $post->published_at->format('M j, Y')]) }}@else{{ __('Updated :time', ['time' => $post->updated_at->diffForHumans()]) }}@endif</p>
                            <div class="flex flex-wrap justify-end gap-2">
                                @if(!$post->trashed())
                                    <flux:button size="sm" :href="route('posts.show', $post)" icon="eye" wire:navigate>{{ __('View') }}</flux:button>
                                    @can('update', $post)<flux:button size="sm" :href="route('posts.edit', $post)" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:button>@endcan
                                    @can('duplicate', $post)<flux:button size="sm" wire:click="confirm({{ $post->id }}, 'duplicate')" icon="document-duplicate">{{ __('Duplicate') }}</flux:button>@endcan
                                    @can('publish', $post)<flux:button size="sm" wire:click="confirm({{ $post->id }}, '{{ $post->status === PostStatus::Published ? 'draft' : 'publish' }}')">{{ $post->status === PostStatus::Published ? __('Unpublish') : __('Publish') }}</flux:button>@endcan
                                    @can('archive', $post)<flux:button size="sm" wire:click="confirm({{ $post->id }}, 'archive')">{{ __('Archive') }}</flux:button>@endcan
                                    @can('delete', $post)<flux:button size="sm" variant="danger" wire:click="confirm({{ $post->id }}, 'delete')">{{ __('Delete') }}</flux:button>@endcan
                                @else
                                    @can('restore', $post)<flux:button size="sm" wire:click="confirm({{ $post->id }}, 'restore')">{{ __('Restore') }}</flux:button>@endcan
                                    @can('forceDelete', $post)<flux:button size="sm" variant="danger" wire:click="confirm({{ $post->id }}, 'force-delete')">{{ __('Delete forever') }}</flux:button>@endcan
                                @endif
                            </div>
                        </article>
                    @endforeach
                    <flux:pagination :paginator="$this->posts" />
                </div>
            @endif
        </div>
    </section>
    <flux:modal wire:model="showConfirmModal" class="max-w-lg"><form wire:submit="executeConfirmed" class="space-y-6"><div><flux:heading size="lg">{{ __('Confirm post action') }}</flux:heading><flux:text class="mt-2">{{ __('This action will be recorded in the activity log. Permanent deletion cannot be undone.') }}</flux:text></div><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Confirm') }}</flux:button></div></form></flux:modal>
</div>
