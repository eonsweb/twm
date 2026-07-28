<?php

namespace App\Policies;

use App\Models\Sermon;
use App\Models\User;
use App\PermissionName;

class SermonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SermonsView);
    }

    public function view(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SermonsCreate);
    }

    public function update(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsUpdate);
    }

    public function delete(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsDelete);
    }

    public function restore(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsRestore);
    }

    public function forceDelete(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsForceDelete);
    }

    public function publish(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsPublish);
    }

    public function unpublish(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsUnpublish);
    }

    public function schedule(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsSchedule);
    }

    public function archive(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsArchive);
    }

    public function feature(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsFeature);
    }

    public function duplicate(User $user, Sermon $sermon): bool
    {
        return $user->can(PermissionName::SermonsCreate);
    }
}
