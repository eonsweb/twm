<?php

use App\Models\Ministry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ministry Details')] class extends Component
{
    public Ministry $ministry;

    public function mount(Ministry $ministry): void
    {
        Gate::authorize('view', $ministry);
        abort_if($ministry->trashed(), 404);
        $this->ministry = $ministry->load(['leaders', 'sermons' => fn ($query) => $query->latest('sermon_date')->limit(10), 'events' => fn ($query) => $query->latest('starts_at')->limit(10), 'creator:id,name', 'updater:id,name']);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('ministries.index')" wire:navigate>{{ __('Ministries') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ $ministry->name }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="$ministry->name" :description="$ministry->short_description" :eyebrow="__('Ministry details')"><x-slot:actions>@can('update', $ministry)<flux:button :href="route('ministries.edit', $ministry)" icon="pencil-square" wire:navigate>{{ __('Edit ministry') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <article class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($ministry->imageUrl())<img src="{{ $ministry->imageUrl() }}" alt="" class="max-h-96 w-full rounded-xl object-cover">@endif
            @foreach ([__('Description') => $ministry->description, __('Mission') => $ministry->mission, __('Vision') => $ministry->vision] as $heading => $content) @if ($content)<section><flux:heading size="lg">{{ $heading }}</flux:heading><p class="mt-2 whitespace-pre-line text-slate-600 dark:text-zinc-300">{{ $content }}</p></section>@endif @endforeach
        </article>
        <aside class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Meeting and contact') }}</flux:heading><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">{{ __('Schedule') }}</dt><dd>{{ collect([$ministry->meeting_day, $ministry->meeting_time?->format('g:i A')])->filter()->implode(' · ') ?: __('Not set') }}</dd></div><div><dt class="text-slate-500">{{ __('Location') }}</dt><dd>{{ $ministry->meeting_location ?: __('Not set') }}</dd></div><div><dt class="text-slate-500">{{ __('Contact') }}</dt><dd>{{ $ministry->contact_email ?: $ministry->contact_phone ?: __('Not set') }}</dd></div></dl></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Leadership') }}</flux:heading><div class="mt-4 space-y-3">@forelse ($ministry->leaders as $leader)<div wire:key="leader-{{ $leader->id }}"><p class="font-semibold">{{ $leader->full_name }}</p><p class="text-sm text-slate-500">{{ $leader->pivot->role_title ?: __('Leader') }}@if ($leader->pivot->is_primary) · {{ __('Primary') }}@endif</p></div>@empty<flux:text>{{ __('No leaders assigned.') }}</flux:text>@endforelse</div></section>
        </aside>
    </div>
</div>
