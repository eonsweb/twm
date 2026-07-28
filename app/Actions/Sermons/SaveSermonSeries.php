<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\SermonSeries;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaveSermonSeries
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?UploadedFile $cover = null, ?SermonSeries $series = null): SermonSeries
    {
        Gate::forUser($actor)->authorize($series === null ? 'create' : 'update', $series ?? SermonSeries::class);
        $creating = $series === null;
        $series ??= new SermonSeries;
        $oldValues = $creating ? [] : $series->only(['title', 'slug', 'description', 'starts_at', 'ends_at', 'status', 'is_featured']);
        $oldCover = $series->cover_image_path;

        if ($cover !== null) {
            $path = $cover->store('sermons/series', 'public');

            if ($path === false) {
                throw new RuntimeException('The series cover could not be stored.');
            }

            $data['cover_image_path'] = $path;
        }

        $data['created_by'] ??= $creating ? $actor->id : $series->created_by;
        $data['updated_by'] = $actor->id;
        $series->fill($data)->save();

        if ($cover !== null && $oldCover !== null && $oldCover !== $series->cover_image_path) {
            Storage::disk('public')->delete($oldCover);
        }

        $newValues = $series->only(['title', 'slug', 'description', 'starts_at', 'ends_at', 'status', 'is_featured']);
        $this->activityLogger->log(
            logName: 'sermons',
            event: $creating ? 'sermon_series.created' : 'sermon_series.updated',
            description: ($creating ? 'Created' : 'Updated')." sermon series “{$series->title}”.",
            subject: $series,
            causer: $actor,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return $series->refresh();
    }
}
