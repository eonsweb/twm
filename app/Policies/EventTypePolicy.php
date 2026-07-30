<?php

namespace App\Policies;

use App\Models\EventType;
use App\Models\User;
use App\PermissionName;

class EventTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::EventTypesView);
    }

    public function view(User $user, EventType $eventType): bool
    {
        return $user->can(PermissionName::EventTypesView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::EventTypesCreate);
    }

    public function update(User $user, EventType $eventType): bool
    {
        return $user->can(PermissionName::EventTypesUpdate);
    }

    public function delete(User $user, EventType $eventType): bool
    {
        return $user->can(PermissionName::EventTypesDelete);
    }
}
