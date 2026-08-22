<?php

namespace App\Livewire\Forms;

use App\Models\ServiceSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceScheduleForm extends Form
{
    /** @var list<string> */
    public const DAYS = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    public string $name = '';

    public string $dayOfWeek = 'Sunday';

    public string $startTime = '';

    public string $endTime = '';

    public string $location = '';

    public string $description = '';

    public int|string $displayOrder = 0;

    public bool $isActive = true;

    public function setServiceSchedule(ServiceSchedule $serviceSchedule): void
    {
        $this->name = $serviceSchedule->name;
        $this->dayOfWeek = $serviceSchedule->day_of_week;
        $this->startTime = Carbon::parse((string) $serviceSchedule->start_time)->format('H:i');
        $this->endTime = $serviceSchedule->end_time === null
            ? ''
            : Carbon::parse((string) $serviceSchedule->end_time)->format('H:i');
        $this->location = $serviceSchedule->location ?? '';
        $this->description = $serviceSchedule->description ?? '';
        $this->displayOrder = $serviceSchedule->display_order;
        $this->isActive = $serviceSchedule->is_active;
    }

    public function normalize(): void
    {
        $this->name = str($this->name)->squish()->toString();
        $this->location = str($this->location)->squish()->toString();
        $this->description = trim($this->description);
        $this->displayOrder = max(0, (int) $this->displayOrder);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dayOfWeek' => ['required', Rule::in(self::DAYS)],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i', 'after:startTime'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'displayOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function scheduleData(): array
    {
        return [
            'name' => $this->name,
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime !== '' ? $this->endTime : null,
            'location' => $this->location !== '' ? $this->location : null,
            'description' => $this->description !== '' ? $this->description : null,
            'display_order' => $this->displayOrder,
            'is_active' => $this->isActive,
        ];
    }
}
