<?php

namespace App\Policies;

use App\Models\DonationCategory;
use App\Models\User;
use App\PermissionName;

class DonationCategoryPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can(PermissionName::DonationCategoriesManage->value);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, DonationCategory $c): bool
    {
        return $this->viewAny($u);
    }

    public function delete(User $u, DonationCategory $c): bool
    {
        return $this->viewAny($u) && ! $c->donations()->exists();
    }
}
