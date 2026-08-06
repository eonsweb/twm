<?php

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;
use App\PermissionName;

class DonorPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can(PermissionName::DonorsView->value);
    }

    public function view(User $u, Donor $d): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $u->can(PermissionName::DonorsCreate->value);
    }

    public function update(User $u, Donor $d): bool
    {
        return $u->can(PermissionName::DonorsUpdate->value);
    }

    public function delete(User $u, Donor $d): bool
    {
        return $u->can(PermissionName::DonorsDelete->value) && ! $d->donations()->exists();
    }
}
