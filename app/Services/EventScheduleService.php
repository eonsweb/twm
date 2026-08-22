<?php

namespace App\Services;

use App\EventScheduleType;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EventScheduleService
{
    /** @var array<string, int> */
    private const WEEKDAYS = [
        'sunday' => Carbon::SUNDAY,
        'monday' => Carbon::MONDAY,
        'tuesday' => Carbon::TUESDAY,
        'wednesday' => Carbon::WEDNESDAY,
        'thursday' => Carbon::THURSDAY,
        'friday' => Carbon::FRIDAY,
        'saturday' => Carbon::SATURDAY,
    ];

    public function nextOccurrence(Event $event, ?Carbon $from = null): ?Carbon
    {
        $from = Carbon::instance($from ?? now())->setTimezone($event->timezone);
        $start = Carbon::instance($event->starts_at)->setTimezone($event->timezone);

        if (! $event->isRecurring()) {
            $end = Carbon::instance($event->ends_at ?? $event->starts_at)->setTimezone($event->timezone);

            return $end->gte($from) ? $start : null;
        }

        if ($event->recurrence_end_date?->endOfDay()->lt($from)) {
            return null;
        }

        $occurrence = match ($event->schedule_type) {
            EventScheduleType::Weekly => $this->nextWeeklyOccurrence($event, $from, $start),
            EventScheduleType::Monthly => $this->nextMonthlyOccurrence($event, $from, $start),
            EventScheduleType::Yearly => $this->nextYearlyOccurrence($event, $from, $start),
            EventScheduleType::Custom => $start->gte($from) ? $start : null,
            EventScheduleType::OneTime => null,
        };

        return $occurrence !== null && $event->recurrence_end_date?->endOfDay()->lt($occurrence)
            ? null
            : $occurrence;
    }

    public function label(Event $event): string
    {
        $time = $event->is_all_day ? __('All day') : $event->starts_at->setTimezone($event->timezone)->format('g:i A');

        return match ($event->schedule_type) {
            EventScheduleType::Weekly => $this->weeklyLabel($event, $time),
            EventScheduleType::Monthly => $this->monthlyLabel($event, $time),
            EventScheduleType::Yearly => $this->yearlyLabel($event, $time),
            EventScheduleType::Custom => filled($event->recurrence_rule)
                ? (string) $event->recurrence_rule
                : __('Custom schedule'),
            EventScheduleType::OneTime => $event->formattedDateRange(),
        };
    }

    private function nextWeeklyOccurrence(Event $event, Carbon $from, Carbon $start): ?Carbon
    {
        $days = $this->recurrenceDays($event, Str::lower($start->format('l')));
        $interval = max(1, $event->recurrence_interval);
        $anchorWeek = $start->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        for ($offset = 0; $offset <= 371; $offset++) {
            $date = $from->copy()->startOfDay()->addDays($offset);
            $day = Str::lower($date->format('l'));
            $weeks = $anchorWeek->diffInWeeks($date->copy()->startOfWeek(Carbon::MONDAY), false);

            if (! in_array($day, $days, true) || $weeks < 0 || $weeks % $interval !== 0) {
                continue;
            }

            $candidate = $date->setTimeFrom($start);

            if ($candidate->gte($from) && $candidate->gte($start)) {
                return $candidate;
            }
        }

        return null;
    }

    private function nextMonthlyOccurrence(Event $event, Carbon $from, Carbon $start): ?Carbon
    {
        $interval = max(1, $event->recurrence_interval);
        $anchorMonth = $start->copy()->startOfMonth();

        for ($offset = 0; $offset <= 60; $offset++) {
            $month = $from->copy()->startOfMonth()->addMonthsNoOverflow($offset);
            $months = $anchorMonth->diffInMonths($month, false);

            if ($months < 0 || $months % $interval !== 0) {
                continue;
            }

            foreach ($this->monthlyCandidates($event, $month, $start) as $candidate) {
                if ($candidate->gte($from) && $candidate->gte($start)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function nextYearlyOccurrence(Event $event, Carbon $from, Carbon $start): ?Carbon
    {
        $month = $event->recurrence_month ?? $start->month;
        $day = $event->recurrence_day_of_month ?? $start->day;
        $interval = max(1, $event->recurrence_interval);

        for ($year = max($from->year, $start->year); $year <= $from->year + 10; $year++) {
            if (($year - $start->year) % $interval !== 0) {
                continue;
            }

            $candidate = Carbon::create($year, $month, min($day, Carbon::create($year, $month)->daysInMonth), 0, 0, 0, $event->timezone)
                ->setTimeFrom($start);

            if ($candidate->gte($from) && $candidate->gte($start)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<Carbon> */
    private function monthlyCandidates(Event $event, Carbon $month, Carbon $start): array
    {
        if ($event->recurrence_week_of_month === null) {
            $day = min($event->recurrence_day_of_month ?? $start->day, $month->daysInMonth);

            return [$month->copy()->day($day)->setTimeFrom($start)];
        }

        $days = $this->recurrenceDays($event, Str::lower($start->format('l')));
        $candidates = [];

        foreach ($days as $day) {
            $targetDay = self::WEEKDAYS[$day];

            if ($event->recurrence_week_of_month === 'last') {
                $candidate = $month->copy()->endOfMonth()->startOfDay();
                $candidate->subDays(($candidate->dayOfWeek - $targetDay + 7) % 7);
            } else {
                $week = max(1, min(4, (int) $event->recurrence_week_of_month));
                $candidate = $month->copy()->startOfMonth();
                $candidate->addDays(($targetDay - $candidate->dayOfWeek + 7) % 7)->addWeeks($week - 1);
            }

            if ($candidate->month === $month->month) {
                $candidates[] = $candidate->setTimeFrom($start);
            }
        }

        usort($candidates, fn (Carbon $first, Carbon $second): int => $first->getTimestamp() <=> $second->getTimestamp());

        return $candidates;
    }

    private function weeklyLabel(Event $event, string $time): string
    {
        $days = collect($this->recurrenceDays($event, Str::lower($event->starts_at->setTimezone($event->timezone)->format('l'))))
            ->map(fn (string $day): string => Str::title($day))
            ->join(', ', ' & ');
        $prefix = $event->recurrence_interval > 1 ? __('Every :count weeks on', ['count' => $event->recurrence_interval]) : __('Every');

        return "{$prefix} {$days} · {$time}";
    }

    private function monthlyLabel(Event $event, string $time): string
    {
        if ($event->recurrence_week_of_month !== null) {
            $week = $event->recurrence_week_of_month === 'last'
                ? __('Last week')
                : Str::headline($event->recurrence_week_of_month).' '.__('week');

            return "{$week} ".__('of every month')." · {$time}";
        }

        $day = $event->recurrence_day_of_month ?? $event->starts_at->setTimezone($event->timezone)->day;

        return __('Day :day of every month', ['day' => $day])." · {$time}";
    }

    private function yearlyLabel(Event $event, string $time): string
    {
        $month = Carbon::create()->month($event->recurrence_month ?? $event->starts_at->month)->format('F');
        $day = $event->recurrence_day_of_month;

        return ($day === null ? __('Every :month', ['month' => $month]) : __('Every :month :day', ['month' => $month, 'day' => $day]))." · {$time}";
    }

    /** @return list<string> */
    private function recurrenceDays(Event $event, string $fallback): array
    {
        $days = array_values(array_filter(
            $event->recurrence_days ?? [],
            fn (string $day): bool => array_key_exists($day, self::WEEKDAYS),
        ));

        return $days === [] ? [$fallback] : $days;
    }
}
