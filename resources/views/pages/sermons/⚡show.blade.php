<?php

use App\Models\Sermon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sermon Preview')] class extends Component
{
    public Sermon $sermon;

    public function mount(Sermon $sermon): void
    {
        Gate::authorize('view', $sermon);
        $this->sermon = $sermon->load(['speaker', 'series', 'topics', 'creator', 'updater']);
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('sermons.index')" wire:navigate>{{ __('Sermons') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Preview') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="$sermon->title" :description="__('Administrative preview. Public visibility rules still apply to the public URL.')" :eyebrow="$sermon->status->label()">
        <x-slot:actions>
            @can('update', $sermon)<flux:button :href="route('sermons.edit', $sermon)" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:button>@endcan
            @if ($sermon->isPubliclyAvailable())<flux:button :href="route('public.sermons.show', $sermon)" variant="primary" icon="arrow-top-right-on-square">{{ __('Open public page') }}</flux:button>@endif
        </x-slot:actions>
    </x-admin.page-header>
    <x-sermons.media-player :sermon="$sermon" />
    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <section class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($sermon->summary)<p class="text-lg leading-8 text-slate-700 dark:text-zinc-300">{{ $sermon->summary }}</p>@endif
            @if ($sermon->description)<div class="whitespace-pre-line text-sm leading-7 text-slate-700 dark:text-zinc-300">{{ $sermon->description }}</div>@endif
        </section>
        <aside class="space-y-3 rounded-xl border border-slate-200 bg-white p-6 text-sm dark:border-zinc-800 dark:bg-zinc-900">
            <p><strong>{{ __('Speaker:') }}</strong> {{ $sermon->speaker->full_name }}</p>
            <p><strong>{{ __('Series:') }}</strong> {{ $sermon->series?->title ?? __('None') }}</p>
            <p><strong>{{ __('Sermon date:') }}</strong> {{ $sermon->sermon_date->format('M j, Y') }}</p>
            <p><strong>{{ __('Scripture:') }}</strong> {{ $sermon->scripture_reference ?: __('Not set') }}</p>
            <p><strong>{{ __('Created by:') }}</strong> {{ $sermon->creator?->name ?? __('Unknown') }}</p>
            <div class="flex flex-wrap gap-2">@foreach ($sermon->topics as $topic)<flux:badge wire:key="preview-topic-{{ $topic->id }}">{{ $topic->name }}</flux:badge>@endforeach</div>
        </aside>
    </div>
</div>
