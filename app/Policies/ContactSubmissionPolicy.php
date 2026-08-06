<?php

namespace App\Policies;

use App\Models\ContactSubmission;
use App\Models\User;
use App\PermissionName;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ContactSubmissionsView);
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ContactSubmissionsCreate);
    }

    public function update(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsUpdate);
    }

    public function assign(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsAssign);
    }

    public function reply(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsReply);
    }

    public function resolve(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsResolve);
    }

    public function addNote(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsManageNotes);
    }

    public function markSpam(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsMarkSpam);
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsDelete);
    }

    public function restore(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsRestore);
    }

    public function forceDelete(User $user, ContactSubmission $submission): bool
    {
        return $user->can(PermissionName::ContactSubmissionsForceDelete);
    }
}
