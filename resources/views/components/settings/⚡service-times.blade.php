<?php

use App\Models\ServiceSchedule;
use App\Activity\ActivityLogger;
use App\PermissionName;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    /** @var list<array<string, mixed>> */
    public array $schedules = [];

    public function mount(): void
    {
        Gate::authorize(PermissionName::SettingsServiceTimesUpdate->value);
        $this->loadSchedules();
    }

    public function addSchedule(): void
    {
        Gate::authorize(PermissionName::SettingsServiceTimesUpdate->value);

        $this->schedules[] = [
            'id' => null,
            'name' => '',
            'day_of_week' => 'Sunday',
            'start_time' => '09:00',
            'end_time' => '',
            'location' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    public function removeSchedule(int $index): void
    {
        Gate::authorize(PermissionName::SettingsServiceTimesUpdate->value);

        unset($this->schedules[$index]);
        $this->schedules = array_values($this->schedules);
    }

    public function save(ActivityLogger $activityLogger): void
    {
        Gate::authorize(PermissionName::SettingsServiceTimesUpdate->value);

        $oldSchedules = ServiceSchedule::query()
            ->orderBy('display_order')
            ->get(['name', 'day_of_week', 'start_time', 'end_time', 'location', 'description', 'display_order', 'is_active'])
            ->toArray();
        $validated = $this->validate([
            'schedules' => ['array', 'max:50'],
            'schedules.*.id' => ['nullable', 'integer', 'distinct', 'exists:service_schedules,id'],
            'schedules.*.name' => ['required', 'string', 'max:255'],
            'schedules.*.day_of_week' => ['required', Rule::in($this->days())],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.location' => ['nullable', 'string', 'max:255'],
            'schedules.*.description' => ['nullable', 'string', 'max:2000'],
            'schedules.*.is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $retainedIds = [];

            foreach ($validated['schedules'] as $displayOrder => $scheduleData) {
                $id = $scheduleData['id'];
                unset($scheduleData['id']);
                $scheduleData['end_time'] = $scheduleData['end_time'] ?: null;
                $scheduleData['display_order'] = $displayOrder;

                $schedule = $id === null
                    ? ServiceSchedule::query()->create($scheduleData)
                    : tap(ServiceSchedule::query()->findOrFail($id))->update($scheduleData);

                $retainedIds[] = $schedule->getKey();
            }

            ServiceSchedule::query()
                ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
                ->delete();
        });

        Cache::forget('system-settings.public.service-schedules');
        $newSchedules = ServiceSchedule::query()
            ->orderBy('display_order')
            ->get(['name', 'day_of_week', 'start_time', 'end_time', 'location', 'description', 'display_order', 'is_active'])
            ->toArray();

        if ($oldSchedules !== $newSchedules) {
            $activityLogger->log(
                logName: 'system-settings',
                event: 'settings.service_times.updated',
                description: 'Updated Service Times system settings.',
                oldValues: ['schedules' => $oldSchedules],
                newValues: ['schedules' => $newSchedules],
            );
        }

        $this->loadSchedules();
        Flux::toast(__('Service times saved.'));
    }

    /**
     * @return list<string>
     */
    public function days(): array
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    private function loadSchedules(): void
    {
        $this->schedules = ServiceSchedule::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceSchedule $schedule): array => [
                'id' => $schedule->getKey(),
                'name' => $schedule->name,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => $schedule->end_time ? substr($schedule->end_time, 0, 5) : '',
                'location' => $schedule->location ?? '',
                'description' => $schedule->description ?? '',
                'is_active' => $schedule->is_active,
            ])
            ->all();
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-zinc-800">
            <div>
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ __('Service times') }}</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-zinc-400">
                    {{ __('Manage recurring services in the order they should appear publicly.') }}
                </p>
            </div>
            <flux:button type="button" wire:click="addSchedule" variant="outline" icon="plus">
                {{ __('Add service') }}
            </flux:button>
        </div>

        <div class="space-y-4 p-5 sm:p-6">
            @forelse ($schedules as $index => $schedule)
                <fieldset wire:key="service-schedule-{{ $schedule['id'] ?? 'new-'.$index }}" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                    <div class="mb-4 flex items-center justify-between">
                        <legend class="font-semibold text-slate-900 dark:text-white">
                            {{ $schedule['name'] ?: __('New service') }}
                        </legend>
                        <flux:button
                            type="button"
                            wire:click="removeSchedule({{ $index }})"
                            variant="ghost"
                            icon="trash"
                            size="sm"
                            :aria-label="__('Remove service')"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="schedules.{{ $index }}.name" :label="__('Name')" />
                        </div>
                        <flux:select wire:model="schedules.{{ $index }}.day_of_week" :label="__('Day')">
                            @foreach ($this->days() as $day)
                                <flux:select.option :value="$day">{{ __($day) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="flex items-end pb-2">
                            <flux:switch wire:model="schedules.{{ $index }}.is_active" :label="__('Visible publicly')" />
                        </div>
                        <flux:input wire:model="schedules.{{ $index }}.start_time" type="time" :label="__('Starts')" />
                        <flux:input wire:model="schedules.{{ $index }}.end_time" type="time" :label="__('Ends')" />
                        <div class="sm:col-span-2">
                            <flux:input wire:model="schedules.{{ $index }}.location" :label="__('Location')" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <flux:textarea wire:model="schedules.{{ $index }}.description" :label="__('Description')" rows="2" />
                        </div>
                    </div>
                </fieldset>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-zinc-700">
                    <flux:icon.clock class="mx-auto size-10 text-slate-400" />
                    <p class="mt-3 font-medium text-slate-900 dark:text-white">{{ __('No service times configured') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Add the first recurring service to get started.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="sticky bottom-4 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
        <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ __('Save service times') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </flux:button>
    </div>
</form>
