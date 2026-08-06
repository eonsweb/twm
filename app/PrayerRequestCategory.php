<?php

namespace App;

enum PrayerRequestCategory: string
{
    case Healing = 'healing';
    case Family = 'family';
    case Marriage = 'marriage';
    case Career = 'career';
    case Education = 'education';
    case Financial = 'financial';
    case Salvation = 'salvation';
    case Deliverance = 'deliverance';
    case Thanksgiving = 'thanksgiving';
    case Guidance = 'guidance';
    case General = 'general';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
