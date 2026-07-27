@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.14em] text-church-maroon-700 dark:text-church-gold-400">
                {{ $eyebrow }}
            </p>
        @endif

        <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
            {{ $title }}
        </h1>

        @if ($description)
            <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-600 dark:text-zinc-400">
                {{ $description }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
