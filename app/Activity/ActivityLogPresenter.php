<?php

namespace App\Activity;

use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ActivityLogPresenter
{
    public function __construct(private readonly ActivitySanitizer $sanitizer) {}

    public function causerLabel(ActivityLog $activity): string
    {
        return match (true) {
            $activity->causer instanceof User => $activity->causer->name,
            $activity->causer instanceof Role => $activity->causer->name,
            $activity->causer instanceof Model => (string) ($activity->causer->getAttribute('name') ?? class_basename($activity->causer)),
            $activity->causer_id !== null => 'No longer available',
            default => Str::headline($activity->origin),
        };
    }

    public function subjectLabel(ActivityLog $activity): string
    {
        return match (true) {
            $activity->subject instanceof User => $activity->subject->name,
            $activity->subject instanceof Person => $activity->subject->full_name,
            $activity->subject instanceof Role => $activity->subject->name,
            $activity->subject instanceof Model => (string) (
                $activity->subject->getAttribute('name')
                ?? $activity->subject->getAttribute('title')
                ?? class_basename($activity->subject).' #'.$activity->subject_id
            ),
            $activity->subject_id !== null => (string) data_get(
                $activity->properties,
                'subject_label',
                'Deleted '.class_basename((string) $activity->subject_type).' #'.$activity->subject_id,
            ),
            default => '—',
        };
    }

    public function moduleLabel(ActivityLog $activity): string
    {
        return Str::headline($activity->log_name);
    }

    public function eventLabel(ActivityLog $activity): string
    {
        return Str::headline(Str::afterLast($activity->event, '.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function properties(ActivityLog $activity): array
    {
        $properties = $activity->properties ?? [];
        unset($properties['subject_label']);

        return $this->sanitizer->sanitize($properties);
    }

    /**
     * @return array<string, mixed>
     */
    public function oldValues(ActivityLog $activity): array
    {
        return $this->sanitizer->sanitize($activity->old_values ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function newValues(ActivityLog $activity): array
    {
        return $this->sanitizer->sanitize($activity->new_values ?? []);
    }
}
