<?php

namespace App;

enum DonorType: string
{
    case Member = 'member';
    case Visitor = 'visitor';
    case Organization = 'organization';
    case Anonymous = 'anonymous';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
