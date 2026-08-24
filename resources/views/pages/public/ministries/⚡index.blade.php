<?php

use App\Models\Ministry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Ministries')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function ministries()
    {
        return Ministry::query()->published()->ordered()->with(['leaders' => fn ($query) => $query->limit(1)])->paginate(12);
    }
};
?>

<div>
    <header class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36"><p class="font-semibold text-church-gold-400">{{ __('Church life') }}</p><h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Our ministries') }}</h1><p class="mt-4 max-w-3xl text-lg text-white/80">{{ __('Find a ministry, fellowship, or department where you can grow, serve, and belong.') }}</p></div></header>
    <section class="mx-auto max-w-7xl bg-white px-4 py-14 text-zinc-950 sm:px-6 lg:px-8">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->ministries as $ministry)<a href="{{ route('public.ministries.show', $ministry) }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900" wire:navigate wire:key="public-ministry-{{ $ministry->id }}">@if ($ministry->imageUrl())<img src="{{ $ministry->imageUrl() }}" alt="" class="h-48 w-full object-cover">@endif<div class="p-6"><h2 class="text-xl font-bold">{{ $ministry->name }}</h2><p class="mt-2 line-clamp-3 text-slate-600 dark:text-zinc-300">{{ $ministry->short_description }}</p><p class="mt-4 text-sm font-semibold text-church-maroon-700 dark:text-church-gold-400">{{ __('Learn more') }} →</p></div></a>@empty<p>{{ __('No ministries are currently published.') }}</p>@endforelse
    </div>
    <div class="mt-10">{{ $this->ministries->links() }}</div>
    </section>
</div>
