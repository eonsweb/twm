<?php

namespace App\Actions\ServiceSchedules;

use App\Activity\ActivityLogger;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class DeleteServiceSchedule
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, ServiceSchedule $serviceSchedule): void
    {
        Gate::forUser($actor)->authorize('delete', $serviceSchedule);
        $properties = ['name' => $serviceSchedule->name, 'day_of_week' => $serviceSchedule->day_of_week];
        $serviceSchedule->delete();
        Cache::forget('system-settings.public.service-schedules');

        $this->activityLogger->log(
            logName: 'service-schedules',
            event: 'service-schedule.deleted',
            description: "Deleted service schedule \"{$serviceSchedule->name}\".",
            subject: $serviceSchedule,
            causer: $actor,
            properties: $properties,
        );
    }
}
