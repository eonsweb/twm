<?php

namespace App;

enum DonationSource: string
{
    case Admin = 'admin_entry';
    case Website = 'public_website';
    case Service = 'church_service';
    case BankImport = 'bank_import';
    case MobileMoney = 'mobile_money';
    case Campaign = 'fundraising_campaign';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
