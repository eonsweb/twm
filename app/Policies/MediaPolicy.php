<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\PermissionName;

class MediaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::MediaView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaView)
            && ($media->visibility->value === 'public' || $user->can(PermissionName::MediaManagePrivate));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::MediaCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaDelete);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaRestore);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaForceDelete);
    }

    public function download(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaDownload)
            && ($media->visibility->value === 'public' || $user->can(PermissionName::MediaManagePrivate));
    }

    public function managePrivate(User $user, Media $media): bool
    {
        return $user->can(PermissionName::MediaManagePrivate);
    }
}
