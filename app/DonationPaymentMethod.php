<?php

namespace App;

enum DonationPaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';
    case Online = 'online_payment';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
