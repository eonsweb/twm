<?php

use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Post Details')] class extends Component
{
    public Post $post;

    public function mount(Post $post): void
    {
        Gate::authorize('view', $post);
        $this->post = $post->load(['author:id,name,email', 'category:id,name,slug', 'tags:id,name,slug']);
    }

    #[Computed]
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('blog.preview', now()->addMinutes(30), ['post' => $this->post]);
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('posts.index')" wire:navigate>{{ __('Blog posts') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Details') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="$post->title" :description="__('Post details and publication summary.')" :eyebrow="$post->status->label()"><x-slot:actions>@can('preview', $post)<flux:button :href="$this->previewUrl" target="_blank" icon="eye">{{ __('Preview') }}</flux:button>@endcan @can('update', $post)<flux:button :href="route('posts.edit', $post)" variant="primary" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            @if($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="{{ $post->featured_image_alt_text }}" class="mb-6 aspect-video w-full rounded-xl object-cover">@endif
            @if($post->excerpt)<p class="mb-6 text-lg text-slate-600 dark:text-zinc-300">{{ $post->excerpt }}</p>@endif
            <div class="prose max-w-none dark:prose-invert">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($post->content ?? '') !!}</div>
        </article>
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Publication') }}</flux:heading><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">{{ __('Author') }}</dt><dd class="font-medium">{{ $post->author->name }}</dd></div><div><dt class="text-slate-500">{{ __('Category') }}</dt><dd>{{ $post->category?->name ?? __('Uncategorized') }}</dd></div><div><dt class="text-slate-500">{{ __('Published') }}</dt><dd>{{ $post->published_at?->format('M j, Y g:i A') ?? __('Not published') }}</dd></div><div><dt class="text-slate-500">{{ __('Scheduled') }}</dt><dd>{{ $post->scheduled_for?->format('M j, Y g:i A') ?? __('Not scheduled') }}</dd></div><div><dt class="text-slate-500">{{ __('Reading time') }}</dt><dd>{{ trans_choice(':count minute|:count minutes', $post->readingTime()) }}</dd></div></dl></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Tags') }}</flux:heading><div class="mt-3 flex flex-wrap gap-2">@forelse($post->tags as $tag)<flux:badge>{{ $tag->name }}</flux:badge>@empty<span class="text-sm text-slate-500">{{ __('No tags') }}</span>@endforelse</div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('SEO') }}</flux:heading><p class="mt-3 font-medium">{{ $post->seoTitle() }}</p><p class="mt-1 text-sm text-slate-500">{{ $post->seoDescription() }}</p>@if($post->canonical_url)<p class="mt-2 break-all text-xs text-blue-600">{{ $post->canonical_url }}</p>@endif</div>
        </aside>
    </div>
</div>
