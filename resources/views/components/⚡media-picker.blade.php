<?php

use App\Media\MediaFileService;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Modelable] public array $mediaIds = [];
    public array $allowedTypes = [];
    public bool $multiple = false;
    public int $maximum = 1;
    public string $collection = 'default';
    public bool $allowUpload = true;
    public bool $allowPrivate = false;
    public bool $open = false;
    public string $search = '';
    public string $folder = '';
    public array $pending = [];
    public $upload = null;

    public function mount(
        array $allowedTypes = [],
        bool $multiple = false,
        int $maximum = 1,
        string $collection = 'default',
        bool $allowUpload = true,
        bool $allowPrivate = false,
    ): void {
        $this->allowedTypes = collect($allowedTypes)
            ->map(fn ($type): string => $type instanceof MediaType ? $type->value : (string) $type)
            ->filter(fn (string $type): bool => MediaType::tryFrom($type) !== null)
            ->values()->all();
        $this->multiple = $multiple;
        $this->maximum = max(1, $multiple ? $maximum : 1);
        $this->collection = $collection;
        $this->allowUpload = $allowUpload;
        $this->allowPrivate = $allowPrivate;
        $this->pending = array_values($this->mediaIds);
    }

    #[Computed]
    public function assets()
    {
        return Media::query()
            ->active()
            ->when(! $this->allowPrivate, fn (Builder $query): Builder => $query->public())
            ->when($this->allowedTypes !== [], fn (Builder $query): Builder => $query->whereIn('media_type', $this->allowedTypes))
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->folder === 'root', fn (Builder $query): Builder => $query->whereNull('media_folder_id'))
            ->when(ctype_digit($this->folder), fn (Builder $query): Builder => $query->where('media_folder_id', (int) $this->folder))
            ->latest()
            ->limit(24)
            ->get(['id', 'name', 'path', 'disk', 'media_type', 'visibility', 'extension', 'alt_text']);
    }

    #[Computed]
    public function folders()
    {
        return MediaFolder::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function selectedAssets()
    {
        return Media::query()->whereKey($this->mediaIds)->get();
    }

    public function show(): void
    {
        Gate::authorize('viewAny', Media::class);
        $this->pending = array_values($this->mediaIds);
        $this->open = true;
    }

    public function toggle(int $mediaId): void
    {
        $media = Media::findOrFail($mediaId);
        Gate::authorize('view', $media);
        abort_if($this->allowedTypes !== [] && ! in_array($media->media_type->value, $this->allowedTypes, true), 422);
        abort_if(! $this->allowPrivate && $media->visibility === MediaVisibility::Private, 403);

        if (in_array($mediaId, $this->pending, true)) {
            $this->pending = array_values(array_diff($this->pending, [$mediaId]));

            return;
        }

        $this->pending = $this->multiple
            ? array_values([...$this->pending, $mediaId])
            : [$mediaId];

        if (count($this->pending) > $this->maximum) {
            array_shift($this->pending);
            Flux::toast(variant: 'warning', text: __('The oldest selection was removed because the selection limit is :count.', ['count' => $this->maximum]));
        }
    }

    public function confirm(): void
    {
        $this->mediaIds = array_slice(array_values(array_unique($this->pending)), 0, $this->maximum);
        $this->dispatch('media-selected', ids: $this->mediaIds, collection: $this->collection);
        $this->open = false;
    }

    public function remove(int $mediaId): void
    {
        $this->mediaIds = array_values(array_diff($this->mediaIds, [$mediaId]));
        $this->pending = $this->mediaIds;
        $this->dispatch('media-selected', ids: $this->mediaIds, collection: $this->collection);
    }

    public function uploadNew(MediaFileService $files): void
    {
        abort_unless($this->allowUpload, 403);
        Gate::authorize('create', Media::class);
        $this->validate(['upload' => ['required', 'file', 'max:'.$files->maximumKilobytes()]]);
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $folder = ctype_digit($this->folder) ? MediaFolder::findOrFail((int) $this->folder) : null;
        $media = $files->store($this->upload, $actor, $folder);
        $this->reset('upload');
        unset($this->assets);
        $this->toggle($media->id);
        Flux::toast(variant: 'success', text: __('Asset uploaded and selected.'));
    }
};
?>

<div class="space-y-3">
    @if($this->selectedAssets->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach($this->selectedAssets as $media)
                <div wire:key="selected-media-{{ $media->id }}" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                    <x-media.thumbnail :media="$media" size="sm" />
                    <p class="min-w-0 flex-1 truncate text-sm font-medium">{{ $media->name }}</p>
                    <flux:button type="button" size="sm" variant="ghost" icon="x-mark" wire:click="remove({{ $media->id }})" :aria-label="__('Remove :name', ['name' => $media->name])" />
                </div>
            @endforeach
        </div>
    @endif
    <flux:button type="button" wire:click="show" icon="photo">{{ $mediaIds === [] ? __('Choose media') : __('Change media') }}</flux:button>

    <flux:modal wire:model="open" class="max-w-6xl">
        <div class="space-y-5">
            <div><flux:heading size="lg">{{ __('Choose media') }}</flux:heading><flux:text class="mt-1">{{ __('Select up to :count compatible assets.', ['count' => $maximum]) }}</flux:text></div>
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_15rem]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" />
                <flux:select wire:model.live="folder" :label="__('Folder')"><flux:select.option value="">{{ __('All folders') }}</flux:select.option><flux:select.option value="root">{{ __('Root') }}</flux:select.option>@foreach($this->folders as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
            </div>
            @if($allowUpload && Auth::user()?->can('create', Media::class))
                <div class="flex flex-col gap-3 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-end dark:border-zinc-700">
                    <label class="min-w-0 flex-1 text-sm font-medium">{{ __('Upload a new asset') }}<input type="file" wire:model="upload" class="mt-2 block w-full text-sm"></label>
                    <flux:button type="button" wire:click="uploadNew" wire:loading.attr="disabled" wire:target="uploadNew">
                        <span wire:loading.remove wire:target="uploadNew">{{ __('Upload') }}</span>
                        <span wire:loading wire:target="uploadNew">{{ __('Uploading...') }}</span>
                    </flux:button>
                </div>
            @endif
            <div class="grid max-h-[55vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                @forelse($this->assets as $media)
                    <button type="button" wire:key="picker-media-{{ $media->id }}" wire:click="toggle({{ $media->id }})" class="overflow-hidden rounded-xl border text-left focus:outline-none focus:ring-2 focus:ring-church-maroon-700 {{ in_array($media->id, $pending, true) ? 'border-church-gold-500 ring-2 ring-church-gold-400' : 'border-slate-200 dark:border-zinc-700' }}">
                        <x-media.thumbnail :media="$media" size="lg" class="rounded-none" />
                        <span class="block truncate p-3 text-sm font-medium">{{ $media->name }}</span>
                    </button>
                @empty
                    <div class="col-span-full"><x-admin.empty-state icon="photo" :title="__('No compatible media')" :description="__('Change the search or folder filter, or upload a compatible file.')" /></div>
                @endforelse
            </div>
            <div class="flex items-center justify-between gap-3"><flux:text>{{ trans_choice(':count selected|:count selected', count($pending), ['count' => count($pending)]) }}</flux:text><div class="flex gap-3"><flux:button type="button" variant="ghost" wire:click="$set('open', false)">{{ __('Cancel') }}</flux:button><flux:button type="button" variant="primary" wire:click="confirm">{{ __('Use selected media') }}</flux:button></div></div>
        </div>
    </flux:modal>
</div>
