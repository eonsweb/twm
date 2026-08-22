@props(['sermon'])

<article {{ $attributes->class(['group overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900']) }}>
    <a href="{{ route('public.sermons.show', $sermon) }}" class="block" wire:navigate>
        <div class="relative aspect-video overflow-hidden bg-church-maroon-950">
            @if ($sermon->thumbnailUrl())
                <img src="{{ $sermon->thumbnailUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            @else
                <div class="flex h-full items-center justify-center text-church-gold-400"><flux:icon.play-circle class="size-14" /></div>
            @endif
            <span class="absolute bottom-3 start-3 rounded-full bg-black/70 px-2.5 py-1 text-xs font-semibold text-white">
                {{ $sermon->media_platform->label() }}
            </span>
        </div>
        <div class="space-y-3 p-5">
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-zinc-400">
                <time datetime="{{ $sermon->sermon_date->toDateString() }}">{{ $sermon->sermon_date->format('M j, Y') }}</time>
            </div>
            <h2 class="text-xl font-bold tracking-tight text-slate-950 group-hover:text-church-maroon-800 dark:text-white dark:group-hover:text-church-gold-400">{{ $sermon->title }}</h2>
            <p class="text-sm font-semibold text-church-maroon-700 dark:text-church-gold-400">{{ $sermon->speaker->full_name }}</p>
            @if ($sermon->summary)
                <p class="line-clamp-3 text-sm leading-6 text-slate-600 dark:text-zinc-400">{{ $sermon->summary }}</p>
            @endif
        </div>
    </a>
</article>
