<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\Sermon;
use App\Models\User;
use App\Sermons\ExternalMedia;
use App\SermonStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SaveSermon
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ExternalMedia $externalMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $topicIds
     * @param  list<int>  $ministryIds
     */
    public function handle(
        User $actor,
        array $data,
        array $topicIds,
        ?UploadedFile $thumbnail = null,
        bool $removeThumbnail = false,
        ?Sermon $sermon = null,
        array $ministryIds = [],
    ): Sermon {
        $creating = $sermon === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $sermon ?? Sermon::class);
        $sermon ??= new Sermon;
        $data = $this->normalizeExternalMedia($data);
        $this->authorizeWorkflow($actor, $data, $sermon);
        $oldValues = $creating ? [] : $this->auditValues($sermon->loadMissing('topics:id'));
        $oldThumbnailPath = $sermon->thumbnail_path;
        $newThumbnailPath = $this->storeThumbnail($thumbnail);

        if ($newThumbnailPath !== null) {
            $data['thumbnail_path'] = $newThumbnailPath;
        } elseif ($removeThumbnail) {
            $data['thumbnail_path'] = null;
        }

        $data['created_by'] ??= $creating ? $actor->id : $sermon->created_by;
        $data['updated_by'] = $actor->id;

        try {
            $savedSermon = DB::transaction(function () use ($sermon, $data, $topicIds, $ministryIds): Sermon {
                $sermon->fill($data)->save();
                $sermon->topics()->sync($topicIds);
                $sermon->ministries()->sync($ministryIds);

                return $sermon->refresh()->load(['speaker:id,title,first_name,middle_name,last_name', 'series:id,title', 'topics:id,name', 'ministries:id,name']);
            });
        } catch (Throwable $exception) {
            if ($newThumbnailPath !== null) {
                Storage::disk('public')->delete($newThumbnailPath);
            }

            throw $exception;
        }

        if (($newThumbnailPath !== null || $removeThumbnail)
            && $oldThumbnailPath !== null
            && $oldThumbnailPath !== $savedSermon->thumbnail_path
        ) {
            Storage::disk('public')->delete($oldThumbnailPath);
        }

        $newValues = $this->auditValues($savedSermon);
        $this->logChanges($actor, $savedSermon, $oldValues, $newValues, $creating);

        return $savedSermon;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeExternalMedia(array $data): array
    {
        $media = $this->externalMedia->inspect((string) ($data['external_media_url'] ?? ''));

        $data['external_media_url'] = $media['original_url'];
        $data['media_platform'] = $media['platform']->value;
        $data['embed_url'] = $media['embed_url'];
        $data['external_thumbnail_url'] = $media['thumbnail_url'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorizeWorkflow(User $actor, array $data, Sermon $sermon): void
    {
        $newStatus = SermonStatus::from($data['status']);
        $oldStatus = $sermon->exists ? $sermon->status : null;

        if ($newStatus !== $oldStatus) {
            $ability = match ($newStatus) {
                SermonStatus::Published => 'publish',
                SermonStatus::Scheduled => 'schedule',
                SermonStatus::Unpublished => 'unpublish',
                SermonStatus::Archived => 'archive',
                SermonStatus::Draft => null,
            };

            if ($ability !== null) {
                Gate::forUser($actor)->authorize($ability, $sermon);
            }
        }

        if ((bool) $data['is_featured'] !== (bool) $sermon->is_featured) {
            Gate::forUser($actor)->authorize('feature', $sermon);
        }
    }

    private function storeThumbnail(?UploadedFile $thumbnail): ?string
    {
        if ($thumbnail === null) {
            return null;
        }

        $path = $thumbnail->store('sermons/thumbnails', 'public');

        if ($path === false) {
            throw new RuntimeException('The sermon thumbnail could not be stored.');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Sermon $sermon): array
    {
        return [
            'title' => $sermon->title,
            'slug' => $sermon->slug,
            'summary' => str($sermon->summary)->limit(300)->toString(),
            'scripture_reference' => $sermon->scripture_reference,
            'sermon_date' => $sermon->sermon_date->toDateString(),
            'duration_seconds' => $sermon->duration_seconds,
            'media_platform' => $sermon->media_platform->value,
            'media_type' => $sermon->media_type->value,
            'media_url' => $this->externalMedia->safeAuditUrl($sermon->external_media_url),
            'thumbnail' => $sermon->thumbnail_path === null ? 'Not set' : 'Set',
            'speaker' => $sermon->speaker->full_name,
            'series' => $sermon->series?->title,
            'topics' => $sermon->topics->pluck('name')->sort()->values()->all(),
            'service_name' => $sermon->service_name,
            'location' => $sermon->location,
            'status' => $sermon->status->value,
            'published_at' => $sermon->published_at?->toIso8601String(),
            'scheduled_at' => $sermon->scheduled_at?->toIso8601String(),
            'is_featured' => $sermon->is_featured,
            'seo_title' => $sermon->seo_title,
            'seo_description' => $sermon->seo_description,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function logChanges(User $actor, Sermon $sermon, array $oldValues, array $newValues, bool $creating): void
    {
        if ($creating) {
            $this->activityLogger->log(
                logName: 'sermons',
                event: 'sermon.created',
                description: "Created sermon “{$sermon->title}”.",
                subject: $sermon,
                causer: $actor,
                newValues: $newValues,
            );

            if ($sermon->status !== SermonStatus::Draft) {
                $this->logWorkflowEvent($actor, $sermon, null, $sermon->status);
            }

            return;
        }

        $changedKeys = collect($newValues)
            ->filter(fn (mixed $value, string $key): bool => ($oldValues[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if ($changedKeys === []) {
            return;
        }

        $safeOld = Arr::only($oldValues, $changedKeys);
        $safeNew = Arr::only($newValues, $changedKeys);
        $this->activityLogger->log(
            logName: 'sermons',
            event: 'sermon.updated',
            description: "Updated sermon “{$sermon->title}”.",
            subject: $sermon,
            causer: $actor,
            oldValues: $safeOld,
            newValues: $safeNew,
        );

        foreach ([
            'media_url' => 'media_url_changed',
            'thumbnail' => 'thumbnail_changed',
            'speaker' => 'speaker_changed',
            'series' => 'series_changed',
            'topics' => 'topics_changed',
        ] as $field => $event) {
            if (in_array($field, $changedKeys, true)) {
                $this->activityLogger->log(
                    logName: 'sermons',
                    event: "sermon.{$event}",
                    description: 'Changed '.str($field)->replace('_', ' ')." for sermon “{$sermon->title}”.",
                    subject: $sermon,
                    causer: $actor,
                    oldValues: [$field => $safeOld[$field] ?? null],
                    newValues: [$field => $safeNew[$field] ?? null],
                );
            }
        }

        if (in_array('status', $changedKeys, true)) {
            $this->logWorkflowEvent(
                $actor,
                $sermon,
                SermonStatus::tryFrom((string) ($safeOld['status'] ?? '')),
                $sermon->status,
            );
        }

        if (in_array('is_featured', $changedKeys, true)) {
            $this->activityLogger->log(
                logName: 'sermons',
                event: $sermon->is_featured ? 'sermon.featured' : 'sermon.unfeatured',
                description: ($sermon->is_featured ? 'Featured' : 'Unfeatured')." sermon “{$sermon->title}”.",
                subject: $sermon,
                causer: $actor,
            );
        }
    }

    private function logWorkflowEvent(User $actor, Sermon $sermon, ?SermonStatus $oldStatus, SermonStatus $newStatus): void
    {
        $verb = match ($newStatus) {
            SermonStatus::Published => 'Published',
            SermonStatus::Scheduled => 'Scheduled',
            SermonStatus::Unpublished => 'Unpublished',
            SermonStatus::Archived => 'Archived',
            SermonStatus::Draft => 'Returned to draft',
        };

        $this->activityLogger->log(
            logName: 'sermons',
            event: 'sermon.'.$newStatus->value,
            description: "{$verb} sermon “{$sermon->title}”.",
            subject: $sermon,
            causer: $actor,
            oldValues: ['status' => $oldStatus?->value],
            newValues: ['status' => $newStatus->value],
        );
    }
}
