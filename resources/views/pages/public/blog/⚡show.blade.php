<?php

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Post $post;

    public function mount(Post $post): void
    {
        abort_unless($post->isPubliclyVisible(), 404);
        $this->post = $post->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);
    }

    public function title(): string { return $this->post->seoTitle(); }

    #[Computed]
    public function related()
    {
        return Post::query()->publiclyVisible()->whereKeyNot($this->post->id)
            ->when($this->post->post_category_id, fn (Builder $query): Builder => $query->where('post_category_id', $this->post->post_category_id))
            ->with(['author:id,name', 'category:id,name,slug'])->orderByRaw('COALESCE(published_at, scheduled_for) DESC')->limit(3)->get();
    }

    #[Computed]
    public function previous(): ?Post { return Post::query()->publiclyVisible()->whereRaw('COALESCE(published_at, scheduled_for) < ?', [$this->post->publicDate()])->orderByRaw('COALESCE(published_at, scheduled_for) DESC')->first(['id', 'title', 'slug']); }
    #[Computed]
    public function next(): ?Post { return Post::query()->publiclyVisible()->whereRaw('COALESCE(published_at, scheduled_for) > ?', [$this->post->publicDate()])->orderByRaw('COALESCE(published_at, scheduled_for) ASC')->first(['id', 'title', 'slug']); }
};
?>

@push('meta')
    <meta name="description" content="{{ $post->seoDescription() }}">
    <link rel="canonical" href="{{ $post->canonical_url ?: route('blog.show', $post) }}">
    <meta property="og:title" content="{{ $post->seoTitle() }}"><meta property="og:description" content="{{ $post->seoDescription() }}"><meta property="og:type" content="article"><meta property="og:url" content="{{ route('blog.show', $post) }}">
    @if($post->imageUrl())<meta property="og:image" content="{{ $post->imageUrl() }}">@endif
@endpush

<div>
    <article>
        <header class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-4xl px-4 pb-16 pt-28 text-center sm:px-6 sm:pb-20 sm:pt-32 lg:pt-36"><p class="text-sm font-bold uppercase tracking-widest text-church-gold-400">{{ $post->category?->name ?? __('Article') }}</p><h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">{{ $post->title }}</h1><p class="mt-5 text-white/70"><a href="{{ route('blog.author', $post->author) }}" class="hover:underline" wire:navigate>{{ $post->author->name }}</a> · {{ $post->publicDate()?->format('F j, Y') }}@if($post->updated_at->gt($post->publicDate())) · {{ __('Updated :date', ['date' => $post->updated_at->format('M j, Y')]) }}@endif · {{ trans_choice(':count minute read|:count minutes read', $post->readingTime()) }}</p></div></header>
        <div class="mx-auto max-w-5xl bg-white px-4 py-12 text-zinc-950 sm:px-6">@if($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="{{ $post->featured_image_alt_text }}" class="mb-10 aspect-video w-full rounded-2xl object-cover shadow-lg">@endif<div class="mx-auto max-w-3xl">@if($post->excerpt)<p class="mb-8 border-s-4 border-church-gold-500 ps-5 text-xl leading-8 text-slate-600 dark:text-zinc-300">{{ $post->excerpt }}</p>@endif<div class="prose prose-lg max-w-none dark:prose-invert">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($post->content ?? '') !!}</div><div class="mt-10 flex flex-wrap gap-2">@foreach($post->tags as $tag)<a href="{{ route('blog.tag', $tag) }}" wire:navigate><flux:badge>{{ $tag->name }}</flux:badge></a>@endforeach</div><div class="mt-10 flex flex-wrap gap-3 border-y border-stone-200 py-5 dark:border-zinc-800"><span class="font-semibold">{{ __('Share:') }}</span><a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('blog.show', $post)) }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">Facebook</a><a href="https://twitter.com/intent/tweet?url={{ urlencode(route('blog.show', $post)) }}&text={{ urlencode($post->title) }}" target="_blank" rel="noopener" class="hover:underline">X</a><a href="mailto:?subject={{ rawurlencode($post->title) }}&body={{ rawurlencode(route('blog.show', $post)) }}" class="hover:underline">{{ __('Email') }}</a></div></div></div>
    </article>
    <nav class="mx-auto grid max-w-5xl gap-4 px-4 sm:grid-cols-2 sm:px-6">@if($this->previous)<a href="{{ route('blog.show', $this->previous) }}" class="rounded-xl border border-stone-200 p-5 dark:border-zinc-800" wire:navigate><span class="text-xs text-slate-500">{{ __('Previous') }}</span><strong class="mt-1 block">{{ $this->previous->title }}</strong></a>@endif @if($this->next)<a href="{{ route('blog.show', $this->next) }}" class="rounded-xl border border-stone-200 p-5 sm:text-right dark:border-zinc-800" wire:navigate><span class="text-xs text-slate-500">{{ __('Next') }}</span><strong class="mt-1 block">{{ $this->next->title }}</strong></a>@endif</nav>
    @if($this->related->isNotEmpty())<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6"><h2 class="text-2xl font-black">{{ __('Related articles') }}</h2><div class="mt-6 grid gap-6 md:grid-cols-3">@foreach($this->related as $related)<a href="{{ route('blog.show', $related) }}" class="rounded-xl border border-stone-200 p-5 hover:shadow-md dark:border-zinc-800" wire:navigate><span class="text-xs text-slate-500">{{ $related->category?->name }}</span><strong class="mt-2 block">{{ $related->title }}</strong></a>@endforeach</div></section>@endif
</div>
