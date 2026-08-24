<?php

use App\Models\Person;
use App\Models\Sermon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Sermons')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $speaker = '';
    #[Url]
    public string $year = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function sermons(): LengthAwarePaginator
    {
        return Sermon::query()
            ->publiclyAvailable()
            ->with('speaker:id,title,first_name,middle_name,last_name,slug')
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('title', 'like', $search)
                    ->orWhere('summary', 'like', $search)
                    ->orWhere('scripture_reference', 'like', $search));
            })
            ->when($this->speaker !== '', fn (Builder $query): Builder => $query->where('speaker_id', $this->speaker))
            ->when($this->year !== '', fn (Builder $query): Builder => $query->whereYear('sermon_date', $this->year))
            ->orderByDesc('sermon_date')
            ->orderByDesc('id')
            ->paginate(12);
    }

    #[Computed]
    public function featured(): ?Sermon
    {
        return Sermon::query()
            ->publiclyAvailable()
            ->where('is_featured', true)
            ->with('speaker:id,title,first_name,middle_name,last_name')
            ->orderBy('display_order')
            ->orderByDesc('sermon_date')
            ->first();
    }

    #[Computed]
    public function filters(): array
    {
        return [
            'speakers' => Person::query()->publicSpeakers()->orderBy('first_name')->get(['id', 'title', 'first_name', 'middle_name', 'last_name']),
            'years' => Sermon::query()
                ->publiclyAvailable()
                ->select('sermon_date')
                ->distinct()
                ->orderByDesc('sermon_date')
                ->get()
                ->map(fn (Sermon $sermon): int => $sermon->sermon_date->year)
                ->unique()
                ->values(),
        ];
    }
};
?>

<div>
    <section class="bg-church-maroon-950 text-white">
        <div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36"><p class="text-sm font-semibold uppercase tracking-[0.2em] text-church-gold-400">{{ __('Grow in the Word') }}</p><h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Sermons') }}</h1><p class="mt-4 max-w-2xl text-lg leading-8 text-white/75">{{ __('Watch, listen, and revisit biblical teaching from our church family.') }}</p></div>
    </section>
    <div class="mx-auto max-w-7xl space-y-10 bg-white px-4 py-10 text-zinc-950 sm:px-6 lg:px-8">
        @if ($this->featured)
            <section aria-labelledby="featured-sermon"><h2 id="featured-sermon" class="mb-4 text-2xl font-bold">{{ __('Featured sermon') }}</h2><div class="grid overflow-hidden rounded-2xl bg-church-maroon-950 text-white shadow-xl lg:grid-cols-2"><div class="aspect-video lg:aspect-auto">@if ($this->featured->thumbnailUrl())<img src="{{ $this->featured->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">@endif</div><div class="flex flex-col justify-center p-7 sm:p-10"><p class="text-sm font-semibold text-church-gold-400">{{ $this->featured->speaker->full_name }}</p><h3 class="mt-2 text-3xl font-bold">{{ $this->featured->title }}</h3><p class="mt-4 line-clamp-3 text-white/75">{{ $this->featured->summary }}</p><a href="{{ route('public.sermons.show', $this->featured) }}" class="mt-6 inline-flex w-fit rounded-lg bg-church-gold-500 px-5 py-3 font-semibold text-church-maroon-950" wire:navigate>{{ __('Watch or listen') }}</a></div></div></section>
        @endif
        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search')" />
                <flux:select wire:model.live="speaker" :label="__('Speaker')"><flux:select.option value="">{{ __('All speakers') }}</flux:select.option>@foreach ($this->filters['speakers'] as $item)<flux:select.option :value="$item->id">{{ $item->full_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="year" :label="__('Year')"><flux:select.option value="">{{ __('All years') }}</flux:select.option>@foreach ($this->filters['years'] as $item)<flux:select.option :value="$item">{{ $item }}</flux:select.option>@endforeach</flux:select>
            </div>
        </section>
        <section class="relative">
            <div wire:loading.flex class="absolute inset-0 z-10 items-start justify-center bg-stone-50/75 pt-24 backdrop-blur-sm dark:bg-zinc-950/75"><flux:icon.arrow-path class="size-6 animate-spin" /></div>
            @if ($this->sermons->isEmpty())<div class="rounded-2xl border border-dashed border-stone-300 p-12 text-center dark:border-zinc-700"><h2 class="text-xl font-bold">{{ __('No sermons found') }}</h2><p class="mt-2 text-slate-500">{{ __('Try adjusting your search or filters.') }}</p></div>@else<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">@foreach ($this->sermons as $sermon)<x-sermons.card :sermon="$sermon" wire:key="public-sermon-{{ $sermon->id }}" />@endforeach</div><div class="mt-8"><flux:pagination :paginator="$this->sermons" /></div>@endif
        </section>
    </div>
</div>
