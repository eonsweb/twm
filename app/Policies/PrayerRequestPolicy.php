<?php

namespace App\Policies;

use App\Models\PrayerRequest;
use App\Models\User;
use App\PermissionName;

class PrayerRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PrayerRequestsView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::PrayerRequestsCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsDelete);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsRestore);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsForceDelete);
    }

    public function assign(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsAssign);
    }

    public function addNote(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsAddNotes);
    }

    public function viewContactDetails(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsViewContactDetails);
    }

    public function viewSensitiveMetadata(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsViewSensitiveMetadata);
    }

    public function markAnswered(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsMarkAnswered);
    }

    public function publish(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsPublish);
    }

    public function archive(User $user, PrayerRequest $prayerRequest): bool
    {
        return $user->can(PermissionName::PrayerRequestsArchive);
    }
}
