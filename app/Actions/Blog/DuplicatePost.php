<?php

namespace App\Actions\Blog;

use App\Activity\ActivityLogger;
use App\Models\Post;
use App\Models\User;
use App\PostStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DuplicatePost
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, Post $source): Post
    {
        Gate::forUser($actor)->authorize('duplicate', $source);

        $copy = DB::transaction(function () use ($actor, $source): Post {
            $source->loadMissing('tags:id');
            $copy = $source->replicate([
                'slug', 'status', 'published_at', 'scheduled_for', 'archived_at',
                'created_at', 'updated_at', 'deleted_at',
            ]);
            $copy->title = $source->title.' Copy';
            $copy->slug = Post::uniqueSlug($copy->title);
            $copy->status = PostStatus::Draft;
            $copy->author_id = $actor->id;
            $copy->is_featured = false;
            $copy->save();
            $copy->tags()->sync($source->tags->modelKeys());

            return $copy->refresh()->load(['author:id,name', 'category:id,name', 'tags:id,name']);
        });
        $this->activityLogger->log(
            logName: 'blog', event: 'post.duplicated',
            description: "Duplicated post \"{$source->title}\" as \"{$copy->title}\".",
            subject: $copy, causer: $actor, properties: ['source_post_id' => $source->id],
        );

        return $copy;
    }
}
