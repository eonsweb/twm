@props([
    'icon',
    'title',
    'description',
])

<div {{ $attributes->class(['flex min-h-48 flex-col items-center justify-center px-5 py-8 text-center']) }}>
    <span class="flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
        <flux:icon :icon="$icon" class="size-5" />
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-zinc-200">{{ $title }}</p>
    <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500 dark:text-zinc-500">{{ $description }}</p>
</div>
