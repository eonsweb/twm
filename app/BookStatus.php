<?php

namespace App;

enum BookStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'green',
            self::Archived => 'amber',
        };
    }
}
