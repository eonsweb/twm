<?php

use App\Actions\Events\SaveEvent;
use App\EventStatus;
use App\Livewire\Forms\EventForm;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Ministry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Create Event')] class extends Component
{
    use WithFileUploads;

    public EventForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Event::class);
        $this->form->startDate = now('Africa/Accra')->addDay()->toDateString();
    }

    #[Computed]
    public function eventTypes()
    {
        return EventType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function ministries()
    {
        return Ministry::query()->active()->ordered()->get(['id', 'name']);
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
        $event = $saveEvent->handle(Auth::user(), $this->form->eventData(), $this->form->featuredImage);

        session()->flash('success', __('Event created successfully.'));
        $this->redirectRoute('events.edit', $event, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Create event')" :description="__('Add event details, scheduling, location, registration, and publication settings.')" :eyebrow="__('Events')" />

    <form wire:submit="save" class="space-y-6">
        <x-admin.event-form :form="$form" :event-types="$this->eventTypes" :ministries="$this->ministries" />
        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button :href="route('events.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="button" wire:click="saveDraft" wire:loading.attr="disabled">{{ __('Save as draft') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('Create event') }}</flux:button>
        </div>
    </form>
</div>
