<?php

use App\Actions\ServiceSchedules\SaveServiceSchedule;
use App\Livewire\Forms\ServiceScheduleForm;
use App\Models\ServiceSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Service Schedule')] class extends Component
{
    public ServiceScheduleForm $form;

    public function mount(): void
    {
        Gate::authorize('create', ServiceSchedule::class);
    }

    public function save(SaveServiceSchedule $saveServiceSchedule): void
    {
        $this->form->normalize();
        $this->form->validate();
        $schedule = $saveServiceSchedule->handle(Auth::user(), $this->form->scheduleData());
        session()->flash('success', __('Service schedule created successfully.'));
        $this->redirectRoute('service-schedules.edit', $schedule, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('service-schedules.index')" wire:navigate>{{ __('Service Schedules') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Create Service Schedule')" :description="__('Add a recurring weekly church program shown on the website.')" :eyebrow="__('Service Schedules')" />

    <form wire:submit="save" class="space-y-6">
        <x-admin.service-schedule-form :form="$form" />
        <div class="flex justify-end gap-3">
            <flux:button :href="route('service-schedules.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
