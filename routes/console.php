<?php

use App\Console\Commands\PublishScheduledPages;
use App\Console\Commands\PublishScheduledPosts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PublishScheduledPosts::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(PublishScheduledPages::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('activity-logs:prune')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
