<?php

namespace App;

enum PrayerRequestSource: string
{
    case Website = 'website';
    case Admin = 'admin';
    case Phone = 'phone';
    case InPerson = 'in_person';
    case ChurchService = 'church_service';
    case Event = 'event';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
