<?php

namespace App\Policies;

use App\Models\PostCategory;
use App\Models\User;
use App\PermissionName;

class PostCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PostCategoriesManage);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PostCategoriesManage);
    }

    public function update(User $user, PostCategory $category): bool
    {
        return $user->can(PermissionName::PostCategoriesManage);
    }

    public function delete(User $user, PostCategory $category): bool
    {
        return $user->can(PermissionName::PostCategoriesManage);
    }
}
