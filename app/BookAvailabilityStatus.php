<?php

namespace App;

enum BookAvailabilityStatus: string
{
    case Available = 'available';
    case OutOfStock = 'out_of_stock';
    case Preorder = 'preorder';
    case ComingSoon = 'coming_soon';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Preorder => 'blue',
            self::ComingSoon => 'amber',
            self::OutOfStock, self::Unavailable => 'zinc',
        };
    }

    public function canPurchase(): bool
    {
        return in_array($this, [self::Available, self::Preorder], true);
    }
}
