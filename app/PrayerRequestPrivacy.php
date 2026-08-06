<?php

namespace App;

enum PrayerRequestPrivacy: string
{
    case Private = 'private';
    case PrayerTeam = 'prayer_team';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => __('Private'), self::PrayerTeam => __('Prayer team'), self::Public => __('Public if approved')
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Private => 'red', self::PrayerTeam => 'amber', self::Public => 'green'
        };
    }
}
