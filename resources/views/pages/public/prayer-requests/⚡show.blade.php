<?php

use App\Models\PrayerRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public PrayerRequest $prayerRequest;

    public function mount(PrayerRequest $prayerRequest): void
    {
        abort_unless($prayerRequest->isPubliclyVisible(), 404);
        $this->prayerRequest = $prayerRequest;
    }

    public function title(): string { return $this->prayerRequest->public_title ?? __('Answered Prayer'); }
};
?>

<main class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8"><a href="{{ route('public.prayer-requests.index') }}" wire:navigate class="text-sm font-semibold text-church-maroon-700 dark:text-church-gold-400">← {{ __('All answered prayers') }}</a><article class="mt-6 rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-sm font-semibold uppercase tracking-wider text-church-maroon-700 dark:text-church-gold-400">{{ __('Answered prayer') }}</p><h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $prayerRequest->public_title }}</h1>@if ($prayerRequest->public_excerpt)<p class="mt-5 text-xl text-slate-600 dark:text-zinc-300">{{ $prayerRequest->public_excerpt }}</p>@endif<div class="mt-8 whitespace-pre-line text-lg leading-8 text-slate-700 dark:text-zinc-300">{{ $prayerRequest->public_content }}</div></article></main>
