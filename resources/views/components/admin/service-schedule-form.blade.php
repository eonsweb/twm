@props(['form'])

<section class="space-y-6 rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div>
        <flux:heading size="lg">{{ __('Weekly program details') }}</flux:heading>
        <flux:text class="mt-1">{{ __('These details appear automatically in the homepage Service Times section when active.') }}</flux:text>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <flux:input wire:model="form.name" :label="__('Name')" maxlength="255" required />
        </div>
        <flux:select wire:model="form.dayOfWeek" :label="__('Day of week')" required>
            @foreach (\App\Livewire\Forms\ServiceScheduleForm::DAYS as $day)
                <flux:select.option :value="$day">{{ __($day) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model="form.displayOrder" type="number" min="0" :label="__('Display order')" required />
        <flux:input wire:model="form.startTime" type="time" :label="__('Start time')" required />
        <flux:input wire:model="form.endTime" type="time" :label="__('End time')" />
        <div class="sm:col-span-2">
            <flux:input wire:model="form.location" :label="__('Location')" maxlength="255" />
        </div>
        <div class="sm:col-span-2">
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="4" />
        </div>
        <div class="sm:col-span-2">
            <flux:switch wire:model="form.isActive" :label="__('Active')" :description="__('Show this recurring program on the public website.')" />
        </div>
    </div>
</section>
