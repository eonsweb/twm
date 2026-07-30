<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use App\PermissionName;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PostTagsManage);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PostTagsManage);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can(PermissionName::PostTagsManage);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can(PermissionName::PostTagsManage);
    }
}
