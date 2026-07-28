<?php

use App\Models\Sermon;
use App\Models\User;
use App\PermissionName;
use App\SermonStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('Dashboard')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize(PermissionName::DashboardView->value);
    }

    /**
     * @return list<array{label: string, value: int|string, caption: string, icon: string, tone: string, permission: string}>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            [
                'label' => __('Sermons'),
                'value' => Sermon::query()->publiclyAvailable()->count(),
                'caption' => __('Published sermons'),
                'icon' => 'play-circle',
                'tone' => 'gold',
                'permission' => PermissionName::SermonsView->value,
            ],
            [
                'label' => __('Events'),
                'value' => '—',
                'caption' => __('Content module coming soon'),
                'icon' => 'calendar-days',
                'tone' => 'blue',
                'permission' => PermissionName::EventsView->value,
            ],
            [
                'label' => __('Ministries'),
                'value' => '—',
                'caption' => __('Content module coming soon'),
                'icon' => 'user-group',
                'tone' => 'green',
                'permission' => PermissionName::MinistriesView->value,
            ],
            [
                'label' => __('Blog posts'),
                'value' => '—',
                'caption' => __('Content module coming soon'),
                'icon' => 'document-text',
                'tone' => 'purple',
                'permission' => PermissionName::PostsView->value,
            ],
            [
                'label' => __('Donations'),
                'value' => '—',
                'caption' => __('Engagement module coming soon'),
                'icon' => 'heart',
                'tone' => 'rose',
                'permission' => PermissionName::DonationsView->value,
            ],
            [
                'label' => __('Media files'),
                'value' => '—',
                'caption' => __('Media module coming soon'),
                'icon' => 'photo',
                'tone' => 'slate',
                'permission' => PermissionName::MediaView->value,
            ],
            [
                'label' => __('Users'),
                'value' => User::query()->count(),
                'caption' => __('Registered administrator accounts'),
                'icon' => 'users',
                'tone' => 'maroon',
                'permission' => PermissionName::UsersView->value,
            ],
        ];
    }

    public function firstName(): string
    {
        return Str::before(auth()->user()->name, ' ');
    }

    #[Computed]
    public function recentSermons()
    {
        return Sermon::query()
            ->with('speaker:id,title,first_name,middle_name,last_name')
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'speaker_id', 'status', 'updated_at']);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-7">
    <x-admin.page-header
        :title="__('Welcome back, :name!', ['name' => $this->firstName()])"
        :description="__('Here is an overview of your church website and administration activity.')"
        :eyebrow="__('Administration dashboard')"
    />

    <section aria-labelledby="overview-heading">
        <h2 id="overview-heading" class="sr-only">{{ __('Website overview') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
            @foreach ($this->stats as $stat)
                @can($stat['permission'])
                    <x-admin.stat-card
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :caption="$stat['caption']"
                        :icon="$stat['icon']"
                        :tone="$stat['tone']"
                        wire:key="stat-{{ Str::slug($stat['label']) }}"
                    />
                @endcan
            @endforeach
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-3" aria-label="{{ __('Recent administration activity') }}">
        @can(PermissionName::SermonsView->value)
            <article class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('Recent sermons') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-500">{{ __('Latest messages published to the church website') }}</p>
                </div>
                @forelse ($this->recentSermons as $sermon)
                    <a href="{{ route('sermons.show', $sermon) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-zinc-800" wire:navigate wire:key="dashboard-sermon-{{ $sermon->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $sermon->title }}</p>
                            <p class="text-xs text-slate-500">{{ $sermon->speaker->full_name }}</p>
                        </div>
                        <flux:badge :color="$sermon->status === SermonStatus::Published ? 'green' : 'zinc'">{{ $sermon->status->label() }}</flux:badge>
                    </a>
                @empty
                    <x-admin.empty-state icon="play-circle" :title="__('No sermon data yet')" :description="__('Create the first sermon to see it here.')" />
                @endforelse
            </article>
        @endcan

        @can(PermissionName::EventsView->value)
            <article class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('Upcoming events') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-500">{{ __('Scheduled gatherings and ministry activities') }}</p>
                </div>
                <x-admin.empty-state
                    icon="calendar-days"
                    :title="__('No event data yet')"
                    :description="__('Upcoming events will appear here when the events module is available.')"
                />
            </article>
        @endcan

        @can(PermissionName::DonationsView->value)
            <article class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('Recent donations') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-500">{{ __('The latest giving activity') }}</p>
                </div>
                <x-admin.empty-state
                    icon="heart"
                    :title="__('No donation data yet')"
                    :description="__('Recent donations will appear here when the donations module is available.')"
                />
            </article>
        @endcan
    </section>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <article class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('Quick actions') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-500">{{ __('Shortcuts will activate as each content module is added.') }}</p>

            @php
                $quickActions = [
                    ['label' => __('Add sermon'), 'icon' => 'plus-circle', 'permission' => PermissionName::SermonsCreate->value, 'route' => 'sermons.create'],
                    ['label' => __('Create event'), 'icon' => 'calendar-days', 'permission' => PermissionName::EventsCreate->value, 'route' => 'events.create'],
                    ['label' => __('New blog post'), 'icon' => 'document-plus', 'permission' => PermissionName::PostsCreate->value, 'route' => 'posts.create'],
                    ['label' => __('Upload media'), 'icon' => 'arrow-up-tray', 'permission' => PermissionName::MediaUpload->value, 'route' => 'media.create'],
                    ['label' => __('Add page'), 'icon' => 'document-duplicate', 'permission' => PermissionName::PagesCreate->value, 'route' => 'pages.create'],
                    ['label' => __('View donations'), 'icon' => 'heart', 'permission' => PermissionName::DonationsView->value, 'route' => 'donations.index'],
                ];
            @endphp

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quickActions as $action)
                    @can($action['permission'])
                        @if (Route::has($action['route']))
                            <flux:button
                                :href="route($action['route'])"
                                :icon="$action['icon']"
                                variant="outline"
                                class="justify-start"
                                wire:navigate
                            >
                                {{ $action['label'] }}
                            </flux:button>
                        @else
                            <flux:button
                                :icon="$action['icon']"
                                variant="outline"
                                class="justify-start"
                                :aria-label="__(':action, coming soon', ['action' => $action['label']])"
                                disabled
                            >
                                {{ $action['label'] }}
                            </flux:button>
                        @endif
                    @endcan
                @endforeach
            </div>
        </article>

        <article class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="font-semibold text-slate-950 dark:text-white">{{ __('Website status') }}</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-zinc-400">{{ __('Public website') }}</dt>
                    <dd>
                        <a href="{{ route('home') }}" class="font-medium text-emerald-700 hover:underline dark:text-emerald-400" wire:navigate>
                            {{ __('Online') }}
                        </a>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500 dark:text-zinc-400">{{ __('Authorization') }}</dt>
                    <dd class="font-medium text-emerald-700 dark:text-emerald-400">{{ __('Operational') }}</dd>
                </div>
                @can(PermissionName::RolesView->value)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500 dark:text-zinc-400">{{ __('Configured roles') }}</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ Role::query()->count() }}</dd>
                    </div>
                @endcan
            </dl>
        </article>
    </section>
</div>
