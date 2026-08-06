<?php

namespace App;

enum RecurringFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
