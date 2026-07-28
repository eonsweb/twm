<?php

namespace App\Actions\Users;

use App\Activity\ActivityLogger;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateUser
{
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
        User $user,
        array $data,
        array $roleIds,
        ?int $personId = null,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
    ): User {
        $oldValues = $user->only(['name', 'username', 'email', 'photo']);
        $oldPhotoPath = $user->photo;
        $newPhotoPath = $this->storePhoto($photo);

        if ($newPhotoPath !== null) {
            $data['photo'] = $newPhotoPath;
        } elseif ($removePhoto) {
            $data['photo'] = null;
        }

        if ($user->email !== $data['email']) {
            $data['email_verified_at'] = null;
        }

        try {
            $savedUser = DB::transaction(function () use ($actor, $user, $data, $roleIds, $personId): User {
                $user->update($data);
                $this->syncUserRoles->handle($actor, $user, $roleIds);

                Person::query()->where('user_id', $user->id)->update(['user_id' => null]);

                if ($personId !== null) {
                    Person::query()
                        ->whereKey($personId)
                        ->where(function ($query) use ($user): void {
                            $query->whereNull('user_id')->orWhere('user_id', $user->id);
                        })
                        ->lockForUpdate()
                        ->firstOrFail()
                        ->update(['user_id' => $user->id]);
                }

                return $user->refresh()->load(['roles:id,name', 'person:id,user_id']);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (($newPhotoPath !== null || $removePhoto) && $oldPhotoPath !== null) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $newValues = $savedUser->only(['name', 'username', 'email', 'photo']);
        $changedKeys = collect($newValues)
            ->filter(fn (mixed $value, string $key): bool => $oldValues[$key] !== $value)
            ->keys()
            ->all();

        if ($changedKeys !== []) {
            $this->activityLogger->log(
                logName: 'users',
                event: 'user.updated',
                description: "Updated user {$savedUser->name}.",
                subject: $savedUser,
                causer: $actor,
                oldValues: collect($oldValues)->only($changedKeys)->all(),
                newValues: collect($newValues)->only($changedKeys)->all(),
            );
        }

        return $savedUser;
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
