<?php

namespace App\Console\Commands;

use App\Activity\ActivityLogger;
use App\Models\ActivityLog;
use App\Settings\SettingManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('activity-logs:prune {--days= : Override the configured retention period} {--batch=1000 : Rows deleted per batch}')]
#[Description('Prune expired activity logs in bounded batches.')]
class PruneActivityLogs extends Command
{
    public function __construct(
        private readonly SettingManager $settings,
        private readonly ActivityLogger $activityLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $retentionDays = $this->option('days') === null
            ? (int) $this->settings->get('activity_logs', 'retention_days', 365)
            : (int) $this->option('days');
        $batchSize = max(100, min(5000, (int) $this->option('batch')));

        if ($retentionDays < 30) {
            $this->error('Retention must be at least 30 days.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($retentionDays);
        $retainSecurityLogs = (bool) $this->settings->get('activity_logs', 'retain_security_logs', true);
        $deleted = 0;

        do {
            $ids = ActivityLog::query()
                ->where('created_at', '<', $cutoff)
                ->when($retainSecurityLogs, fn ($query) => $query->where('log_name', '!=', 'authentication'))
                ->oldest('id')
                ->limit($batchSize)
                ->pluck('id');

            $batchDeleted = $ids->isEmpty()
                ? 0
                : ActivityLog::query()->whereKey($ids)->delete();
            $deleted += $batchDeleted;
        } while ($batchDeleted === $batchSize);

        $this->activityLogger->log(
            logName: 'activity-logs',
            event: 'activity_logs.pruned',
            description: "Pruned {$deleted} expired activity logs.",
            properties: [
                'deleted_count' => $deleted,
                'retention_days' => $retentionDays,
                'cutoff' => $cutoff,
                'security_logs_retained' => $retainSecurityLogs,
            ],
            origin: 'console',
        );

        $this->info("Pruned {$deleted} expired activity logs.");

        return self::SUCCESS;
    }
}
