<?php

namespace App\Policies;

use App\Models\ServiceSchedule;
use App\Models\User;
use App\PermissionName;

class ServiceSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ServiceSchedulesView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $user->can(PermissionName::ServiceSchedulesView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::ServiceSchedulesCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $user->can(PermissionName::ServiceSchedulesUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceSchedule $serviceSchedule): bool
    {
        return $user->can(PermissionName::ServiceSchedulesDelete);
    }
}
