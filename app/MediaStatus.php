<?php

namespace App;

enum MediaStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
