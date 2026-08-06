<?php

namespace App\Policies;

use App\Models\DonationCampaign;
use App\Models\User;
use App\PermissionName;

class DonationCampaignPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can(PermissionName::DonationCampaignsManage->value);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, DonationCampaign $c): bool
    {
        return $this->viewAny($u);
    }

    public function delete(User $u, DonationCampaign $c): bool
    {
        return $this->viewAny($u) && ! $c->donations()->exists();
    }
}
