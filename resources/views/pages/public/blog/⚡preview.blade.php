<?php

use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Post Preview')] class extends Component
{
    public Post $post;

    public function mount(Post $post): void
    {
        Gate::authorize('preview', $post);
        $this->post = $post->load(['author:id,name', 'category:id,name', 'tags:id,name']);
    }
};
?>

<div><div class="bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-950">{{ __('Private signed preview — this article may not be public.') }}</div><article class="mx-auto max-w-4xl px-4 py-12 sm:px-6"><p class="text-sm font-bold uppercase text-church-maroon-700">{{ $post->status->label() }} · {{ $post->category?->name ?? __('Article') }}</p><h1 class="mt-3 text-4xl font-black">{{ $post->title }}</h1><p class="mt-4 text-slate-500">{{ $post->author->name }} · {{ trans_choice(':count minute read|:count minutes read', $post->readingTime()) }}</p>@if($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="{{ $post->featured_image_alt_text }}" class="my-10 aspect-video w-full rounded-2xl object-cover">@endif@if($post->excerpt)<p class="mb-8 text-xl text-slate-600">{{ $post->excerpt }}</p>@endif<div class="prose prose-lg max-w-none dark:prose-invert">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($post->content ?? '') !!}</div></article></div>
