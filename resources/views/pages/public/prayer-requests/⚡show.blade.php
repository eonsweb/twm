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

    public function title(): string
    {
        return $this->prayerRequest->public_title ?? __('Answered Prayer');
    }
};
?>

<div>
    <header class="bg-church-maroon-950 text-white">
        <div class="mx-auto max-w-3xl px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:px-8 lg:pt-36">
            <a href="{{ route('public.prayer-requests.index') }}" wire:navigate class="text-sm font-semibold text-church-gold-400">&larr; {{ __('All answered prayers') }}</a>
            <p class="mt-6 text-sm font-semibold uppercase tracking-wider text-church-gold-400">{{ __('Answered prayer') }}</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">{{ $prayerRequest->public_title }}</h1>
            @if ($prayerRequest->public_excerpt)
                <p class="mt-5 text-xl text-white/80">{{ $prayerRequest->public_excerpt }}</p>
            @endif
        </div>
    </header>

    <article class="mx-auto max-w-3xl bg-white px-4 py-14 text-zinc-950 sm:px-6 lg:px-8">
        <div class="whitespace-pre-line text-lg leading-8 text-slate-700 dark:text-zinc-300">{{ $prayerRequest->public_content }}</div>
    </article>
</div>
