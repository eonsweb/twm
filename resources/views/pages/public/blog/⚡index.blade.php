<?php

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Blog')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $category = '';

    public function updated(): void { $this->resetPage(); }

    #[Computed]
    public function featured(): ?Post
    {
        return Post::query()->publiclyVisible()->featured()->with(['author:id,name', 'category:id,name,slug'])->orderByRaw('COALESCE(published_at, scheduled_for) DESC')->first();
    }

    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return Post::query()->publiclyVisible()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->when($this->featured, fn (Builder $query): Builder => $query->whereKeyNot($this->featured->id))
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->category !== '', fn (Builder $query): Builder => $query->whereHas('category', fn (Builder $category): Builder => $category->where('slug', $this->category)))
            ->orderByRaw('COALESCE(published_at, scheduled_for) DESC')->paginate(9);
    }

    #[Computed]
    public function categories()
    {
        return PostCategory::query()->where('is_active', true)->whereHas('posts', fn (Builder $query): Builder => $query->publiclyVisible())->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
    }
};
?>

<div>
    <section class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36"><p class="text-sm font-bold uppercase tracking-[0.2em] text-church-gold-400">{{ __('Stories & teaching') }}</p><h1 class="mt-3 text-4xl font-black tracking-tight sm:text-5xl">{{ __('Church blog') }}</h1><p class="mt-5 max-w-2xl text-lg leading-8 text-white/75">{{ __('News, biblical encouragement, testimonies, and practical resources for life and ministry.') }}</p></div></section>
    <section class="mx-auto max-w-7xl space-y-10 bg-white px-4 py-12 text-zinc-950 sm:px-6 lg:px-8">
        @if($this->featured)
            <article class="grid overflow-hidden rounded-2xl bg-church-maroon-900 text-white shadow-xl lg:grid-cols-2">@if($this->featured->imageUrl())<img src="{{ $this->featured->imageUrl() }}" alt="{{ $this->featured->featured_image_alt_text }}" class="h-full min-h-72 w-full object-cover">@else<div class="min-h-72 bg-church-maroon-800"></div>@endif<div class="flex flex-col justify-center p-8 sm:p-12"><p class="text-sm font-bold uppercase tracking-widest text-church-gold-400">{{ __('Featured') }} · {{ $this->featured->category?->name ?? __('Article') }}</p><h2 class="mt-3 text-3xl font-black">{{ $this->featured->title }}</h2><p class="mt-4 leading-7 text-white/75">{{ $this->featured->excerpt }}</p><p class="mt-5 text-sm text-white/60">{{ $this->featured->author->name }} · {{ $this->featured->publicDate()?->format('M j, Y') }} · {{ trans_choice(':count min read|:count mins read', $this->featured->readingTime()) }}</p><a href="{{ route('blog.show', $this->featured) }}" class="mt-7 font-bold text-church-gold-400 hover:underline" wire:navigate>{{ __('Read article →') }}</a></div></article>
        @endif
        <div class="rounded-2xl border border-stone-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]"><flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search articles')" /><flux:select wire:model.live="category" :label="__('Category')"><flux:select.option value="">{{ __('All categories') }}</flux:select.option>@foreach($this->categories as $item)<flux:select.option :value="$item->slug">{{ $item->name }}</flux:select.option>@endforeach</flux:select></div></div>
        <div class="relative"><div wire:loading.flex class="absolute inset-0 z-10 items-start justify-center bg-stone-50/75 pt-20 dark:bg-zinc-950/75"><flux:icon.arrow-path class="size-6 animate-spin" /></div>
            @if($this->posts->isEmpty())<div class="rounded-2xl border border-dashed border-stone-300 p-12 text-center dark:border-zinc-700"><h2 class="text-xl font-bold">{{ __('No articles found') }}</h2><p class="mt-2 text-slate-500">{{ __('Try a different search or category.') }}</p></div>
            @else<div class="grid gap-7 md:grid-cols-2 lg:grid-cols-3">@foreach($this->posts as $post)<article wire:key="public-post-{{ $post->id }}" class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900">@if($post->imageUrl())<a href="{{ route('blog.show', $post) }}" wire:navigate><img src="{{ $post->imageUrl() }}" alt="{{ $post->featured_image_alt_text }}" class="aspect-[16/9] w-full object-cover"></a>@endif<div class="p-6"><p class="text-xs font-bold uppercase tracking-wider text-church-maroon-700 dark:text-church-gold-400">{{ $post->category?->name ?? __('Article') }}</p><h2 class="mt-2 text-xl font-bold"><a href="{{ route('blog.show', $post) }}" class="hover:text-church-maroon-700" wire:navigate>{{ $post->title }}</a></h2><p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $post->excerpt ?: str(strip_tags($post->content))->limit(150) }}</p><p class="mt-5 text-xs text-slate-500">{{ $post->author->name }} · {{ $post->publicDate()?->format('M j, Y') }} · {{ $post->readingTime() }} min</p></div></article>@endforeach</div><div class="mt-8"><flux:pagination :paginator="$this->posts" /></div>@endif
        </div>
    </section>
</div>
