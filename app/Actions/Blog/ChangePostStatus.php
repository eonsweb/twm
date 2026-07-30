<?php

namespace App\Actions\Blog;

use App\Activity\ActivityLogger;
use App\Models\Post;
use App\Models\User;
use App\PostStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangePostStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function publish(User $actor, Post $post): Post
    {
        throw_if(blank(strip_tags($post->content ?? '')), ValidationException::withMessages(['content' => __('Content is required before publishing.')]));

        return $this->change($actor, $post, PostStatus::Published);
    }

    public function draft(User $actor, Post $post): Post
    {
        return $this->change($actor, $post, PostStatus::Draft);
    }

    public function archive(User $actor, Post $post): Post
    {
        return $this->change($actor, $post, PostStatus::Archived);
    }

    private function change(User $actor, Post $post, PostStatus $status): Post
    {
        Gate::forUser($actor)->authorize($status === PostStatus::Archived ? 'archive' : 'publish', $post);
        $oldStatus = $post->status;
        $post->forceFill([
            'status' => $status,
            'published_at' => $status === PostStatus::Published ? ($post->published_at ?? now()) : $post->published_at,
            'scheduled_for' => $status === PostStatus::Scheduled ? $post->scheduled_for : null,
            'archived_at' => $status === PostStatus::Archived ? now() : null,
        ])->save();
        $event = $status === PostStatus::Draft ? 'unpublished' : $status->value;
        $this->activityLogger->log(
            logName: 'blog', event: "post.{$event}",
            description: str($event)->headline()->toString()." post \"{$post->title}\".",
            subject: $post, causer: $actor,
            oldValues: ['status' => $oldStatus->value], newValues: ['status' => $status->value],
        );

        return $post->refresh();
    }
}
