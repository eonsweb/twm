<?php

namespace App;

enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
