<?php

namespace App\Console\Commands;

use App\Activity\ActivityLogger;
use App\Models\Page;
use App\PageStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pages:publish-scheduled')]
#[Description('Publish pages whose scheduled publication time has arrived')]
class PublishScheduledPages extends Command
{
    public function handle(ActivityLogger $logger): int
    {
        $published = 0;
        Page::query()->where('status', PageStatus::Scheduled)->whereNotNull('published_at')->where('published_at', '<=', now())->orderBy('id')->chunkById(100, function ($pages) use ($logger, &$published): void {
            foreach ($pages as $page) {
                $updated = Page::query()->whereKey($page)->where('status', PageStatus::Scheduled)->update(['status' => PageStatus::Published, 'updated_at' => now()]);
                if ($updated === 1) {
                    $logger->log('pages', 'page.published', 'Published scheduled page "'.$page->title.'".', $page, origin: 'scheduler');
                    $published++;
                }
            }
        });
        $this->info("Published {$published} scheduled page(s).");

        return self::SUCCESS;
    }
}
