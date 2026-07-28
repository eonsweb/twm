<?php

use App\Activity\ActivityLogger;
use App\Models\ActivityLog;
use App\Settings\SettingManager;
use Database\Seeders\SystemSettingSeeder;

beforeEach(function () {
    $this->seed(SystemSettingSeeder::class);
});

test('pruning removes only expired non-security activity and records the operation', function () {
    $logger = app(ActivityLogger::class);
    $expired = $logger->log(
        logName: 'users',
        event: 'user.expired',
        description: 'Expired administrative activity.',
    );
    $security = $logger->log(
        logName: 'authentication',
        event: 'login.old',
        description: 'Old security activity.',
    );
    $recent = $logger->log(
        logName: 'users',
        event: 'user.recent',
        description: 'Recent activity.',
    );

    ActivityLog::query()
        ->whereKey([$expired->id, $security->id])
        ->update(['created_at' => now()->subDays(400)]);

    $this->artisan('activity-logs:prune')->assertSuccessful();

    expect(ActivityLog::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->whereKey($security->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->whereKey($recent->id)->exists())->toBeTrue();

    $pruningActivity = ActivityLog::query()->where('event', 'activity_logs.pruned')->firstOrFail();

    expect(data_get($pruningActivity->properties, 'deleted_count'))->toBe(1)
        ->and(data_get($pruningActivity->properties, 'security_logs_retained'))->toBeTrue()
        ->and($pruningActivity->origin)->toBe('console');
});

test('configured retention controls the pruning cutoff', function () {
    app(SettingManager::class)->put('activity_logs', 'retention_days', 90);
    $activity = app(ActivityLogger::class)->log(
        logName: 'users',
        event: 'user.old',
        description: 'Old user activity.',
    );
    ActivityLog::query()->whereKey($activity->id)->update(['created_at' => now()->subDays(100)]);

    $this->artisan('activity-logs:prune')->assertSuccessful();

    expect(ActivityLog::query()->whereKey($activity->id)->exists())->toBeFalse();
});

test('command line retention overrides are validated', function () {
    $this->artisan('activity-logs:prune', ['--days' => 5])
        ->expectsOutput('Retention must be at least 30 days.')
        ->assertFailed();
});

test('the pruning command is registered with the scheduler', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('activity-logs:prune')
        ->assertSuccessful();
});
