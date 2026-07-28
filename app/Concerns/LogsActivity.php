<?php

namespace App\Concerns;

use App\Activity\ActivityLogger;
use App\Activity\ActivitySanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(fn (self $model) => $model->writeAutomaticActivity('created'));
        static::updated(fn (self $model) => $model->writeAutomaticActivity('updated'));
        static::deleted(fn (self $model) => $model->writeAutomaticActivity('deleted'));

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', fn (self $model) => $model->writeAutomaticActivity('restored'));
        }
    }

    protected function activityLogName(): string
    {
        return Str::plural(Str::snake(class_basename($this)));
    }

    protected function activitySubjectName(): string
    {
        return (string) ($this->getAttribute('name')
            ?? $this->getAttribute('title')
            ?? $this->getKey());
    }

    protected function activityDescription(string $event): string
    {
        return Str::headline($event).' '.Str::lower(class_basename($this)).' '.$this->activitySubjectName().'.';
    }

    /**
     * @return list<string>
     */
    protected function activityExcludedAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at'];
    }

    private function writeAutomaticActivity(string $event): void
    {
        if (auth()->user() === null) {
            return;
        }

        $logger = app(ActivityLogger::class);
        $sanitizer = app(ActivitySanitizer::class);
        $changed = $event === 'updated' ? $this->getChanges() : $this->getAttributes();
        $keys = collect(array_keys($changed))
            ->reject(fn (string $key): bool => in_array($key, $this->activityExcludedAttributes(), true))
            ->reject(fn (string $key): bool => $sanitizer->isSensitiveKey($key))
            ->values()
            ->all();

        if ($keys === []) {
            return;
        }

        $newValues = $event === 'deleted' ? [] : Arr::only($changed, $keys);
        $oldValues = $event === 'updated'
            ? collect($keys)->mapWithKeys(fn (string $key): array => [$key => $this->getOriginal($key)])->all()
            : [];

        $logger->log(
            logName: $this->activityLogName(),
            event: Str::lower(class_basename($this)).'.'.$event,
            description: $this->activityDescription($event),
            subject: $this,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }
}
