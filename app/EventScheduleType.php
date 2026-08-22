<?php

namespace App;

enum EventScheduleType: string
{
    case OneTime = 'one_time';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time event',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
            self::Custom => 'Custom dates',
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::OneTime;
    }
}
