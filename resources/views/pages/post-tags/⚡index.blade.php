<?php

use App\Activity\ActivityLogger;
use App\Models\Tag;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Post Tags')] class extends Component
{
    public ?int $tagId = null;
    public string $name = '';
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    #[Url] public string $search = '';

    public function mount(): void { Gate::authorize('viewAny', Tag::class); }
    #[Computed] public function tags() { return Tag::query()->withCount('posts')->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))->orderBy('name')->get(); }
    public function create(): void { Gate::authorize('create', Tag::class); $this->reset(['tagId', 'name']); $this->showFormModal = true; }
    public function edit(int $id): void { $tag = Tag::findOrFail($id); Gate::authorize('update', $tag); $this->tagId = $tag->id; $this->name = $tag->name; $this->showFormModal = true; }

    public function save(ActivityLogger $logger): void
    {
        $tag = $this->tagId ? Tag::findOrFail($this->tagId) : new Tag;
        Gate::authorize($tag->exists ? 'update' : 'create', $tag->exists ? $tag : Tag::class);
        $data = $this->validate(['name' => ['required', 'string', 'max:100', Rule::unique(Tag::class)->ignore($tag->id)]]);
        $event = $tag->exists ? 'updated' : 'created';
        $tag->fill(['name' => str($data['name'])->squish(), 'slug' => Tag::uniqueSlug($data['name'], $tag->id)])->save();
        $logger->log(logName: 'blog', event: "post-tag.{$event}", description: str($event)->headline()." post tag \"{$tag->name}\".", subject: $tag, causer: Auth::user());
        $this->showFormModal = false; unset($this->tags);
        Flux::toast(variant: 'success', text: __('Tag saved.'));
    }

    public function confirmDelete(int $id): void { $tag = Tag::findOrFail($id); Gate::authorize('delete', $tag); $this->tagId = $id; $this->showDeleteModal = true; }
    public function delete(ActivityLogger $logger): void
    {
        $tag = Tag::findOrFail($this->tagId); Gate::authorize('delete', $tag); $name = $tag->name; $tag->posts()->detach(); $tag->delete();
        $logger->log(logName: 'blog', event: 'post-tag.deleted', description: "Deleted post tag \"{$name}\".", causer: Auth::user());
        $this->showDeleteModal = false; unset($this->tags);
        Flux::toast(variant: 'success', text: __('Tag deleted.'));
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('posts.index')" wire:navigate>{{ __('Blog posts') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Tags') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Post tags')" :description="__('Maintain reusable topics for article discovery.')" :eyebrow="__('Blog')"><x-slot:actions>@can('create', \App\Models\Tag::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add tag') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search tags')" />
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse($this->tags as $tag)<div wire:key="tag-{{ $tag->id }}" class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-zinc-700"><div><h2 class="font-semibold">{{ $tag->name }}</h2><p class="text-xs text-slate-500">{{ trans_choice(':count post|:count posts', $tag->posts_count) }}</p></div><div class="flex gap-1">@can('update', $tag)<flux:button size="sm" wire:click="edit({{ $tag->id }})" icon="pencil-square" />@endcan @can('delete', $tag)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $tag->id }})" icon="trash" />@endcan</div></div>@empty<x-admin.empty-state icon="tag" :title="__('No tags')" :description="__('Create the first tag.')" />@endforelse</div></section>
    <flux:modal wire:model="showFormModal" class="max-w-lg"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $tagId ? __('Edit tag') : __('Add tag') }}</flux:heading><flux:input wire:model="name" :label="__('Name')" required /><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div></form></flux:modal>
    <flux:modal wire:model="showDeleteModal" class="max-w-lg"><form wire:submit="delete" class="space-y-5"><flux:heading size="lg">{{ __('Delete tag?') }}</flux:heading><flux:text>{{ __('The tag will be detached from all posts.') }}</flux:text><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button></div></form></flux:modal>
</div>
