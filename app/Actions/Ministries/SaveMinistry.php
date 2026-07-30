<?php

namespace App\Actions\Ministries;

use App\Activity\ActivityLogger;
use App\MinistryStatus;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SaveMinistry
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{person_id: int, role_title: string|null, is_primary: bool, display_order: int}>  $leaders
     * @param  list<int>  $sermonIds
     * @param  list<int>  $eventIds
     */
    public function handle(
        User $actor,
        array $data,
        array $leaders = [],
        array $sermonIds = [],
        array $eventIds = [],
        ?UploadedFile $featuredImage = null,
        ?UploadedFile $logo = null,
        bool $removeFeaturedImage = false,
        bool $removeLogo = false,
        ?Ministry $ministry = null,
    ): Ministry {
        $creating = $ministry === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $ministry ?? Ministry::class);
        $ministry ??= new Ministry;
        $this->authorizeWorkflow($actor, $ministry, $data);

        $oldValues = $creating ? [] : $this->auditValues($ministry->loadMissing('leaders:id'));
        $oldFeaturedImage = $ministry->featured_image;
        $oldLogo = $ministry->logo;
        $newFeaturedImage = $this->store($featuredImage, 'ministries/featured');
        $newLogo = $this->store($logo, 'ministries/logos');

        if ($newFeaturedImage !== null) {
            $data['featured_image'] = $newFeaturedImage;
        } elseif ($removeFeaturedImage) {
            $data['featured_image'] = null;
        }

        if ($newLogo !== null) {
            $data['logo'] = $newLogo;
        } elseif ($removeLogo) {
            $data['logo'] = null;
        }

        $data['slug'] = Ministry::uniqueSlug(
            (string) ($data['slug'] ?: $data['name']),
            $ministry->exists ? $ministry->id : null,
        );
        $data['created_by'] ??= $creating ? $actor->id : $ministry->created_by;
        $data['updated_by'] = $actor->id;

        try {
            $saved = DB::transaction(function () use ($ministry, $data, $leaders, $sermonIds, $eventIds): Ministry {
                $ministry->fill($data)->save();
                $ministry->leaders()->sync(collect($leaders)->mapWithKeys(
                    fn (array $leader): array => [$leader['person_id'] => Arr::except($leader, 'person_id')],
                )->all());
                $ministry->sermons()->sync($sermonIds);
                $ministry->events()->whereNotIn('id', $eventIds)->update(['ministry_id' => null]);
                if ($eventIds !== []) {
                    $ministry->events()->getModel()->newQuery()->whereKey($eventIds)->update(['ministry_id' => $ministry->id]);
                }

                return $ministry->refresh()->load(['leaders', 'sermons:id', 'events:id']);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(array_filter([$newFeaturedImage, $newLogo]));
            throw $exception;
        }

        $this->deleteReplacedFile($oldFeaturedImage, $saved->featured_image, $newFeaturedImage !== null || $removeFeaturedImage);
        $this->deleteReplacedFile($oldLogo, $saved->logo, $newLogo !== null || $removeLogo);
        $newValues = $this->auditValues($saved);
        $keys = $creating ? array_keys($newValues) : collect($newValues)
            ->filter(fn (mixed $value, string $key): bool => ($oldValues[$key] ?? null) !== $value)
            ->keys()->all();

        if ($keys !== []) {
            $this->activityLogger->log(
                logName: 'ministries',
                event: $creating ? 'ministry.created' : 'ministry.updated',
                description: ($creating ? 'Created' : 'Updated')." ministry \"{$saved->name}\".",
                subject: $saved,
                causer: $actor,
                oldValues: Arr::only($oldValues, $keys),
                newValues: Arr::only($newValues, $keys),
            );
        }

        if (($oldValues['leaders'] ?? []) !== $newValues['leaders']) {
            $this->activityLogger->log(
                logName: 'ministries',
                event: 'ministry.leaders-changed',
                description: "Changed leaders for ministry \"{$saved->name}\".",
                subject: $saved,
                causer: $actor,
                oldValues: ['leaders' => $oldValues['leaders'] ?? []],
                newValues: ['leaders' => $newValues['leaders']],
            );
        }

        if (($oldValues['status'] ?? MinistryStatus::Draft->value) !== $newValues['status']) {
            $event = match ($saved->status) {
                MinistryStatus::Published => 'published',
                MinistryStatus::Inactive => 'marked-inactive',
                MinistryStatus::Draft => 'moved-to-draft',
            };
            $this->activityLogger->log(
                logName: 'ministries',
                event: "ministry.{$event}",
                description: str($event)->headline()->toString()." ministry \"{$saved->name}\".",
                subject: $saved,
                causer: $actor,
                oldValues: ['status' => $oldValues['status'] ?? MinistryStatus::Draft->value],
                newValues: ['status' => $newValues['status']],
            );
        }

        if (($oldValues['is_featured'] ?? false) !== $newValues['is_featured']) {
            $event = $saved->is_featured ? 'featured' : 'unfeatured';
            $this->activityLogger->log(
                logName: 'ministries',
                event: "ministry.{$event}",
                description: str($event)->headline()->toString()." ministry \"{$saved->name}\".",
                subject: $saved,
                causer: $actor,
                oldValues: ['is_featured' => $oldValues['is_featured'] ?? false],
                newValues: ['is_featured' => $newValues['is_featured']],
            );
        }

        return $saved;
    }

    /** @param array<string, mixed> $data */
    private function authorizeWorkflow(User $actor, Ministry $ministry, array $data): void
    {
        $newStatus = MinistryStatus::from((string) $data['status']);
        $oldStatus = $ministry->exists ? $ministry->status : MinistryStatus::Draft;

        if ($newStatus !== $oldStatus && $newStatus === MinistryStatus::Published) {
            Gate::forUser($actor)->authorize('publish', $ministry);
        }

        if ($ministry->exists && $oldStatus === MinistryStatus::Published && $newStatus !== $oldStatus) {
            Gate::forUser($actor)->authorize('publish', $ministry);
        }
    }

    private function store(?UploadedFile $file, string $directory): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $file->store($directory, 'public');
        throw_if($path === false, RuntimeException::class, 'The ministry image could not be stored.');

        return $path;
    }

    private function deleteReplacedFile(?string $old, ?string $current, bool $changed): void
    {
        if ($changed && $old !== null && $old !== $current) {
            Storage::disk('public')->delete($old);
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(Ministry $ministry): array
    {
        return [
            'name' => $ministry->name,
            'slug' => $ministry->slug,
            'status' => $ministry->status->value,
            'is_featured' => $ministry->is_featured,
            'display_order' => $ministry->display_order,
            'published_at' => $ministry->published_at?->toIso8601String(),
            'leaders' => $ministry->leaders->pluck('id')->sort()->values()->all(),
            'featured_image' => $ministry->featured_image === null ? 'Not set' : 'Set',
            'logo' => $ministry->logo === null ? 'Not set' : 'Set',
        ];
    }
}
