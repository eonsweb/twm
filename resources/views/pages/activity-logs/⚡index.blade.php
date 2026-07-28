<?php

use App\Activity\ActivityLogPresenter;
use App\Activity\ActivityLogQuery;
use App\Models\ActivityLog;
use App\Models\User;
use App\PermissionName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Activity Logs')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $causerId = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $event = '';

    #[Url]
    public string $logName = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $subjectType = '';

    #[Url]
    public string $ipAddress = '';

    #[Url]
    public string $actorType = '';

    public int $perPage = 25;

    protected ActivityLogQuery $activityLogQuery;

    protected ActivityLogPresenter $presenter;

    public function boot(ActivityLogQuery $activityLogQuery, ActivityLogPresenter $presenter): void
    {
        $this->activityLogQuery = $activityLogQuery;
        $this->presenter = $presenter;
    }

    public function mount(): void
    {
        Gate::authorize(PermissionName::ActivityLogsView->value);
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'search',
            'dateFrom',
            'dateTo',
            'causerId',
            'role',
            'event',
            'logName',
            'status',
            'subjectType',
            'ipAddress',
            'actorType',
        ], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'dateFrom',
            'dateTo',
            'causerId',
            'role',
            'event',
            'logName',
            'status',
            'subjectType',
            'ipAddress',
            'actorType',
        ]);
        $this->resetPage();
    }

    public function viewDetails(int $activityId): void
    {
        Gate::authorize(PermissionName::ActivityLogsViewDetails->value);

        $this->dispatch('activity-log-selected', activityId: $activityId);
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return $this->activityLogQuery
            ->build($this->filters())
            ->select([
                'id',
                'log_name',
                'event',
                'description',
                'subject_type',
                'subject_id',
                'causer_type',
                'causer_id',
                'ip_address',
                'origin',
                'status',
                'created_at',
            ])
            ->with(['causer', 'subject'])
            ->paginate($this->perPage);
    }

    #[Computed]
    public function totalActivityCount(): int
    {
        return ActivityLog::query()->count();
    }

    #[Computed]
    public function causers()
    {
        return User::query()
            ->whereIn('id', ActivityLog::query()
                ->select('causer_id')
                ->where('causer_type', (new User)->getMorphClass())
                ->whereNotNull('causer_id'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'username']);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function events()
    {
        return ActivityLog::query()->select('event')->distinct()->orderBy('event')->limit(100)->pluck('event');
    }

    #[Computed]
    public function logNames()
    {
        return ActivityLog::query()->select('log_name')->distinct()->orderBy('log_name')->limit(100)->pluck('log_name');
    }

    #[Computed]
    public function subjectTypes()
    {
        return ActivityLog::query()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->limit(100)
            ->pluck('subject_type');
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('activity-logs.export', array_filter(
            $this->filters(),
            fn (mixed $value): bool => filled($value),
        ));
    }

    public function causerLabel(ActivityLog $activity): string
    {
        return $this->presenter->causerLabel($activity);
    }

    public function subjectLabel(ActivityLog $activity): string
    {
        return $this->presenter->subjectLabel($activity);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return [
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'causer_id' => $this->causerId,
            'role' => $this->role,
            'event' => $this->event,
            'log_name' => $this->logName,
            'status' => $this->status,
            'subject_type' => $this->subjectType,
            'ip_address' => $this->ipAddress,
            'actor_type' => $this->actorType,
        ];
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Activity logs') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Activity logs')"
        :description="__('Review important authentication, administration, leadership, and system activity.')"
        :eyebrow="__('System')"
    >
        <x-slot:actions>
            @can(\App\PermissionName::ActivityLogsExport->value)
                <flux:button :href="$this->exportUrl" variant="outline" icon="arrow-down-tray">
                    {{ __('Export filtered CSV') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat-card :label="__('Total activities')" :value="number_format($this->totalActivityCount)" :caption="__('Append-only records across all modules')" icon="clipboard-document-list" tone="maroon" />
        <x-admin.stat-card :label="__('Filtered results')" :value="number_format($this->activities->total())" :caption="__('Records matching the active filters')" icon="funnel" tone="green" />
    </section>

    <section class="rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-200/80 p-4 sm:p-5 dark:border-zinc-800">
            <div class="grid gap-4 lg:grid-cols-[minmax(16rem,2fr)_repeat(3,minmax(10rem,1fr))]">
                <flux:input
                    wire:model.live.debounce.350ms="search"
                    icon="magnifying-glass"
                    :label="__('Search')"
                    :placeholder="__('Description, user, event, IP…')"
                />
                <flux:select wire:model.live="logName" :label="__('Module')">
                    <flux:select.option value="">{{ __('All modules') }}</flux:select.option>
                    @foreach ($this->logNames as $option)
                        <flux:select.option :value="$option" wire:key="log-name-{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="event" :label="__('Event')">
                    <flux:select.option value="">{{ __('All events') }}</flux:select.option>
                    @foreach ($this->events as $option)
                        <flux:select.option :value="$option" wire:key="event-{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="status" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    <flux:select.option value="success">{{ __('Success') }}</flux:select.option>
                    <flux:select.option value="failure">{{ __('Failure') }}</flux:select.option>
                    <flux:select.option value="warning">{{ __('Warning') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                <flux:input wire:model.live="dateFrom" type="date" :label="__('From date')" />
                <flux:input wire:model.live="dateTo" type="date" :label="__('To date')" />
                <flux:select wire:model.live="causerId" :label="__('User')">
                    <flux:select.option value="">{{ __('All users') }}</flux:select.option>
                    @foreach ($this->causers as $causer)
                        <flux:select.option :value="$causer->id" wire:key="causer-{{ $causer->id }}">
                            {{ $causer->name }} ({{ '@'.$causer->username }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="role" :label="__('Role')">
                    <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                    @foreach ($this->roles as $roleOption)
                        <flux:select.option :value="$roleOption->name" wire:key="role-filter-{{ $roleOption->id }}">
                            {{ \Illuminate\Support\Str::headline($roleOption->name) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="subjectType" :label="__('Subject type')">
                    <flux:select.option value="">{{ __('All subjects') }}</flux:select.option>
                    @foreach ($this->subjectTypes as $typeOption)
                        <flux:select.option :value="$typeOption" wire:key="subject-type-{{ md5($typeOption) }}">
                            {{ \Illuminate\Support\Str::headline(class_basename($typeOption)) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="actorType" :label="__('Actor type')">
                    <flux:select.option value="">{{ __('All activity') }}</flux:select.option>
                    <flux:select.option value="authenticated">{{ __('Authenticated') }}</flux:select.option>
                    <flux:select.option value="system">{{ __('Guest or system') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model.live.debounce.350ms="ipAddress" :label="__('IP address')" placeholder="203.0.113.10" />
            </div>

            <div class="flex justify-end">
                <flux:button type="button" wire:click="resetFilters" variant="ghost" icon="x-mark">
                    {{ __('Reset filters') }}
                </flux:button>
            </div>
        </div>

        <div id="activity-log-table" class="overflow-x-auto">
            @if ($this->activities->isEmpty())
                <div class="p-6 sm:p-10">
                    <x-admin.empty-state
                        icon="clipboard-document-list"
                        :title="__('No activity found')"
                        :description="__('Try changing the filters or search terms.')"
                    />
                </div>
            @else
                <flux:table :paginate="$this->activities" pagination:scroll-to="#activity-log-table">
                    <flux:table.columns>
                        <flux:table.column class="ps-5 sm:ps-6">{{ __('Date and time') }}</flux:table.column>
                        <flux:table.column>{{ __('Causer') }}</flux:table.column>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                        <flux:table.column>{{ __('Module') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column>{{ __('Subject') }}</flux:table.column>
                        <flux:table.column>{{ __('IP address') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="pe-5 text-end sm:pe-6">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->activities as $activity)
                            <flux:table.row :key="$activity->id">
                                <flux:table.cell class="whitespace-nowrap ps-5 sm:ps-6">
                                    <span class="block font-medium text-slate-900 dark:text-white">{{ $activity->created_at->format('M j, Y') }}</span>
                                    <span class="text-xs text-slate-500 dark:text-zinc-400">{{ $activity->created_at->format('g:i:s A') }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $this->causerLabel($activity) }}</flux:table.cell>
                                <flux:table.cell>{{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::afterLast($activity->event, '.')) }}</flux:table.cell>
                                <flux:table.cell>{{ \Illuminate\Support\Str::headline($activity->log_name) }}</flux:table.cell>
                                <flux:table.cell class="min-w-72 max-w-md">
                                    <p class="line-clamp-2 text-sm">{{ $activity->description }}</p>
                                </flux:table.cell>
                                <flux:table.cell>{{ $this->subjectLabel($activity) }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $activity->ip_address ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge
                                        :color="match ($activity->status) {
                                            'success' => 'green',
                                            'failure' => 'red',
                                            default => 'amber',
                                        }"
                                        size="sm"
                                    >
                                        {{ \Illuminate\Support\Str::headline($activity->status) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="pe-5 text-end sm:pe-6">
                                    @can(\App\PermissionName::ActivityLogsViewDetails->value)
                                        <flux:button
                                            type="button"
                                            wire:click="viewDetails({{ $activity->id }})"
                                            variant="ghost"
                                            icon="eye"
                                            size="sm"
                                            :aria-label="__('View activity details')"
                                        />
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        <div wire:loading.flex class="items-center gap-2 border-t border-slate-200 px-5 py-3 text-sm text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
            <flux:icon.arrow-path class="size-4 animate-spin" />
            {{ __('Loading activity…') }}
        </div>
    </section>

    @can(\App\PermissionName::ActivityLogsViewDetails->value)
        <livewire:activity-logs.details />
    @endcan
</div>
