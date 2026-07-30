<?php

namespace App;

enum EventLocationType: string
{
    case Physical = 'physical';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical venue',
            self::Online => 'Online event',
            self::Hybrid => 'Hybrid event',
        };
    }
}
