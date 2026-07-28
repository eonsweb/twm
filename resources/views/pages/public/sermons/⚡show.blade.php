<?php

use App\Models\Sermon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Sermon $sermon;

    public function mount(Sermon $sermon): void
    {
        abort_unless($sermon->isPubliclyAvailable(), 404);
        $this->sermon = $sermon->load(['speaker', 'series', 'topics']);
    }

    public function title(): string
    {
        return $this->sermon->seo_title ?: $this->sermon->title;
    }

    #[Computed]
    public function related()
    {
        $topicIds = $this->sermon->topics->modelKeys();

        return Sermon::query()
            ->publiclyAvailable()
            ->whereKeyNot($this->sermon->id)
            ->where(function (Builder $query) use ($topicIds): void {
                $query->where('sermon_series_id', $this->sermon->sermon_series_id)
                    ->orWhere('speaker_id', $this->sermon->speaker_id)
                    ->when($topicIds !== [], fn (Builder $query): Builder => $query->orWhereHas('topics', fn (Builder $topics): Builder => $topics->whereKey($topicIds)));
            })
            ->with(['speaker:id,title,first_name,middle_name,last_name', 'series:id,title'])
            ->orderByDesc('sermon_date')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function previous(): ?Sermon
    {
        return Sermon::query()->publiclyAvailable()->where('sermon_date', '<', $this->sermon->sermon_date)->orderByDesc('sermon_date')->first();
    }

    #[Computed]
    public function next(): ?Sermon
    {
        return Sermon::query()->publiclyAvailable()->where('sermon_date', '>', $this->sermon->sermon_date)->orderBy('sermon_date')->first();
    }
};
?>

@push('meta')
    <meta name="description" content="{{ $sermon->seo_description ?: \Illuminate\Support\Str::limit($sermon->summary, 160) }}">
    <link rel="canonical" href="{{ route('public.sermons.show', $sermon) }}">
    <meta property="og:title" content="{{ $sermon->seo_title ?: $sermon->title }}">
    <meta property="og:description" content="{{ $sermon->seo_description ?: \Illuminate\Support\Str::limit($sermon->summary, 160) }}">
    <meta property="og:url" content="{{ route('public.sermons.show', $sermon) }}">
    <meta property="og:type" content="video.other">
    @if ($sermon->thumbnailUrl())<meta property="og:image" content="{{ $sermon->thumbnailUrl() }}">@endif
    <meta name="twitter:card" content="summary_large_image">
@endpush

<article>
    <header class="bg-church-maroon-950 py-12 text-white">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8"><a href="{{ route('public.sermons.index') }}" class="text-sm font-semibold text-church-gold-400" wire:navigate>&larr; {{ __('All sermons') }}</a><div class="mt-6 flex flex-wrap gap-2 text-sm text-white/70"><time>{{ $sermon->sermon_date->format('F j, Y') }}</time>@if ($sermon->series)<span>&middot;</span><a href="{{ route('public.sermon-series.show', $sermon->series) }}" wire:navigate>{{ $sermon->series->title }}</a>@endif</div><h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">{{ $sermon->title }}</h1><a href="{{ route('public.speakers.show', $sermon->speaker) }}" class="mt-4 inline-flex items-center gap-3 font-semibold text-church-gold-400" wire:navigate>@if ($sermon->speaker->photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($sermon->speaker->photo_path) }}" alt="" class="size-10 rounded-full object-cover">@endif{{ $sermon->speaker->full_name }}</a></div>
    </header>
    <div class="mx-auto max-w-5xl space-y-10 px-4 py-10 sm:px-6 lg:px-8">
        <x-sermons.media-player :sermon="$sermon" />
        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
            <div class="space-y-8">
                @if ($sermon->summary)<p class="text-xl leading-8 text-slate-700 dark:text-zinc-300">{{ $sermon->summary }}</p>@endif
                @if ($sermon->description)<section><h2 class="text-2xl font-bold">{{ __('Sermon notes') }}</h2><div class="mt-4 whitespace-pre-line leading-8 text-slate-700 dark:text-zinc-300">{{ $sermon->description }}</div></section>@endif
            </div>
            <aside class="space-y-5 rounded-2xl border border-stone-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @if ($sermon->scripture_reference)<div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Scripture') }}</p><p class="mt-1 font-semibold">{{ $sermon->scripture_reference }}</p></div>@endif
                @if ($sermon->service_name)<div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Service') }}</p><p class="mt-1">{{ $sermon->service_name }}</p></div>@endif
                @if ($sermon->location)<div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Location') }}</p><p class="mt-1">{{ $sermon->location }}</p></div>@endif
                <a href="{{ $sermon->external_media_url }}" target="_blank" rel="noopener noreferrer nofollow" class="inline-flex w-full justify-center rounded-lg bg-church-maroon-900 px-4 py-3 font-semibold text-white">{{ __('Open on :platform', ['platform' => $sermon->media_platform->label()]) }}</a>
                <div class="flex flex-wrap gap-2">@foreach ($sermon->topics as $topic)<flux:badge wire:key="detail-topic-{{ $topic->id }}">{{ $topic->name }}</flux:badge>@endforeach</div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Share') }}</p><div class="mt-2 flex gap-2"><a class="text-sm underline" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(route('public.sermons.show', $sermon)) }}" target="_blank" rel="noopener noreferrer">{{ __('Facebook') }}</a><a class="text-sm underline" href="https://twitter.com/intent/tweet?url={{ rawurlencode(route('public.sermons.show', $sermon)) }}&text={{ rawurlencode($sermon->title) }}" target="_blank" rel="noopener noreferrer">{{ __('X') }}</a></div></div>
            </aside>
        </div>
        @if ($this->related->isNotEmpty())<section><h2 class="mb-5 text-2xl font-bold">{{ __('Related sermons') }}</h2><div class="grid gap-6 md:grid-cols-3">@foreach ($this->related as $related)<x-sermons.card :sermon="$related" wire:key="related-{{ $related->id }}" />@endforeach</div></section>@endif
        <nav aria-label="{{ __('Sermon navigation') }}" class="flex justify-between gap-4 border-t border-stone-200 pt-6 dark:border-zinc-800">@if ($this->previous)<a href="{{ route('public.sermons.show', $this->previous) }}" wire:navigate>&larr; {{ $this->previous->title }}</a>@else<span></span>@endif @if ($this->next)<a href="{{ route('public.sermons.show', $this->next) }}" class="text-end" wire:navigate>{{ $this->next->title }} &rarr;</a>@endif</nav>
    </div>
</article>
