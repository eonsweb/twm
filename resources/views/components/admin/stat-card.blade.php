@props([
    'label',
    'value',
    'caption',
    'icon',
    'tone' => 'maroon',
])

@php
    $tones = [
        'gold' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
        'maroon' => 'bg-church-maroon-50 text-church-maroon-700 dark:bg-church-maroon-700/20 dark:text-church-gold-300',
        'blue' => 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
        'green' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
        'purple' => 'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300',
        'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300',
    ];
@endphp

<article {{ $attributes->class(['rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-600 dark:text-zinc-400">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $value }}</p>
        </div>

        <span class="{{ $tones[$tone] ?? $tones['maroon'] }} flex size-10 shrink-0 items-center justify-center rounded-lg">
            <flux:icon :icon="$icon" class="size-5" />
        </span>
    </div>

    <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-zinc-500">{{ $caption }}</p>
</article>
