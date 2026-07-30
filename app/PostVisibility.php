<?php

namespace App;

enum PostVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
