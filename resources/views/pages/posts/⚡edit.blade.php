<?php

use App\Actions\Blog\SavePost;
use App\Livewire\Forms\PostForm;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\PermissionName;
use App\PostStatus;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Blog Post')] class extends Component
{
    use WithFileUploads;

    public PostForm $form;
    public Post $post;

    public function mount(Post $post): void
    {
        Gate::authorize('update', $post);
        abort_if($post->trashed(), 404);
        $this->post = $post;
        $this->form->setPost($post);
    }

    #[Computed]
    public function categories()
    {
        return PostCategory::query()
            ->where(function ($query): void {
                $query->where('is_active', true);

                if ($this->post->post_category_id !== null) {
                    $query->orWhere('id', $this->post->post_category_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
    #[Computed] public function tags() { return Tag::query()->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function authors() { return User::query()->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function previewUrl(): string { return URL::temporarySignedRoute('blog.preview', now()->addMinutes(30), ['post' => $this->post]); }

    public function saveDraft(SavePost $savePost): void { $this->form->status = PostStatus::Draft->value; $this->persist($savePost); }
    public function save(SavePost $savePost): void { $this->persist($savePost); }

    private function persist(SavePost $savePost): void
    {
        if ($this->form->status === PostStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }
        $this->form->normalize();
        $this->form->validate();
        $this->post = $savePost->handle(
            Auth::user(), $this->form->postData(), $this->form->tagIds,
            $this->form->featuredImage, $this->form->removeFeaturedImage, $this->post,
        );
        $this->form->setPost($this->post);
        $this->form->featuredImage = null;
        Flux::toast(variant: 'success', text: __('Blog post updated successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('posts.index')" wire:navigate>{{ __('Blog posts') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
    <x-admin.page-header :title="__('Edit blog post')" :description="$post->title" :eyebrow="__('Blog')"><x-slot:actions>@can('preview', $post)<flux:button :href="$this->previewUrl" target="_blank" icon="eye">{{ __('Signed preview') }}</flux:button>@endcan<flux:button :href="route('posts.show', $post)" icon="document-text" wire:navigate>{{ __('Details') }}</flux:button></x-slot:actions></x-admin.page-header>
    <form wire:submit="save" class="space-y-6">
        <x-admin.post-form :form="$form" :categories="$this->categories" :tags="$this->tags" :authors="$this->authors" :can-manage-authors="auth()->user()->can(PermissionName::PostsManageAuthors)" :current-image-url="$post->imageUrl()" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('posts.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="button" wire:click="saveDraft">{{ __('Save draft') }}</flux:button><flux:button type="submit" variant="primary" icon="check">{{ __('Update post') }}</flux:button>
        </div>
    </form>
</div>
