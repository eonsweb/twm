<?php

use App\Actions\Sermons\ChangeSermonStatus;
use App\Actions\Sermons\DeleteSermon;
use App\Actions\Sermons\DuplicateSermon;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\User;
use App\SermonMediaPlatform;
use App\SermonMediaType;
use App\SermonStatus;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Sermons')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $status = '';
    #[Url]
    public string $platform = '';
    #[Url]
    public string $mediaType = '';
    #[Url]
    public string $speaker = '';
    #[Url]
    public string $featured = '';
    #[Url]
    public string $createdBy = '';
    #[Url]
    public string $sermonFrom = '';
    #[Url]
    public string $sermonTo = '';
    #[Url]
    public string $publishedFrom = '';
    #[Url]
    public string $publishedTo = '';
    #[Url]
    public string $sort = 'sermon_date';
    #[Url]
    public string $direction = 'desc';

    public int $perPage = 15;
    public bool $showConfirmModal = false;
    public ?int $targetSermonId = null;
    public string $pendingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Sermon::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['showConfirmModal', 'targetSermonId', 'pendingAction'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function sermons(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, ['sermon_date', 'published_at', 'title', 'created_at', 'updated_at'], true)
            ? $this->sort
            : 'sermon_date';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        return Sermon::query()
            ->withTrashed()
            ->select([
                'id', 'title', 'slug', 'thumbnail_path', 'external_thumbnail_url', 'speaker_id',
                'media_platform', 'media_type', 'sermon_date', 'status',
                'is_featured', 'published_at', 'created_by', 'deleted_at', 'created_at', 'updated_at',
            ])
            ->with([
                'speaker:id,title,first_name,middle_name,last_name',
                'creator:id,name',
            ])
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('scripture_reference', 'like', $search)
                        ->orWhere('summary', 'like', $search)
                        ->orWhereHas('speaker', fn (Builder $speaker): Builder => $speaker
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search));
                });
            })
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->platform !== '', fn (Builder $query): Builder => $query->where('media_platform', $this->platform))
            ->when($this->mediaType !== '', fn (Builder $query): Builder => $query->where('media_type', $this->mediaType))
            ->when($this->speaker !== '', fn (Builder $query): Builder => $query->where('speaker_id', $this->speaker))
            ->when($this->featured !== '', fn (Builder $query): Builder => $query->where('is_featured', $this->featured === '1'))
            ->when($this->createdBy !== '', fn (Builder $query): Builder => $query->where('created_by', $this->createdBy))
            ->when($this->sermonFrom !== '', fn (Builder $query): Builder => $query->whereDate('sermon_date', '>=', $this->sermonFrom))
            ->when($this->sermonTo !== '', fn (Builder $query): Builder => $query->whereDate('sermon_date', '<=', $this->sermonTo))
            ->when($this->publishedFrom !== '', fn (Builder $query): Builder => $query->whereDate('published_at', '>=', $this->publishedFrom))
            ->when($this->publishedTo !== '', fn (Builder $query): Builder => $query->whereDate('published_at', '<=', $this->publishedTo))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function filterOptions(): array
    {
        return [
            'speakers' => Person::query()->whereHas('sermons')->orderBy('first_name')->get(['id', 'title', 'first_name', 'middle_name', 'last_name']),
            'creators' => User::query()->whereHas('createdSermons')->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function confirm(int $sermonId, string $action): void
    {
        $sermon = Sermon::withTrashed()->findOrFail($sermonId);
        $ability = match ($action) {
            'delete' => 'delete',
            'restore' => 'restore',
            'forceDelete' => 'forceDelete',
            'duplicate' => 'duplicate',
            'publish' => 'publish',
            'unpublish' => 'unpublish',
            'archive' => 'archive',
            'feature' => 'feature',
            default => abort(404),
        };
        Gate::authorize($ability, $sermon);
        $this->targetSermonId = $sermonId;
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(
        DeleteSermon $deleteSermon,
        DuplicateSermon $duplicateSermon,
        ChangeSermonStatus $changeStatus,
    ): void {
        $sermon = Sermon::withTrashed()->findOrFail($this->targetSermonId);
        $actor = Auth::user();

        match ($this->pendingAction) {
            'delete' => $deleteSermon->delete($actor, $sermon),
            'restore' => $deleteSermon->restore($actor, $sermon),
            'forceDelete' => $deleteSermon->forceDelete($actor, $sermon),
            'duplicate' => $duplicateSermon->handle($actor, $sermon),
            'publish' => $changeStatus->publish($actor, $sermon),
            'unpublish' => $changeStatus->unpublish($actor, $sermon),
            'archive' => $changeStatus->archive($actor, $sermon),
            'feature' => $changeStatus->feature($actor, $sermon, ! $sermon->is_featured),
            default => abort(404),
        };

        $this->reset(['showConfirmModal', 'targetSermonId', 'pendingAction']);
        unset($this->sermons);
        Flux::toast(variant: 'success', text: __('Sermon action completed.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Sermons') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Sermons')" :description="__('Create, organize, schedule, and publish externally hosted sermon media.')" :eyebrow="__('Content management')">
        <x-slot:actions>
            @can('create', Sermon::class)
                <flux:button :href="route('sermons.create')" variant="primary" icon="plus" wire:navigate>{{ __('Add sermon') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card :label="__('Total sermons')" :value="Sermon::withTrashed()->count()" :caption="__('Including archived and deleted records')" icon="play-circle" />
        <x-admin.stat-card :label="__('Published')" :value="Sermon::query()->publiclyAvailable()->count()" :caption="__('Currently visible on the public website')" icon="globe-alt" />
        <x-admin.stat-card :label="__('Drafts')" :value="Sermon::query()->where('status', SermonStatus::Draft)->count()" :caption="__('Not visible publicly')" icon="pencil-square" />
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-100 p-4 sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search sermons')" :placeholder="__('Title, speaker, scripture, or summary')" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (SermonStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="speaker" :label="__('Speaker')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($this->filterOptions['speakers'] as $item)<flux:select.option :value="$item->id">{{ $item->full_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="platform" :label="__('Platform')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (SermonMediaPlatform::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="mediaType" :label="__('Media type')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (SermonMediaType::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="featured" :label="__('Featured')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="1">{{ __('Featured') }}</flux:select.option><flux:select.option value="0">{{ __('Not featured') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="createdBy" :label="__('Created by')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($this->filterOptions['creators'] as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model.live="sermonFrom" :label="__('Sermon from')" type="date" />
                <flux:input wire:model.live="sermonTo" :label="__('Sermon to')" type="date" />
                <flux:select wire:model.live="sort" :label="__('Sort by')"><flux:select.option value="sermon_date">{{ __('Sermon date') }}</flux:select.option><flux:select.option value="published_at">{{ __('Published date') }}</flux:select.option><flux:select.option value="title">{{ __('Title') }}</flux:select.option><flux:select.option value="created_at">{{ __('Created') }}</flux:select.option><flux:select.option value="updated_at">{{ __('Updated') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="direction" :label="__('Direction')"><flux:select.option value="desc">{{ __('Descending') }}</flux:select.option><flux:select.option value="asc">{{ __('Ascending') }}</flux:select.option></flux:select>
            </div>
        </div>

        <div class="relative p-4 sm:p-6">
            <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 backdrop-blur-sm dark:bg-zinc-900/75"><flux:icon.arrow-path class="size-5 animate-spin" /></div>
            @if ($this->sermons->isEmpty())
                <x-admin.empty-state icon="play-circle" :title="__('No sermons found')" :description="__('Adjust the filters or create the first sermon.')" />
            @else
                <div class="overflow-x-auto">
                    <flux:table :paginate="$this->sermons">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Sermon') }}</flux:table.column>
                            <flux:table.column>{{ __('Speaker') }}</flux:table.column>
                            <flux:table.column>{{ __('Platform') }}</flux:table.column>
                            <flux:table.column>{{ __('Date') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="hidden xl:table-cell">{{ __('Created by') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->sermons as $sermon)
                                <flux:table.row :key="$sermon->id" wire:key="sermon-{{ $sermon->id }}">
                                    <flux:table.cell>
                                        <div class="flex min-w-64 items-center gap-3">
                                            <div class="h-12 w-20 overflow-hidden rounded-lg bg-slate-900">
                                                @if ($sermon->thumbnailUrl())<img src="{{ $sermon->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">@endif
                                            </div>
                                            <div><p class="font-semibold text-slate-950 dark:text-white">{{ $sermon->title }}</p>@if ($sermon->is_featured)<flux:badge size="sm" color="amber">{{ __('Featured') }}</flux:badge>@endif</div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $sermon->speaker->full_name }}</flux:table.cell>
                                    <flux:table.cell>{{ $sermon->media_platform->label() }}</flux:table.cell>
                                    <flux:table.cell>{{ $sermon->sermon_date->format('M j, Y') }}</flux:table.cell>
                                    <flux:table.cell><flux:badge :color="$sermon->deleted_at ? 'red' : ($sermon->status === SermonStatus::Published ? 'green' : ($sermon->status === SermonStatus::Scheduled ? 'blue' : 'zinc'))">{{ $sermon->deleted_at ? __('Deleted') : $sermon->status->label() }}</flux:badge></flux:table.cell>
                                    <flux:table.cell class="hidden xl:table-cell">{{ $sermon->creator?->name ?? __('Unknown') }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :title', ['title' => $sermon->title])" />
                                            <flux:menu>
                                                @if (! $sermon->deleted_at)
                                                    <flux:menu.item :href="route('sermons.show', $sermon)" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                                                    @can('update', $sermon)<flux:menu.item :href="route('sermons.edit', $sermon)" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:menu.item>@endcan
                                                    @can('duplicate', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'duplicate')" icon="document-duplicate">{{ __('Duplicate') }}</flux:menu.item>@endcan
                                                    @can('feature', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'feature')" icon="star">{{ $sermon->is_featured ? __('Unfeature') : __('Feature') }}</flux:menu.item>@endcan
                                                    @if ($sermon->status !== SermonStatus::Published) @can('publish', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'publish')" icon="globe-alt">{{ __('Publish') }}</flux:menu.item>@endcan @endif
                                                    @if ($sermon->status === SermonStatus::Published) @can('unpublish', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'unpublish')" icon="eye-slash">{{ __('Unpublish') }}</flux:menu.item>@endcan @endif
                                                    @can('archive', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'archive')" icon="archive-box">{{ __('Archive') }}</flux:menu.item>@endcan
                                                    @can('delete', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'delete')" icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>@endcan
                                                @else
                                                    @can('restore', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'restore')" icon="arrow-uturn-left">{{ __('Restore') }}</flux:menu.item>@endcan
                                                    @can('forceDelete', $sermon)<flux:menu.item wire:click="confirm({{ $sermon->id }}, 'forceDelete')" icon="trash" variant="danger">{{ __('Delete permanently') }}</flux:menu.item>@endcan
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </section>

    <flux:modal wire:model="showConfirmModal" class="max-w-lg">
        <form wire:submit="executeConfirmed" class="space-y-6">
            <div><flux:heading size="lg">{{ __('Confirm sermon action') }}</flux:heading><flux:text class="mt-2">{{ __('This will perform the selected action. Publication and deletion changes are recorded in the activity log.') }}</flux:text></div>
            <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Confirm') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
