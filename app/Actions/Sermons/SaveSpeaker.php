<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaveSpeaker
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?UploadedFile $photo = null, ?Person $speaker = null): Person
    {
        Gate::forUser($actor)->authorize('manageSpeakers', Person::class);
        $creating = $speaker === null;
        $speaker ??= new Person;
        $oldPhotoPath = $speaker->photo_path;

        if ($photo !== null) {
            $path = $photo->store('leadership/portraits', 'public');

            if ($path === false) {
                throw new RuntimeException('The speaker photo could not be stored.');
            }

            $data['photo_path'] = $path;
        }

        $speaker->fill($data)->save();

        if ($photo !== null && $oldPhotoPath !== null && $oldPhotoPath !== $speaker->photo_path) {
            Storage::disk('public')->delete($oldPhotoPath);
        }
        $this->activityLogger->log(
            logName: 'sermons',
            event: $creating ? 'speaker.created' : 'speaker.updated',
            description: ($creating ? 'Created' : 'Updated')." speaker {$speaker->full_name}.",
            subject: $speaker,
            causer: $actor,
            newValues: [
                'name' => $speaker->full_name,
                'slug' => $speaker->slug,
                'active' => $speaker->is_active,
                'public' => $speaker->is_public,
            ],
        );

        return $speaker->refresh();
    }
}
