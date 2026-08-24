<?php

use App\Actions\Events\SaveEvent;
use App\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Ministry;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Event')] class extends Component
{
    use WithFileUploads;

    public EventForm $form;
    public Event $event;

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        abort_if($event->trashed(), 404);
        $this->event = $event->load('featuredImage');
        $this->form->setEvent($this->event);
    }

    #[Computed]
    public function eventTypes()
    {
        return EventType::query()
            ->where(function ($query): void {
                $query->where('is_active', true);

                if ($this->event->event_type_id !== null) {
                    $query->orWhere('id', $this->event->event_type_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function ministries()
    {
        return Ministry::query()
            ->where(function ($query): void {
                $query->active();

                if ($this->event->ministry_id !== null) {
                    $query->orWhere('id', $this->event->ministry_id);
                }
            })
            ->ordered()
            ->get(['id', 'name']);
    }

    public function saveDraft(SaveEvent $saveEvent): void
    {
        $this->form->status = EventStatus::Draft->value;
        $this->persist($saveEvent);
    }

    public function save(SaveEvent $saveEvent): void
    {
        $this->persist($saveEvent);
    }

    public function removeEventImage(): void
    {
        $this->form->featuredImageIds = [];
        $this->form->featuredImage = null;
        $this->form->removeFeaturedImage = true;
    }

    private function persist(SaveEvent $saveEvent): void
    {
        if ($this->form->status === EventStatus::Published->value && $this->form->publishedAt === '') {
            $this->form->publishedAt = now()->format('Y-m-d\TH:i');
        }

        $this->form->normalize();
        $this->form->validate();
        $this->form->validateChronology();
        $this->event = $saveEvent->handle(
            Auth::user(),
            $this->form->eventData(),
            $this->form->featuredImage,
            $this->form->removeFeaturedImage,
            $this->event,
        );
        $this->form->setEvent($this->event);
        $this->form->featuredImage = null;
        Flux::toast(variant: 'success', text: __('Event updated successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <x-admin.page-header :title="__('Edit event')" :description="$event->title" :eyebrow="__('Events')">
        <x-slot:actions>
            <flux:button :href="route('events.show', $event)" icon="eye" wire:navigate>{{ __('Preview') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <form wire:submit="save" class="space-y-6" data-event-edit-form>
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ __('Event could not be updated') }}">
                {{ __('Review the highlighted fields and try again.') }}
            </flux:callout>
        @endif
        <x-admin.event-form :form="$form" :event-types="$this->eventTypes" :ministries="$this->ministries" :current-image-url="$event->imageUrl()" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            @if ($errors->any())
                <p class="me-auto self-center text-sm font-semibold text-red-700 dark:text-red-300" role="alert">{{ __('Please correct the highlighted fields.') }}</p>
            @endif
            <flux:button :href="route('events.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Update event') }}</span>
                <span wire:loading wire:target="save">{{ __('Updating...') }}</span>
            </flux:button>
        </div>
    </form>
</div>
