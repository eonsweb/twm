<?php

namespace App\Http\Controllers;

use App\Activity\ActivityCsvExporter;
use App\Activity\ActivityLogger;
use App\Activity\ActivityLogQuery;
use App\Http\Requests\ExportActivityLogsRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogExportController extends Controller
{
    public function __construct(
        private readonly ActivityLogQuery $activityLogQuery,
        private readonly ActivityCsvExporter $exporter,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(ExportActivityLogsRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        $this->activityLogger->log(
            logName: 'activity-logs',
            event: 'activity_logs.exported',
            description: 'Exported filtered activity logs.',
            causer: $request->user(),
            properties: ['filters' => $filters],
        );

        return response()->streamDownload(
            fn () => $this->exporter->stream($this->activityLogQuery->build($filters)),
            'activity-logs-'.now()->format('Y-m-d-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
