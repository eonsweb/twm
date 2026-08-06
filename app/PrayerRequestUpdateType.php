<?php

namespace App;

enum PrayerRequestUpdateType: string
{
    case Note = 'note';
    case StatusChange = 'status_change';
    case Assignment = 'assignment';
    case FollowUp = 'follow_up';
    case ContactAttempt = 'contact_attempt';
    case Testimony = 'testimony';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
