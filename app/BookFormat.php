<?php

namespace App;

enum BookFormat: string
{
    case Physical = 'physical';
    case Ebook = 'ebook';
    case Audiobook = 'audiobook';
    case PhysicalAndDigital = 'physical_and_digital';

    public function label(): string
    {
        return match ($this) {
            self::Physical => __('Physical'),
            self::Ebook => __('E-book'),
            self::Audiobook => __('Audiobook'),
            self::PhysicalAndDigital => __('Physical & digital'),
        };
    }

    public function isDigital(): bool
    {
        return $this !== self::Physical;
    }

    public function hasPhysicalEdition(): bool
    {
        return in_array($this, [self::Physical, self::PhysicalAndDigital], true);
    }
}
