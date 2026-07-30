<?php

namespace App;

enum MinistryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Inactive => 'Inactive',
        };
    }
}
