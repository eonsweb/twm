<?php

namespace App\Activity;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogger
{
    public function __construct(
        private readonly ActivitySanitizer $sanitizer,
        private readonly RequestMetadata $requestMetadata,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        Model|Authenticatable|null $causer = null,
        array $properties = [],
        array $oldValues = [],
        array $newValues = [],
        string $status = 'success',
        ?string $failureReason = null,
        ?string $batchId = null,
        ?string $origin = null,
    ): ActivityLog {
        $metadata = $this->requestMetadata->capture($origin);
        $causer ??= auth()->user();
        $subjectLabel = $subject?->getAttribute('full_name')
            ?? $subject?->getAttribute('name')
            ?? $subject?->getAttribute('title')
            ?? ($subject === null ? null : class_basename($subject).' #'.$subject->getKey());

        $activity = new ActivityLog;
        $activity->forceFill([
            'log_name' => Str::limit(Str::lower($logName), 64, ''),
            'event' => Str::limit(Str::lower($event), 96, ''),
            'description' => $this->sanitizer->sanitizeText($description, 1000),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer instanceof Model ? $causer->getMorphClass() : null,
            'causer_id' => $causer instanceof Authenticatable
                ? $causer->getAuthIdentifier()
                : ($causer instanceof Model ? $causer->getKey() : null),
            'batch_id' => $batchId,
            'properties' => $this->sanitizer->sanitize([
                ...($subjectLabel === null ? [] : ['subject_label' => $subjectLabel]),
                ...$properties,
            ]) ?: null,
            'old_values' => $this->sanitizer->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitizer->sanitize($newValues) ?: null,
            'status' => in_array($status, ['success', 'failure', 'warning'], true) ? $status : 'success',
            'failure_reason' => $this->sanitizer->sanitizeText($failureReason, 500),
            'created_at' => now(),
            ...$metadata,
        ])->saveQuietly();

        return $activity;
    }
}
