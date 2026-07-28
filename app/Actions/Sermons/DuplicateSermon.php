<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\Sermon;
use App\Models\User;
use App\SermonStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicateSermon
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, Sermon $source): Sermon
    {
        Gate::forUser($actor)->authorize('duplicate', $source);
        $source->loadMissing('topics:id');

        $duplicate = DB::transaction(function () use ($actor, $source): Sermon {
            $duplicate = $source->replicate([
                'slug',
                'status',
                'published_at',
                'scheduled_at',
                'is_featured',
                'thumbnail_path',
                'created_by',
                'updated_by',
            ]);
            $duplicate->forceFill([
                'title' => Str::limit($source->title.' (Copy)', 200, ''),
                'slug' => $this->uniqueSlug($source->slug.'-copy'),
                'status' => SermonStatus::Draft,
                'published_at' => null,
                'scheduled_at' => null,
                'is_featured' => false,
                'thumbnail_path' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
            $duplicate->topics()->sync($source->topics->modelKeys());

            return $duplicate->load(['speaker', 'series', 'topics']);
        });

        $this->activityLogger->log(
            logName: 'sermons',
            event: 'sermon.duplicated',
            description: "Duplicated sermon “{$source->title}” as “{$duplicate->title}”.",
            subject: $duplicate,
            causer: $actor,
            properties: ['source_sermon_id' => $source->id],
            newValues: ['slug' => $duplicate->slug, 'status' => SermonStatus::Draft->value],
        );

        return $duplicate;
    }

    private function uniqueSlug(string $base): string
    {
        $base = Str::slug($base);
        $slug = $base;
        $counter = 2;

        while (Sermon::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
