<?php

use App\Actions\Sermons\SaveSermonSeries;
use App\Models\SermonSeries;
use App\SermonSeriesStatus;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Sermon Series')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $seriesId = null;
    public string $title = '';
    public string $slug = '';
    public string $description = '';
    public string $startsAt = '';
    public string $endsAt = '';
    public string $status = 'draft';
    public bool $isFeatured = false;
    public mixed $cover = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', SermonSeries::class);
    }

    #[Computed]
    public function items()
    {
        return SermonSeries::query()->withCount('sermons')->orderByDesc('created_at')->paginate(15);
    }

    public function create(): void
    {
        Gate::authorize('create', SermonSeries::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $series = SermonSeries::findOrFail($id);
        Gate::authorize('update', $series);
        $this->seriesId = $series->id;
        $this->title = $series->title;
        $this->slug = $series->slug;
        $this->description = $series->description ?? '';
        $this->startsAt = $series->starts_at?->toDateString() ?? '';
        $this->endsAt = $series->ends_at?->toDateString() ?? '';
        $this->status = $series->status->value;
        $this->isFeatured = $series->is_featured;
        $this->showForm = true;
    }

    public function save(SaveSermonSeries $saveSeries): void
    {
        $series = $this->seriesId ? SermonSeries::findOrFail($this->seriesId) : null;
        $this->slug = Str::slug($this->slug ?: $this->title);
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique(SermonSeries::class, 'slug')->ignore($this->seriesId)],
            'description' => ['nullable', 'string', 'max:10000'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'status' => ['required', Rule::enum(SermonSeriesStatus::class)],
            'isFeatured' => ['boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $saveSeries->handle(Auth::user(), [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'starts_at' => $validated['startsAt'] ?: null,
            'ends_at' => $validated['endsAt'] ?: null,
            'status' => $validated['status'],
            'is_featured' => $validated['isFeatured'],
        ], $this->cover, $series);
        $this->resetForm();
        unset($this->items);
        Flux::toast(variant: 'success', text: __('Sermon series saved.'));
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'seriesId', 'title', 'slug', 'description', 'startsAt', 'endsAt', 'cover', 'isFeatured']);
        $this->status = SermonSeriesStatus::Draft->value;
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Series') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Sermon series')" :description="__('Group related sermons into public collections.')" :eyebrow="__('Sermons')"><x-slot:actions>@can('create', SermonSeries::class)<flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add series') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        @if ($this->items->isEmpty())<x-admin.empty-state icon="rectangle-stack" :title="__('No sermon series')" :description="__('Create a series when sermons belong to a shared teaching theme.')" />@else
            <flux:table :paginate="$this->items"><flux:table.columns><flux:table.column>{{ __('Series') }}</flux:table.column><flux:table.column>{{ __('Status') }}</flux:table.column><flux:table.column>{{ __('Sermons') }}</flux:table.column><flux:table.column>{{ __('Dates') }}</flux:table.column><flux:table.column align="end">{{ __('Actions') }}</flux:table.column></flux:table.columns><flux:table.rows>@foreach ($this->items as $item)<flux:table.row :key="$item->id" wire:key="series-row-{{ $item->id }}"><flux:table.cell><p class="font-semibold">{{ $item->title }}</p><p class="text-xs text-slate-500">/{{ $item->slug }}</p></flux:table.cell><flux:table.cell><flux:badge>{{ $item->status->label() }}</flux:badge></flux:table.cell><flux:table.cell>{{ $item->sermons_count }}</flux:table.cell><flux:table.cell>{{ $item->starts_at?->format('M Y') ?? '—' }} &ndash; {{ $item->ends_at?->format('M Y') ?? '—' }}</flux:table.cell><flux:table.cell align="end">@can('update', $item)<flux:button wire:click="edit({{ $item->id }})" size="sm" variant="ghost" icon="pencil-square" />@endcan</flux:table.cell></flux:table.row>@endforeach</flux:table.rows></flux:table>
        @endif
    </section>
    <flux:modal wire:model="showForm" class="max-w-2xl"><form wire:submit="save" class="space-y-5"><flux:heading size="lg">{{ $seriesId ? __('Edit series') : __('Add series') }}</flux:heading><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="title" :label="__('Title')" required /><flux:input wire:model="slug" :label="__('Slug')" /><flux:input wire:model="startsAt" :label="__('Start date')" type="date" /><flux:input wire:model="endsAt" :label="__('End date')" type="date" /><flux:select wire:model="status" :label="__('Status')">@foreach (SermonSeriesStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:switch wire:model="isFeatured" :label="__('Featured')" /></div><flux:textarea wire:model="description" :label="__('Description')" rows="5" /><flux:input wire:model="cover" :label="__('Cover image')" type="file" accept="image/jpeg,image/png,image/webp" /><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save series') }}</flux:button></div></form></flux:modal>
</div>
