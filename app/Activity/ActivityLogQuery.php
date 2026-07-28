<?php

namespace App\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ActivityLogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ActivityLog>
     */
    public function build(array $filters): Builder
    {
        return ActivityLog::query()
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = '%'.$this->escapeLike(Str::limit(Str::squish($filters['search']), 100, '')).'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('description', 'like', $search)
                        ->orWhere('event', 'like', $search)
                        ->orWhere('log_name', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhere('request_id', 'like', $search)
                        ->orWhereHasMorph(
                            'causer',
                            [User::class],
                            fn (Builder $query) => $query
                                ->where('name', 'like', $search)
                                ->orWhere('username', 'like', $search)
                                ->orWhere('email', 'like', $search),
                        );
                });
            })
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(filled($filters['causer_id'] ?? null), fn (Builder $query) => $query
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $filters['causer_id']))
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->whereHasMorph(
                'causer',
                [User::class],
                fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $query) => $query->where('name', $filters['role']),
                ),
            ))
            ->when(filled($filters['event'] ?? null), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when(filled($filters['log_name'] ?? null), fn (Builder $query) => $query->where('log_name', $filters['log_name']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['subject_type'] ?? null), fn (Builder $query) => $query->where('subject_type', $filters['subject_type']))
            ->when(filled($filters['ip_address'] ?? null), fn (Builder $query) => $query->where('ip_address', $filters['ip_address']))
            ->when(($filters['actor_type'] ?? '') === 'authenticated', fn (Builder $query) => $query->whereNotNull('causer_id'))
            ->when(($filters['actor_type'] ?? '') === 'system', fn (Builder $query) => $query->whereNull('causer_id'))
            ->latest('created_at')
            ->latest('id');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
