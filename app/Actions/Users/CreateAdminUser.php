<?php

namespace App\Actions\Users;

use App\AccountStatus;
use App\Activity\ActivityLogger;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CreateAdminUser
{
    public const TEMPORARY_PASSWORD = 'password';

    public function __construct(
        private readonly SyncUserRoles $syncUserRoles,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{name: string, username: string, email: string}  $data
     * @param  list<int>  $roleIds
     */
    public function handle(
        User $actor,
        array $data,
        array $roleIds,
        AccountStatus $accountStatus = AccountStatus::Active,
        ?string $suspensionReason = null,
        ?int $personId = null,
        ?UploadedFile $photo = null,
    ): User {
        Gate::forUser($actor)->authorize('create', User::class);

        $photoPath = $this->storePhoto($photo);

        try {
            $user = DB::transaction(function () use ($actor, $data, $roleIds, $accountStatus, $suspensionReason, $personId, $photoPath): User {
                $user = User::query()->create([
                    ...$data,
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::TEMPORARY_PASSWORD),
                    'must_change_password' => true,
                    'photo' => $photoPath,
                    'account_status' => $accountStatus->value,
                    'suspended_at' => $accountStatus === AccountStatus::Suspended ? now() : null,
                    'suspension_reason' => $accountStatus === AccountStatus::Suspended
                        ? $suspensionReason
                        : null,
                ]);

                $this->syncUserRoles->handle($actor, $user, $roleIds);

                if ($personId !== null) {
                    Person::query()
                        ->whereKey($personId)
                        ->whereNull('user_id')
                        ->lockForUpdate()
                        ->firstOrFail()
                        ->update(['user_id' => $user->id]);
                }

                return $user->load(['roles:id,name', 'person:id,user_id']);
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        $this->activityLogger->log(
            logName: 'users',
            event: 'user.created',
            description: "Created user {$user->name}.",
            subject: $user,
            causer: $actor,
            newValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'account_status' => $user->account_status,
                'roles' => $user->roles->pluck('name')->all(),
                'photo' => $user->photo === null ? 'Not set' : 'Added',
                'must_change_password' => $user->must_change_password,
            ],
        );

        return $user;
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        $photoPath = $photo->store('users/photos', 'public');

        if ($photoPath === false) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        return $photoPath;
    }
}
