<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $log_name
 * @property string $event
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property string|null $batch_id
 * @property string|null $request_id
 * @property array<string, mixed>|null $properties
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_url
 * @property string|null $http_method
 * @property string|null $route_name
 * @property string $origin
 * @property string $status
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property-read Model|null $subject
 * @property-read Model|null $causer
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected $attributes = [
        'origin' => 'system',
        'status' => 'success',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Activity logs are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Activity logs cannot be deleted individually.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
