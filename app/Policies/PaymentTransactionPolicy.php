<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\PermissionName;

class PaymentTransactionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can(PermissionName::PaymentTransactionsView->value);
    }

    public function view(User $u, PaymentTransaction $t): bool
    {
        return $this->viewAny($u);
    }
}
