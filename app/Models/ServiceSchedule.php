<?php

namespace App\Models;

use Database\Factories\ServiceScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ServiceSchedule extends Model
{
    /** @use HasFactory<ServiceScheduleFactory> */
    use HasFactory;

    public function formattedTime(): string
    {
        return Carbon::parse((string) $this->start_time)->format('g:i A');
    }

    public function formattedTimeRange(): string
    {
        if ($this->end_time === null) {
            return $this->formattedTime();
        }

        return $this->formattedTime().' – '.Carbon::parse((string) $this->end_time)->format('g:i A');
    }

    /** @param  Builder<ServiceSchedule>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<ServiceSchedule>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'day_of_week',
        'start_time',
        'end_time',
        'location',
        'description',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
