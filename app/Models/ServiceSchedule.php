<?php

namespace App\Models;

use Database\Factories\ServiceScheduleFactory;
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
