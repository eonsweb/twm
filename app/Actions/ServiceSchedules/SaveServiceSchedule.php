<?php

namespace App\Actions\ServiceSchedules;

use App\Activity\ActivityLogger;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class SaveServiceSchedule
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param  array<string, mixed>  $data */
    public function handle(User $actor, array $data, ?ServiceSchedule $serviceSchedule = null): ServiceSchedule
    {
        $creating = $serviceSchedule === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $serviceSchedule ?? ServiceSchedule::class);
        $serviceSchedule ??= new ServiceSchedule;
        $oldValues = $creating ? [] : $serviceSchedule->only(array_keys($data));

        $serviceSchedule->fill($data)->save();
        $serviceSchedule->refresh();
        $this->forgetPublicCache();

        $this->activityLogger->log(
            logName: 'service-schedules',
            event: $creating ? 'service-schedule.created' : 'service-schedule.updated',
            description: ($creating ? 'Created' : 'Updated')." service schedule \"{$serviceSchedule->name}\".",
            subject: $serviceSchedule,
            causer: $actor,
            oldValues: $oldValues,
            newValues: Arr::only($serviceSchedule->getAttributes(), array_keys($data)),
        );

        return $serviceSchedule;
    }

    public function toggleActive(User $actor, ServiceSchedule $serviceSchedule): ServiceSchedule
    {
        Gate::forUser($actor)->authorize('update', $serviceSchedule);
        $oldValue = $serviceSchedule->is_active;
        $serviceSchedule->update(['is_active' => ! $oldValue]);
        $this->forgetPublicCache();

        $this->activityLogger->log(
            logName: 'service-schedules',
            event: $serviceSchedule->is_active ? 'service-schedule.activated' : 'service-schedule.deactivated',
            description: ($serviceSchedule->is_active ? 'Activated' : 'Deactivated')." service schedule \"{$serviceSchedule->name}\".",
            subject: $serviceSchedule,
            causer: $actor,
            oldValues: ['is_active' => $oldValue],
            newValues: ['is_active' => $serviceSchedule->is_active],
        );

        return $serviceSchedule;
    }

    private function forgetPublicCache(): void
    {
        Cache::forget('system-settings.public.service-schedules');
    }
}
