<?php

use App\Models\PrayerRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Answered Prayers')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function stories()
    {
        return PrayerRequest::query()->published()
            ->select(['id', 'public_token', 'public_title', 'public_excerpt', 'published_at'])
            ->latest('published_at')->paginate(12);
    }
};
?>

<main class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"><header class="max-w-3xl"><p class="font-semibold text-church-maroon-700 dark:text-church-gold-400">{{ __('Faith stories') }}</p><h1 class="mt-2 text-4xl font-bold tracking-tight">{{ __('Answered prayers') }}</h1><p class="mt-4 text-lg text-slate-600 dark:text-zinc-300">{{ __('Approved, de-identified stories shared with explicit permission.') }}</p></header><div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">@forelse ($this->stories as $story)<a href="{{ route('public.prayer-requests.show', $story) }}" wire:navigate wire:key="prayer-story-{{ $story->id }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900"><p class="text-xs font-semibold uppercase tracking-wider text-church-maroon-700 dark:text-church-gold-400">{{ __('Answered prayer') }}</p><h2 class="mt-3 text-xl font-bold">{{ $story->public_title }}</h2><p class="mt-3 line-clamp-4 text-slate-600 dark:text-zinc-300">{{ $story->public_excerpt }}</p><p class="mt-5 text-sm text-slate-500">{{ $story->published_at?->format('F Y') }}</p></a>@empty<div class="md:col-span-2 lg:col-span-3"><x-admin.empty-state icon="hand-raised" :title="__('No stories published yet')" :description="__('Please check again later.')" /></div>@endforelse</div>@if ($this->stories->hasPages())<div class="mt-10">{{ $this->stories->links() }}</div>@endif</main>
