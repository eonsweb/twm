<?php

namespace App\Policies;

use App\Models\SermonSeries;
use App\Models\User;
use App\PermissionName;

class SermonSeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SermonsView);
    }

    public function view(User $user, SermonSeries $series): bool
    {
        return $user->can(PermissionName::SermonsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SermonSeriesManage);
    }

    public function update(User $user, SermonSeries $series): bool
    {
        return $user->can(PermissionName::SermonSeriesManage);
    }

    public function delete(User $user, SermonSeries $series): bool
    {
        return $user->can(PermissionName::SermonSeriesManage);
    }
}
