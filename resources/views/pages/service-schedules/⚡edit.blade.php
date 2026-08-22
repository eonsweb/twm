<?php

use App\Actions\ServiceSchedules\SaveServiceSchedule;
use App\Livewire\Forms\ServiceScheduleForm;
use App\Models\ServiceSchedule;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Service Schedule')] class extends Component
{
    public ServiceScheduleForm $form;

    public ServiceSchedule $serviceSchedule;

    public function mount(ServiceSchedule $serviceSchedule): void
    {
        Gate::authorize('update', $serviceSchedule);
        $this->serviceSchedule = $serviceSchedule;
        $this->form->setServiceSchedule($serviceSchedule);
    }

    public function save(SaveServiceSchedule $saveServiceSchedule): void
    {
        $this->form->normalize();
        $this->form->validate();
        $this->serviceSchedule = $saveServiceSchedule->handle(Auth::user(), $this->form->scheduleData(), $this->serviceSchedule);
        $this->form->setServiceSchedule($this->serviceSchedule);
        Flux::toast(variant: 'success', text: __('Service schedule updated successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('service-schedules.index')" wire:navigate>{{ __('Service Schedules') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
    @endif

    <x-admin.page-header :title="__('Edit Service Schedule')" :description="$serviceSchedule->name" :eyebrow="__('Service Schedules')" />

    <form wire:submit="save" class="space-y-6">
        <x-admin.service-schedule-form :form="$form" />
        <div class="flex justify-end gap-3">
            <flux:button :href="route('service-schedules.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
