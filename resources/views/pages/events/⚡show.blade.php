<?php

use App\EventStatus;
use App\Models\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Event Preview')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        Gate::authorize('view', $event);
        abort_if($event->trashed(), 404);
        $this->event = $event->load([
            'eventType:id,name,color',
            'featuredImage:id,disk,path,media_type,visibility,status',
            'creator:id,name',
            'updater:id,name',
        ]);
    }
};
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Preview') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="$event->title" :description="__('Administrative event preview')" :eyebrow="$event->eventType?->name ?? __('Uncategorized')">
        <x-slot:actions>
            @if ($event->isPubliclyAvailable())
                <flux:button :href="route('public.events.show', $event)" icon="arrow-top-right-on-square" target="_blank">{{ __('Public page') }}</flux:button>
            @endif
            @can('update', $event)<flux:button :href="route('events.edit', $event)" variant="primary" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:button>@endcan
        </x-slot:actions>
    </x-admin.page-header>

    @if ($event->status === EventStatus::Cancelled)
        <flux:callout variant="danger" icon="x-circle" heading="{{ __('This event is cancelled') }}">{{ __('The cancellation notice is shown on its public page only if it was published before cancellation.') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            @if ($event->imageUrl())
                <img src="{{ $event->imageUrl() }}" alt="{{ $event->title }}" class="aspect-[16/7] w-full object-cover">
            @else
                <div class="flex aspect-[16/7] items-center justify-center bg-church-maroon-950 text-church-gold-400"><flux:icon.calendar-days class="size-16" /></div>
            @endif
            <div class="space-y-5 p-6">
                <div class="flex flex-wrap gap-2">
                    <flux:badge>{{ $event->status->label() }}</flux:badge>
                    @if ($event->is_featured)<flux:badge color="amber">{{ __('Featured') }}</flux:badge>@endif
                    @if ($event->is_livestreamed)<flux:badge color="blue">{{ __('Livestream') }}</flux:badge>@endif
                </div>
                @if ($event->short_description)<p class="text-lg leading-8 text-slate-600 dark:text-zinc-300">{{ $event->short_description }}</p>@endif
                @if ($event->description)<div class="whitespace-pre-line leading-7 text-slate-700 dark:text-zinc-300">{{ $event->description }}</div>@endif
            </div>
        </article>

        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Event details') }}</flux:heading>
                <dl class="mt-4 space-y-4 text-sm">
                    <div><dt class="font-semibold">{{ __('Date and time') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->formattedDateRange() }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Location') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->locationLabel() }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Timezone') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->timezone }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Created by') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->creator?->name ?? __('Unknown') }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Last updated') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->updated_at->format('M j, Y g:i A') }}</dd></div>
                </dl>
            </div>
        </aside>
    </div>
</div>
