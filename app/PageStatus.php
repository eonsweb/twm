<?php

namespace App;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
