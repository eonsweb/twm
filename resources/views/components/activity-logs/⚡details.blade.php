<?php

use App\Activity\ActivityLogPresenter;
use App\Activity\ActivitySanitizer;
use App\Models\ActivityLog;
use App\Models\User;
use App\PermissionName;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $showModal = false;

    /** @var array<string, mixed> */
    public array $details = [];

    public function mount(): void
    {
        Gate::authorize(PermissionName::ActivityLogsViewDetails->value);
    }

    #[On('activity-log-selected')]
    public function show(int $activityId, ActivityLogPresenter $presenter, ActivitySanitizer $sanitizer): void
    {
        Gate::authorize(PermissionName::ActivityLogsViewDetails->value);

        $activity = ActivityLog::query()
            ->with(['causer', 'subject'])
            ->findOrFail($activityId);
        $oldValues = $presenter->oldValues($activity);
        $newValues = $presenter->newValues($activity);
        $changeKeys = collect([...array_keys($oldValues), ...array_keys($newValues)])->unique();

        $this->details = [
            'id' => $activity->id,
            'created_at' => $activity->created_at->format('M j, Y g:i:s A T'),
            'description' => $activity->description,
            'event' => Str::headline($activity->event),
            'module' => $presenter->moduleLabel($activity),
            'status' => Str::headline($activity->status),
            'origin' => Str::headline($activity->origin),
            'causer' => $presenter->causerLabel($activity),
            'subject' => $presenter->subjectLabel($activity),
            'subject_url' => $this->subjectUrl($activity),
            'ip_address' => $activity->ip_address ?? '—',
            'user_agent' => $sanitizer->sanitizeText($activity->user_agent, 1000) ?? '—',
            'request_url' => $sanitizer->sanitizeText($activity->request_url, 1000) ?? '—',
            'http_method' => $activity->http_method ?? '—',
            'route_name' => $activity->route_name ?? '—',
            'request_id' => $activity->request_id ?? '—',
            'failure_reason' => $sanitizer->sanitizeText($activity->failure_reason, 500),
            'properties' => $presenter->properties($activity),
            'changes' => $changeKeys->map(fn (string $key): array => [
                'field' => Str::headline($key),
                'old' => $oldValues[$key] ?? '—',
                'new' => $newValues[$key] ?? '—',
            ])->all(),
        ];
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->details = [];
    }

    private function subjectUrl(ActivityLog $activity): ?string
    {
        if ($activity->subject instanceof User && Gate::allows('view', $activity->subject)) {
            return route('users.show', $activity->subject);
        }

        return null;
    }
};
?>

<flux:modal wire:model="showModal" class="w-full max-w-4xl">
    @if ($details !== [])
        <div class="space-y-6">
            <div>
                <flux:heading size="xl">{{ __('Activity details') }}</flux:heading>
                <flux:subheading>{{ $details['description'] }}</flux:subheading>
            </div>

            <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:bg-zinc-800/70">
                @foreach ([
                    __('Activity ID') => $details['id'],
                    __('Date and time') => $details['created_at'],
                    __('Event') => $details['event'],
                    __('Module') => $details['module'],
                    __('Status') => $details['status'],
                    __('Origin') => $details['origin'],
                    __('Causer') => $details['causer'],
                    __('Subject') => $details['subject'],
                    __('IP address') => $details['ip_address'],
                    __('HTTP method') => $details['http_method'],
                    __('Route') => $details['route_name'],
                    __('Request ID') => $details['request_id'],
                ] as $label => $value)
                    <div wire:key="activity-detail-{{ \Illuminate\Support\Str::slug($label) }}">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-900 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($details['subject_url'])
                <flux:button :href="$details['subject_url']" variant="outline" icon="arrow-top-right-on-square" wire:navigate>
                    {{ __('View available subject') }}
                </flux:button>
            @endif

            @if ($details['failure_reason'])
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('Failure reason') }}</flux:callout.heading>
                    <flux:callout.text>{{ $details['failure_reason'] }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($details['changes'] !== [])
                <section>
                    <flux:heading size="lg">{{ __('Changed values') }}</flux:heading>
                    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-slate-50 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Field') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('Previous') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold">{{ __('New') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-zinc-700">
                                @foreach ($details['changes'] as $change)
                                    <tr wire:key="changed-field-{{ \Illuminate\Support\Str::slug($change['field']) }}">
                                        <td class="px-4 py-3 font-medium">{{ $change['field'] }}</td>
                                        <td class="max-w-xs px-4 py-3 align-top"><pre class="whitespace-pre-wrap break-words font-sans text-xs">{{ is_array($change['old']) ? json_encode($change['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $change['old'] }}</pre></td>
                                        <td class="max-w-xs px-4 py-3 align-top"><pre class="whitespace-pre-wrap break-words font-sans text-xs">{{ is_array($change['new']) ? json_encode($change['new'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $change['new'] }}</pre></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($details['properties'] !== [])
                <section>
                    <flux:heading size="lg">{{ __('Safe metadata') }}</flux:heading>
                    <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-100">{{ json_encode($details['properties'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif

            <section class="space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Request URL') }}</p>
                    <p class="mt-1 break-all text-sm">{{ $details['request_url'] }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('User agent') }}</p>
                    <p class="mt-1 break-words text-sm">{{ $details['user_agent'] }}</p>
                </div>
            </section>

            <div class="flex justify-end">
                <flux:button type="button" wire:click="close" variant="primary">{{ __('Close') }}</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
