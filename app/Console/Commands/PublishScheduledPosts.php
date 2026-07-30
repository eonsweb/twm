<?php

namespace App\Console\Commands;

use App\Activity\ActivityLogger;
use App\Models\Post;
use App\PostStatus;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish blog posts whose scheduled publication time has arrived';

    public function handle(ActivityLogger $activityLogger): int
    {
        $published = 0;
        Post::query()
            ->scheduled()
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->chunkById(100, function ($posts) use ($activityLogger, &$published): void {
                foreach ($posts as $post) {
                    $post->forceFill([
                        'status' => PostStatus::Published,
                        'published_at' => $post->published_at ?? $post->scheduled_for ?? now(),
                        'scheduled_for' => null,
                    ])->save();
                    $activityLogger->log(
                        logName: 'blog', event: 'post.published',
                        description: "Published scheduled post \"{$post->title}\".",
                        subject: $post, oldValues: ['status' => PostStatus::Scheduled->value],
                        newValues: ['status' => PostStatus::Published->value],
                        origin: 'console',
                    );
                    $published++;
                }
            });
        $this->info("Published {$published} scheduled post(s).");

        return self::SUCCESS;
    }
}
