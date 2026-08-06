<?php

namespace App;

enum PageVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Authenticated = 'authenticated';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
