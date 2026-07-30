<?php

use App\Activity\ActivityLogger;
use App\Models\PostCategory;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Post Categories')] class extends Component
{
    public ?int $categoryId = null;
    public string $name = '';
    public string $description = '';
    public bool $isActive = true;
    public int $sortOrder = 0;
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    #[Url] public string $search = '';

    public function mount(): void { Gate::authorize('viewAny', PostCategory::class); }
    #[Computed] public function categories() { return PostCategory::query()->withCount('posts')->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))->orderBy('sort_order')->orderBy('name')->get(); }

    public function create(): void
    {
        Gate::authorize('create', PostCategory::class);
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $category = PostCategory::findOrFail($id);
        Gate::authorize('update', $category);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->isActive = $category->is_active;
        $this->sortOrder = $category->sort_order;
        $this->showFormModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $category = $this->categoryId ? PostCategory::findOrFail($this->categoryId) : new PostCategory;
        Gate::authorize($category->exists ? 'update' : 'create', $category->exists ? $category : PostCategory::class);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(PostCategory::class)->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0'],
        ]);
        $event = $category->exists ? 'updated' : 'created';
        $category->fill(['name' => str($data['name'])->squish(), 'slug' => PostCategory::uniqueSlug($data['name'], $category->id), 'description' => $data['description'] ?: null, 'is_active' => $data['isActive'], 'sort_order' => $data['sortOrder']])->save();
        $logger->log(logName: 'blog', event: "post-category.{$event}", description: str($event)->headline()." post category \"{$category->name}\".", subject: $category, causer: Auth::user());
        $this->showFormModal = false;
        unset($this->categories);
        Flux::toast(variant: 'success', text: __('Category saved.'));
    }

    public function confirmDelete(int $id): void
    {
        $category = PostCategory::findOrFail($id);
        Gate::authorize('delete', $category);
        $this->categoryId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $category = PostCategory::withCount('posts')->findOrFail($this->categoryId);
        Gate::authorize('delete', $category);
        if ($category->posts_count > 0) {
            $this->addError('delete', __('Move the category’s posts before deleting it.'));
            return;
        }
        $name = $category->name;
        $category->delete();
        $logger->log(logName: 'blog', event: 'post-category.deleted', description: "Deleted post category \"{$name}\".", causer: Auth::user());
        $this->showDeleteModal = false;
        unset($this->categories);
        Flux::toast(variant: 'success', text: __('Category deleted.'));
    }

    private function resetForm(): void
    {
        $this->reset(['categoryId', 'name', 'description', 'showDeleteModal']);
        $this->isActive = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('posts.index')" wire:navigate>{{ __('Blog posts') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Categories') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Post categories')" :description="__('Organize articles into stable, browsable sections.')" :eyebrow="__('Blog')"><x-slot:actions>@can('create', \App\Models\PostCategory::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add category') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search categories')" />
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-3">@forelse($this->categories as $category)<div wire:key="category-{{ $category->id }}" class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-4 dark:border-zinc-700"><div><div class="flex items-center gap-2"><h2 class="font-semibold">{{ $category->name }}</h2>@unless($category->is_active)<flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>@endunless</div><p class="mt-1 text-sm text-slate-500">{{ $category->description ?: __('No description') }} · {{ trans_choice(':count post|:count posts', $category->posts_count) }}</p></div><div class="flex gap-2">@can('update', $category)<flux:button size="sm" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</flux:button>@endcan @can('delete', $category)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $category->id }})">{{ __('Delete') }}</flux:button>@endcan</div></div>@empty<x-admin.empty-state icon="folder" :title="__('No categories')" :description="__('Create the first category.')" />@endforelse</div>
    </section>
    <flux:modal wire:model="showFormModal" class="max-w-xl"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $categoryId ? __('Edit category') : __('Add category') }}</flux:heading><flux:input wire:model="name" :label="__('Name')" required /><flux:textarea wire:model="description" :label="__('Description')" rows="4" /><div class="grid grid-cols-2 gap-4"><flux:input type="number" min="0" wire:model="sortOrder" :label="__('Sort order')" /><flux:switch wire:model="isActive" :label="__('Active')" /></div><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div></form></flux:modal>
    <flux:modal wire:model="showDeleteModal" class="max-w-lg"><form wire:submit="delete" class="space-y-5"><flux:heading size="lg">{{ __('Delete category?') }}</flux:heading><flux:text>{{ __('Categories with posts cannot be deleted.') }}</flux:text>@error('delete')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror<div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button></div></form></flux:modal>
</div>
