<?php

namespace App;

enum DonationCategoryType: string
{
    case Tithe = 'tithe';
    case Offering = 'offering';
    case Thanksgiving = 'thanksgiving';
    case BuildingFund = 'building_fund';
    case Missions = 'missions';
    case Welfare = 'welfare';
    case Anniversary = 'anniversary';
    case SpecialProject = 'special_project';
    case FirstFruit = 'first_fruit';
    case General = 'general_donation';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
