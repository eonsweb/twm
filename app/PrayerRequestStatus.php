<?php

namespace App;

enum PrayerRequestStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Assigned = 'assigned';
    case Praying = 'praying';
    case AwaitingFollowUp = 'awaiting_follow_up';
    case Answered = 'answered';
    case Closed = 'closed';
    case Archived = 'archived';
    case Spam = 'spam';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue', self::UnderReview, self::Assigned => 'amber', self::Praying => 'purple', self::AwaitingFollowUp => 'indigo', self::Answered => 'green', self::Closed, self::Archived => 'zinc', self::Spam => 'red'
        };
    }
}
