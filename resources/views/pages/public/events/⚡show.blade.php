<?php

use App\EventLocationType;
use App\EventStatus;
use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        abort_unless($event->isPubliclyAvailable(), 404);
        $this->event = $event->load(['eventType:id,name,icon,color', 'featuredImage:id,disk,path,media_type,visibility,status']);
    }

    public function title(): string
    {
        return $this->event->title;
    }

    #[Computed]
    public function relatedEvents()
    {
        return Event::query()
            ->published()
            ->active()
            ->upcoming()
            ->whereKeyNot($this->event->id)
            ->when($this->event->event_type_id !== null, fn ($query) => $query->where('event_type_id', $this->event->event_type_id))
            ->with(['eventType:id,name,icon,color', 'featuredImage:id,disk,path,media_type,visibility,status'])
            ->orderBy('starts_at')
            ->limit(3)
            ->get();
    }
};
?>

<div>
    <section class="bg-church-maroon-950 text-white">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <a href="{{ route('public.events.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-church-gold-400" wire:navigate><flux:icon.arrow-left class="size-4" />{{ __('All events') }}</a>
            <div class="mt-8 grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <div class="flex flex-wrap gap-2"><flux:badge color="amber">{{ $event->eventType?->name ?? __('Event') }}</flux:badge>@if ($event->is_featured)<flux:badge color="yellow">{{ __('Featured') }}</flux:badge>@endif @if ($event->is_livestreamed)<flux:badge color="blue">{{ __('Livestream') }}</flux:badge>@endif</div>
                    <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-5xl">{{ $event->title }}</h1>
                    @if ($event->short_description)<p class="mt-5 text-lg leading-8 text-white/75">{{ $event->short_description }}</p>@endif
                </div>
                <div class="overflow-hidden rounded-2xl bg-church-maroon-900 shadow-2xl">
                @if ($event->imageUrl())<img src="{{ $event->imageUrl() }}" alt="{{ $event->title }}" class="aspect-[16/9] w-full object-cover">@else<div class="flex aspect-[16/9] items-center justify-center text-church-gold-400"><flux:icon :name="$event->effectiveIcon()" class="size-20" /></div>@endif
                </div>
            </div>
        </div>
    </section>

    <main class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)] lg:px-8">
        <article class="space-y-8">
            @if ($event->status === EventStatus::Cancelled)
                <flux:callout variant="danger" icon="x-circle" heading="{{ __('This event has been cancelled') }}">{{ __('Please contact the church office if you need more information.') }}</flux:callout>
            @endif
            @if ($event->description)
                <section><h2 class="text-2xl font-black">{{ __('About this event') }}</h2><div class="mt-4 whitespace-pre-line text-base leading-8 text-slate-700 dark:text-zinc-300">{{ $event->description }}</div></section>
            @endif
            @if ($event->location_url && $event->location_type !== EventLocationType::Online)
                <section><h2 class="text-2xl font-black">{{ __('Location') }}</h2><p class="mt-3 text-slate-600 dark:text-zinc-300">{{ collect([$event->venue_name, $event->address, $event->city, $event->region, $event->country])->filter()->join(', ') }}</p><a href="{{ $event->location_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 font-bold text-church-maroon-700 dark:text-church-gold-400">{{ __('Open map') }}<flux:icon.arrow-top-right-on-square class="size-4" /></a></section>
            @endif
            @if ($event->meeting_url && in_array($event->location_type, [EventLocationType::Online, EventLocationType::Hybrid], true))
                <section class="rounded-2xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950/30"><h2 class="text-xl font-black">{{ $event->is_livestreamed ? __('Watch the livestream') : __('Join online') }}</h2><p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ __('Online access is provided by the event organizer. Please do not share restricted meeting links publicly.') }}</p><a href="{{ $event->meeting_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 font-bold text-white hover:bg-blue-600">{{ __('Open online event') }}<flux:icon.video-camera class="size-4" /></a></section>
            @endif
        </article>

        <aside class="space-y-5">
            <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-black">{{ __('Event details') }}</h2>
                <dl class="mt-5 space-y-5 text-sm">
                    <div class="flex gap-3"><flux:icon.calendar-days class="mt-0.5 size-5 shrink-0 text-church-maroon-700 dark:text-church-gold-400" /><div><dt class="font-bold">{{ __('Schedule') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->scheduleLabel() }}</dd>@if ($next = $event->nextOccurrence())<dd class="mt-2 font-semibold text-church-maroon-800 dark:text-church-gold-400">{{ __('Next: :date', ['date' => $next->setTimezone($event->timezone)->format('l, j F Y')]) }}</dd>@endif</div></div>
                    <div class="flex gap-3"><flux:icon.map-pin class="mt-0.5 size-5 shrink-0 text-church-maroon-700 dark:text-church-gold-400" /><div><dt class="font-bold">{{ __('Venue') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->locationLabel() }}</dd></div></div>
                    @if ($event->isRecurring())<div class="flex gap-3"><flux:icon.arrow-path class="mt-0.5 size-5 shrink-0 text-church-maroon-700 dark:text-church-gold-400" /><div><dt class="font-bold">{{ __('Recurring programme') }}</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">{{ $event->schedule_type->label() }}</dd></div></div>@endif
                </dl>
                @if ($event->registration_required && $event->registration_url && $event->status !== EventStatus::Cancelled)
                    <a href="{{ $event->registration_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-6 flex w-full items-center justify-center rounded-xl bg-church-maroon-900 px-5 py-3 font-bold text-white hover:bg-church-maroon-800">{{ __('Register for this event') }}</a>
                    @if ($event->registration_deadline)<p class="mt-2 text-center text-xs text-slate-500">{{ __('Registration closes :date', ['date' => $event->registration_deadline->setTimezone($event->timezone)->format('M j, Y g:i A')]) }}</p>@endif
                @endif
                @if ($event->livestream_url && $event->status !== EventStatus::Cancelled)<a href="{{ $event->livestream_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-3 flex w-full items-center justify-center rounded-xl border border-church-maroon-900 px-5 py-3 font-bold text-church-maroon-900 dark:border-church-gold-400 dark:text-church-gold-400">{{ __('Watch livestream') }}</a>@endif
            </div>
            @if ($event->contact_name || $event->contact_phone || $event->contact_email)
                <div class="rounded-2xl border border-stone-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="font-black">{{ __('Contact') }}</h2><div class="mt-3 space-y-1 text-sm text-slate-600 dark:text-zinc-300">@if ($event->contact_name)<p>{{ $event->contact_name }}</p>@endif @if ($event->contact_phone)<p><a href="tel:{{ $event->contact_phone }}">{{ $event->contact_phone }}</a></p>@endif @if ($event->contact_email)<p><a href="mailto:{{ $event->contact_email }}">{{ $event->contact_email }}</a></p>@endif</div></div>
            @endif
        </aside>
    </main>

    @if ($this->relatedEvents->isNotEmpty())
        <section class="border-t border-stone-200 bg-white py-12 dark:border-zinc-800 dark:bg-zinc-900/40"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><h2 class="text-2xl font-black">{{ __('Related upcoming events') }}</h2><div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">@foreach ($this->relatedEvents as $related)<x-events.card :event="$related" wire:key="related-event-{{ $related->id }}" />@endforeach</div></div></section>
    @endif
</div>
