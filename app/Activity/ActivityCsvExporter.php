<?php

namespace App\Activity;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ActivityCsvExporter
{
    public function __construct(private readonly ActivityLogPresenter $presenter) {}

    /**
     * @param  Builder<ActivityLog>  $query
     */
    public function stream(Builder $query): void
    {
        $output = fopen('php://output', 'wb');

        if ($output === false) {
            throw new RuntimeException('The activity-log export stream could not be opened.');
        }

        fputcsv($output, [
            'ID',
            'Date and time',
            'Causer',
            'Event',
            'Module',
            'Description',
            'Subject',
            'IP address',
            'Status',
            'Origin',
            'HTTP method',
            'Route',
            'Request ID',
        ]);

        $query
            ->reorder('id')
            ->with(['causer', 'subject'])
            ->chunkById(500, function ($activities) use ($output): void {
                foreach ($activities as $activity) {
                    fputcsv($output, array_map($this->sanitizeCell(...), [
                        $activity->id,
                        $activity->created_at->toIso8601String(),
                        $this->presenter->causerLabel($activity),
                        $activity->event,
                        $activity->log_name,
                        $activity->description,
                        $this->presenter->subjectLabel($activity),
                        $activity->ip_address,
                        $activity->status,
                        $activity->origin,
                        $activity->http_method,
                        $activity->route_name,
                        $activity->request_id,
                    ]));
                }
            });

        fclose($output);
    }

    public function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'".$value : $value;
    }
}
