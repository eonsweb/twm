<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\PermissionName;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EventsView);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::EventsCreate);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsUpdate);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsDelete);
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsRestore);
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsForceDelete);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsPublish);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsCancel);
    }

    public function complete(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsUpdate);
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $user->can(PermissionName::EventsCreate);
    }
}
