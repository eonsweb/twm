<?php

namespace App;

enum PreferredContactMethod: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case NoPreference = 'no_preference';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
