<?php

use App\Actions\Blog\SavePost;
use App\Livewire\Forms\PostForm;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\PermissionName;
use App\PostStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create Blog Post')] class extends Component
{
    use WithFileUploads;

    public PostForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Post::class);
        $this->form->authorId = Auth::id();
    }

    #[Computed] public function categories() { return PostCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function tags() { return Tag::query()->orderBy('name')->get(['id', 'name']); }
    #[Computed] public function authors() { return User::query()->orderBy('name')->get(['id', 'name']); }

    public function saveDraft(SavePost $savePost): void { $this->form->status = PostStatus::Draft->value; $this->persist($savePost); }
    public function save(SavePost $savePost): void { $this->persist($savePost); }

    private function persist(SavePost $savePost): void
    {
        if ($this->form->status === PostStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }
        $this->form->normalize();
        $this->form->validate();
        $post = $savePost->handle(Auth::user(), $this->form->postData(), $this->form->tagIds, $this->form->featuredImage);
        session()->flash('success', __('Blog post created successfully.'));
        $this->redirectRoute('posts.edit', $post, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('posts.index')" wire:navigate>{{ __('Blog posts') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Create blog post')" :description="__('Write an article, configure publication, upload a featured image, and prepare search metadata.')" :eyebrow="__('Blog')" />
    <form wire:submit="save" class="space-y-6">
        <x-admin.post-form :form="$form" :categories="$this->categories" :tags="$this->tags" :authors="$this->authors" :can-manage-authors="auth()->user()->can(PermissionName::PostsManageAuthors)" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('posts.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled">{{ __('Save draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Create post') }}</flux:button>
        </div>
    </form>
</div>
