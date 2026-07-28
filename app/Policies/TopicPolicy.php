<?php

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;
use App\PermissionName;

class TopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SermonsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SermonTopicsManage);
    }

    public function update(User $user, Topic $topic): bool
    {
        return $user->can(PermissionName::SermonTopicsManage);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $user->can(PermissionName::SermonTopicsManage);
    }
}
