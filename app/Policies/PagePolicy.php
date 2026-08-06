<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\PermissionName;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PagesView);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PagesCreate);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesUpdate);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesDelete) && ! $page->is_homepage;
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesRestore);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesForceDelete) && ! $page->is_homepage;
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesPublish);
    }

    public function preview(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesPreview);
    }

    public function manageSections(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesManageSections);
    }

    public function manageSeo(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesManageSeo);
    }

    public function duplicate(User $user, Page $page): bool
    {
        return $user->can(PermissionName::PagesCreate);
    }
}
