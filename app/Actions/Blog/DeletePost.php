<?php

namespace App\Actions\Blog;

use App\Activity\ActivityLogger;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeletePost
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, Post $post): void
    {
        Gate::forUser($actor)->authorize('delete', $post);
        $post->delete();
        $this->log($actor, $post, 'deleted');
    }

    public function restore(User $actor, Post $post): void
    {
        Gate::forUser($actor)->authorize('restore', $post);
        $post->restore();
        $post->forceFill(['status' => 'draft', 'published_at' => null, 'scheduled_for' => null])->save();
        $this->log($actor, $post, 'restored');
    }

    public function forceDelete(User $actor, Post $post): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $post);
        $image = $post->featured_image;
        DB::transaction(function () use ($post): void {
            $post->tags()->detach();
            $post->forceDelete();
        });
        if ($image !== null && ! Post::withTrashed()->where('featured_image', $image)->exists()) {
            Storage::disk('public')->delete($image);
        }
        $this->log($actor, $post, 'force-deleted');
    }

    private function log(User $actor, Post $post, string $event): void
    {
        $this->activityLogger->log(
            logName: 'blog', event: "post.{$event}",
            description: str($event)->headline()->toString()." post \"{$post->title}\".",
            subject: $post, causer: $actor, properties: ['slug' => $post->slug],
        );
    }
}
