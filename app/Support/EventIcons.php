<?php

namespace App\Support;

class EventIcons
{
    public const DEFAULT = 'calendar-days';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'calendar-days' => 'Calendar',
            'building-library' => 'Church building',
            'clock' => 'Clock',
            'microphone' => 'Microphone',
            'users' => 'People',
            'user-group' => 'Community',
            'map-pin' => 'Location',
            'megaphone' => 'Outreach',
            'sparkles' => 'Celebration',
            'gift' => 'Gift',
            'fire' => 'Fire',
            'briefcase' => 'Leadership',
            'banknotes' => 'Giving',
            'globe-alt' => 'Global',
            'heart' => 'Heart',
            'sun' => 'Seasonal',
            'star' => 'Featured',
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::options());
    }
}
