<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;
use App\PermissionName;

class DonationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can(PermissionName::DonationsView->value);
    }

    public function view(User $u, Donation $d): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $u->can(PermissionName::DonationsCreate->value);
    }

    public function update(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsUpdate->value);
    }

    public function delete(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsDelete->value) && $d->payment_status->value !== 'completed';
    }

    public function restore(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsRestore->value);
    }

    public function approve(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsApprove->value);
    }

    public function refund(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsRefund->value);
    }

    public function export(User $u): bool
    {
        return $u->can(PermissionName::DonationsExport->value);
    }

    public function printReceipt(User $u, Donation $d): bool
    {
        return $u->can(PermissionName::DonationsPrintReceipt->value) && filled($d->receipt_number);
    }
}
