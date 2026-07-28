<?php

use App\Models\SermonSeries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    public SermonSeries $series;

    public function mount(SermonSeries $series): void
    {
        abort_unless($series->status === \App\SermonSeriesStatus::Published, 404);
        $this->series = $series;
    }

    public function title(): string
    {
        return $this->series->title;
    }

    #[Computed]
    public function sermons(): LengthAwarePaginator
    {
        return $this->series->sermons()
            ->publiclyAvailable()
            ->with(['speaker:id,title,first_name,middle_name,last_name', 'series:id,title'])
            ->orderBy('sermon_date')
            ->paginate(12);
    }
};
?>

<div>
    <header class="bg-church-maroon-950 py-16 text-white"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><a href="{{ route('public.sermons.index') }}" class="text-sm font-semibold text-church-gold-400" wire:navigate>&larr; {{ __('All sermons') }}</a><p class="mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-church-gold-400">{{ __('Sermon series') }}</p><h1 class="mt-3 text-4xl font-bold sm:text-5xl">{{ $series->title }}</h1>@if ($series->description)<p class="mt-5 max-w-3xl text-lg leading-8 text-white/75">{{ $series->description }}</p>@endif<div class="mt-5 flex gap-4 text-sm text-white/60">@if ($series->starts_at)<time>{{ $series->starts_at->format('M Y') }}</time>@endif @if ($series->ends_at)<span>&ndash; {{ $series->ends_at->format('M Y') }}</span>@endif<span>{{ trans_choice(':count sermon|:count sermons', $this->sermons->total(), ['count' => $this->sermons->total()]) }}</span></div></div></header>
    <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">@if ($this->sermons->isEmpty())<div class="rounded-2xl border border-dashed p-12 text-center">{{ __('No published sermons are available in this series yet.') }}</div>@else<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">@foreach ($this->sermons as $sermon)<x-sermons.card :sermon="$sermon" wire:key="series-sermon-{{ $sermon->id }}" />@endforeach</div><div class="mt-8"><flux:pagination :paginator="$this->sermons" /></div>@endif</main>
</div>
