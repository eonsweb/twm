<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\PermissionName;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PostsView);
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PostsCreate);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsUpdate);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsDelete);
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsRestore);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsForceDelete);
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsPublish);
    }

    public function archive(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsArchive);
    }

    public function preview(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsPreview);
    }

    public function duplicate(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsCreate);
    }

    public function manageAuthor(User $user, Post $post): bool
    {
        return $user->can(PermissionName::PostsManageAuthors);
    }
}
