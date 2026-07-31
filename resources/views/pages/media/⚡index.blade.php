<?php

use App\Actions\Media\ManageMedia;
use App\Actions\Media\ManageMediaFolder;
use App\Media\MediaFileService;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Media Library')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $type = '';
    #[Url] public string $folder = '';
    #[Url] public string $uploader = '';
    #[Url] public string $visibility = '';
    #[Url] public string $status = '';
    #[Url] public string $extension = '';
    #[Url] public string $date = '';
    #[Url] public string $sort = 'newest';
    #[Url] public int $perPage = 24;
    public bool $trash = false;
    public string $view = 'grid';
    public array $files = [];
    public array $selected = [];
    public bool $showUploadModal = false;
    public bool $showFolderModal = false;
    public bool $showDetailModal = false;
    public bool $showConfirmModal = false;
    public bool $showBulkModal = false;
    public ?int $folderId = null;
    public ?int $mediaId = null;
    public string $folderName = '';
    public string $folderDescription = '';
    public string $folderParent = '';
    public string $pendingAction = '';
    public string $bulkFolder = '';
    public string $bulkVisibility = 'public';
    public string $name = '';
    public string $altText = '';
    public string $caption = '';
    public string $description = '';
    public string $credit = '';
    public string $copyright = '';
    public string $sourceUrl = '';
    public string $editFolder = '';
    public string $editVisibility = 'public';
    public string $editStatus = 'active';
    public bool $isFeatured = false;
    public $replacementFile = null;

    public function mount(bool $trash = false): void
    {
        Gate::authorize('viewAny', Media::class);
        $this->trash = $trash;
        $this->view = session('media.view', 'grid');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'folder', 'uploader', 'visibility', 'status', 'extension', 'date', 'sort', 'perPage'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['grid', 'list'], true) ? $view : 'grid';
        session(['media.view' => $this->view]);
    }

    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        [$sort, $direction] = match ($this->sort) {
            'oldest' => ['created_at', 'asc'],
            'alphabetical' => ['name', 'asc'],
            'largest' => ['size', 'desc'],
            'smallest' => ['size', 'asc'],
            default => ['created_at', 'desc'],
        };

        return Media::query()
            ->when($this->trash, fn (Builder $query): Builder => $query->onlyTrashed())
            ->when(! $this->trash, fn (Builder $query): Builder => $query->whereNull('deleted_at'))
            ->select([
                'id', 'media_folder_id', 'uploaded_by', 'name', 'original_name', 'disk', 'path',
                'mime_type', 'extension', 'media_type', 'size', 'width', 'height', 'duration',
                'alt_text', 'visibility', 'status', 'is_featured', 'created_at', 'deleted_at',
            ])
            ->with(['folder:id,name,parent_id', 'uploader:id,name'])
            ->when(! Auth::user()?->can(\App\PermissionName::MediaManagePrivate), fn (Builder $query): Builder => $query->public())
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->type !== '', fn (Builder $query): Builder => $query->where('media_type', $this->type))
            ->when($this->folder === 'root', fn (Builder $query): Builder => $query->whereNull('media_folder_id'))
            ->when(ctype_digit($this->folder), fn (Builder $query): Builder => $query->where('media_folder_id', (int) $this->folder))
            ->when($this->uploader !== '', fn (Builder $query): Builder => $query->where('uploaded_by', $this->uploader))
            ->when($this->visibility !== '', fn (Builder $query): Builder => $query->where('visibility', $this->visibility))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->extension !== '', fn (Builder $query): Builder => $query->where('extension', $this->extension))
            ->when($this->date !== '', fn (Builder $query): Builder => $query->whereDate('created_at', $this->date))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(in_array($this->perPage, [24, 48, 96], true) ? $this->perPage : 24);
    }

    #[Computed]
    public function stats(): array
    {
        $stats = Media::query()->selectRaw(
            "COUNT(*) as total,
            COALESCE(SUM(size), 0) as bytes,
            SUM(CASE WHEN media_type = 'image' THEN 1 ELSE 0 END) as images,
            SUM(CASE WHEN media_type = 'video' THEN 1 ELSE 0 END) as videos,
            SUM(CASE WHEN media_type = 'audio' THEN 1 ELSE 0 END) as audio,
            SUM(CASE WHEN media_type IN ('document', 'spreadsheet', 'presentation') THEN 1 ELSE 0 END) as documents"
        )->first();

        return [
            'total' => (int) $stats?->getAttribute('total'),
            'bytes' => (int) $stats?->getAttribute('bytes'),
            'images' => (int) $stats?->getAttribute('images'),
            'videos' => (int) $stats?->getAttribute('videos'),
            'audio' => (int) $stats?->getAttribute('audio'),
            'documents' => (int) $stats?->getAttribute('documents'),
        ];
    }

    #[Computed]
    public function folders()
    {
        return MediaFolder::query()->withCount(['children', 'media'])->orderBy('name')->get(['id', 'parent_id', 'name']);
    }

    #[Computed]
    public function uploaders()
    {
        return User::query()->whereHas('uploadedMedia')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function extensions()
    {
        return Media::query()->select('extension')->distinct()->orderBy('extension')->pluck('extension');
    }

    #[Computed]
    public function detail(): ?Media
    {
        return $this->mediaId === null
            ? null
            : Media::withTrashed()->with(['folder:id,name', 'uploader:id,name'])->withCount('usages')->find($this->mediaId);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'folder', 'uploader', 'visibility', 'status', 'extension', 'date']);
        $this->resetPage();
    }

    public function removePendingFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
        $this->resetValidation();
    }

    public function closeUploadModal(): void
    {
        $this->reset('files');
        $this->resetValidation();
        $this->showUploadModal = false;
    }

    public function saveMedia(MediaFileService $fileService): void
    {
        Gate::authorize('create', Media::class);
        $this->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.$fileService->maximumKilobytes()],
            'folder' => ['nullable'],
        ]);

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $rateLimitKey = 'media-upload:'.$actor->getKey();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            throw ValidationException::withMessages([
                'files' => __('Too many upload attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($rateLimitKey),
                ]),
            ]);
        }
        RateLimiter::hit($rateLimitKey, 60);
        $folder = ctype_digit($this->folder) ? MediaFolder::findOrFail((int) $this->folder) : null;
        $uploaded = 0;
        $failed = [];

        foreach ($this->files as $index => $file) {
            try {
                $fileService->store($file, $actor, $folder);
                $uploaded++;
            } catch (ValidationException $exception) {
                $this->addError("files.{$index}", collect($exception->errors())->flatten()->first() ?? __('The file is invalid.'));
                $failed[] = $file;
            } catch (\Throwable $exception) {
                report($exception);
                $this->addError("files.{$index}", __('The file could not be stored. Try again or contact an administrator.'));
                $failed[] = $file;
            }
        }

        $this->files = $failed;
        $this->showUploadModal = $failed !== [];
        if ($failed === []) {
            $this->resetValidation();
        }
        unset($this->assets, $this->stats, $this->extensions);
        Flux::toast(
            variant: $failed === [] ? 'success' : 'warning',
            text: __(':uploaded uploaded; :failed failed.', ['uploaded' => $uploaded, 'failed' => count($failed)]),
        );
    }

    public function createFolder(): void
    {
        Gate::authorize('create', MediaFolder::class);
        $this->resetFolderForm();
        $this->folderParent = ctype_digit($this->folder) ? $this->folder : '';
        $this->showFolderModal = true;
    }

    public function editFolder(int $folderId): void
    {
        $folder = MediaFolder::findOrFail($folderId);
        Gate::authorize('update', $folder);
        $this->folderId = $folder->id;
        $this->folderName = $folder->name;
        $this->folderDescription = $folder->description ?? '';
        $this->folderParent = (string) ($folder->parent_id ?? '');
        $this->showFolderModal = true;
    }

    public function saveFolder(ManageMediaFolder $manager): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $folder = $this->folderId === null ? null : MediaFolder::findOrFail($this->folderId);
        $manager->save($actor, [
            'name' => $this->folderName,
            'description' => $this->folderDescription ?: null,
            'parent_id' => $this->folderParent ?: null,
        ], $folder);
        $this->showFolderModal = false;
        $this->resetFolderForm();
        unset($this->folders);
        Flux::toast(variant: 'success', text: __('Folder saved successfully.'));
    }

    public function confirmDeleteFolder(int $folderId): void
    {
        $folder = MediaFolder::findOrFail($folderId);
        Gate::authorize('delete', $folder);
        $this->folderId = $folderId;
        $this->pendingAction = 'delete-folder';
        $this->showConfirmModal = true;
    }

    public function openDetail(int $mediaId): void
    {
        $media = Media::withTrashed()->findOrFail($mediaId);
        Gate::authorize('view', $media);
        $this->mediaId = $media->id;
        $this->name = $media->name;
        $this->altText = $media->alt_text ?? '';
        $this->caption = $media->caption ?? '';
        $this->description = $media->description ?? '';
        $this->credit = $media->credit ?? '';
        $this->copyright = $media->copyright ?? '';
        $this->sourceUrl = $media->source_url ?? '';
        $this->editFolder = (string) ($media->media_folder_id ?? '');
        $this->editVisibility = $media->visibility->value;
        $this->editStatus = $media->status->value;
        $this->isFeatured = $media->is_featured;
        $this->showDetailModal = true;
    }

    public function saveMetadata(ManageMedia $manager): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $media = Media::findOrFail($this->mediaId);
        $manager->update($actor, $media, [
            'name' => $this->name,
            'alt_text' => $this->altText ?: null,
            'caption' => $this->caption ?: null,
            'description' => $this->description ?: null,
            'credit' => $this->credit ?: null,
            'copyright' => $this->copyright ?: null,
            'source_url' => $this->sourceUrl ?: null,
            'media_folder_id' => $this->editFolder ?: null,
            'visibility' => $this->editVisibility,
            'status' => $this->editStatus,
            'is_featured' => $this->isFeatured,
        ]);
        unset($this->assets, $this->detail, $this->stats);
        Flux::toast(variant: 'success', text: __('Media details updated.'));
    }

    public function replaceFile(MediaFileService $fileService): void
    {
        $media = Media::findOrFail($this->mediaId);
        Gate::authorize('update', $media);
        $this->validate(['replacementFile' => ['required', 'file', 'max:'.$fileService->maximumKilobytes()]]);
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $fileService->replace($media, $this->replacementFile, $actor);
        $this->reset('replacementFile');
        unset($this->assets, $this->detail, $this->stats, $this->extensions);
        Flux::toast(variant: 'success', text: __('File replaced. Existing uses now reference the new file.'));
    }

    public function confirmMedia(int $mediaId, string $action): void
    {
        $media = Media::withTrashed()->findOrFail($mediaId);
        Gate::authorize(match ($action) {
            'delete' => 'delete',
            'restore' => 'restore',
            'force-delete' => 'forceDelete',
            default => 'update',
        }, $media);
        $this->mediaId = $mediaId;
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(ManageMedia $manager, ManageMediaFolder $folderManager): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        if ($this->pendingAction === 'delete-folder') {
            $folderManager->delete($actor, MediaFolder::findOrFail($this->folderId));
            $this->resetFolderForm();
            unset($this->folders);
        } else {
            $media = Media::withTrashed()->findOrFail($this->mediaId);
            match ($this->pendingAction) {
                'archive' => $manager->archive($actor, $media),
                'activate' => $manager->restoreArchive($actor, $media),
                'delete' => $manager->delete($actor, $media),
                'restore' => $manager->restore($actor, $media),
                'force-delete' => $manager->forceDelete($actor, $media),
                default => abort(404),
            };
        }

        $this->reset(['showConfirmModal', 'pendingAction', 'mediaId']);
        unset($this->assets, $this->detail, $this->stats);
        Flux::toast(variant: 'success', text: __('Action completed successfully.'));
    }

    public function openBulk(string $action): void
    {
        if ($this->selected === []) {
            $this->addError('selected', __('Select at least one asset.'));

            return;
        }

        $this->pendingAction = $action;
        $this->showBulkModal = true;
    }

    public function executeBulk(ManageMedia $manager): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $ids = collect($this->selected)->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->values();
        $affected = 0;

        Media::query()->withTrashed()->whereKey($ids)->chunkById(100, function ($assets) use ($actor, $manager, &$affected): void {
            foreach ($assets as $media) {
                match ($this->pendingAction) {
                    'move' => $manager->update($actor, $media, [
                        'name' => $media->name, 'media_folder_id' => $this->bulkFolder ?: null,
                        'visibility' => $media->visibility->value, 'status' => $media->status->value,
                        'is_featured' => $media->is_featured,
                    ]),
                    'visibility' => $manager->update($actor, $media, [
                        'name' => $media->name, 'media_folder_id' => $media->media_folder_id,
                        'visibility' => $this->bulkVisibility, 'status' => $media->status->value,
                        'is_featured' => $media->is_featured,
                    ]),
                    'archive' => $manager->archive($actor, $media),
                    'restore' => $manager->restore($actor, $media),
                    'delete' => $manager->delete($actor, $media),
                    'force-delete' => $manager->forceDelete($actor, $media),
                    default => abort(404),
                };
                $affected++;
            }
        });

        $this->reset(['selected', 'showBulkModal', 'pendingAction', 'bulkFolder']);
        unset($this->assets, $this->stats);
        Flux::toast(variant: 'success', text: __(':count assets updated.', ['count' => $affected]));
    }

    private function resetFolderForm(): void
    {
        $this->reset(['folderId', 'folderName', 'folderDescription', 'folderParent']);
        $this->resetValidation();
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $trash ? __('Media trash') : __('Media Library') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="$trash ? __('Media trash') : __('Media Library')"
        :description="$trash ? __('Restore assets or permanently remove files that are no longer in use.') : __('Upload, organize, search, preview, and reuse media and website assets.')"
        :eyebrow="__('Content management')"
    >
        <x-slot:actions>
            <flux:button :href="$trash ? route('media.index') : route('media.trash')" :icon="$trash ? 'photo' : 'trash'" wire:navigate>
                {{ $trash ? __('Back to library') : __('Trash') }}
            </flux:button>
            @unless ($trash)
                @can('create', MediaFolder::class)
                    <flux:button wire:click="createFolder" icon="folder-plus">{{ __('Create folder') }}</flux:button>
                @endcan
                @can('create', Media::class)
                    <flux:button wire:click="$set('showUploadModal', true)" variant="primary" icon="arrow-up-tray">{{ __('Upload media') }}</flux:button>
                @endcan
            @endunless
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <x-admin.stat-card :label="__('Assets')" :value="$this->stats['total']" :caption="__('Database records')" icon="rectangle-stack" />
        <x-admin.stat-card :label="__('Images')" :value="$this->stats['images']" :caption="__('Raster images')" icon="photo" tone="gold" />
        <x-admin.stat-card :label="__('Videos')" :value="$this->stats['videos']" :caption="__('Stored video')" icon="video-camera" tone="purple" />
        <x-admin.stat-card :label="__('Audio')" :value="$this->stats['audio']" :caption="__('Audio files')" icon="musical-note" tone="green" />
        <x-admin.stat-card :label="__('Documents')" :value="$this->stats['documents']" :caption="__('Docs and sheets')" icon="document" tone="blue" />
        <x-admin.stat-card :label="__('Storage')" :value="Number::fileSize($this->stats['bytes'])" :caption="__('From stored size metadata')" icon="circle-stack" tone="slate" />
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-5 border-b border-slate-100 p-4 lg:grid-cols-[16rem_minmax(0,1fr)] sm:p-6 dark:border-zinc-800">
            <aside class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Folders') }}</p>
                    <flux:button size="sm" variant="ghost" wire:click="$set('folder', 'root')" icon="home" :aria-label="__('Root media')" />
                </div>
                <button type="button" wire:click="$set('folder', '')" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm {{ $folder === '' ? 'bg-church-gold-100 font-semibold text-church-maroon-900' : 'hover:bg-slate-50 dark:hover:bg-zinc-800' }}">
                    <span>{{ __('All media') }}</span>
                </button>
                <button type="button" wire:click="$set('folder', 'root')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm {{ $folder === 'root' ? 'bg-church-gold-100 font-semibold text-church-maroon-900' : 'hover:bg-slate-50 dark:hover:bg-zinc-800' }}">
                    <flux:icon.folder class="size-4" /> {{ __('Root') }}
                </button>
                <div class="max-h-56 space-y-1 overflow-y-auto pe-1">
                    @foreach ($this->folders as $item)
                        <div wire:key="folder-{{ $item->id }}" class="group flex items-center gap-1">
                            <button type="button" wire:click="$set('folder', '{{ $item->id }}')" class="flex min-w-0 flex-1 items-center justify-between rounded-lg px-3 py-2 text-left text-sm {{ $folder === (string) $item->id ? 'bg-church-gold-100 font-semibold text-church-maroon-900' : 'hover:bg-slate-50 dark:hover:bg-zinc-800' }}">
                                <span class="truncate">{{ $item->name }}</span>
                                <span class="text-xs text-slate-400">{{ $item->media_count }}</span>
                            </button>
                            @can('update', $item)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editFolder({{ $item->id }})" :aria-label="__('Edit :folder', ['folder' => $item->name])" />
                            @endcan
                            @can('delete', $item)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDeleteFolder({{ $item->id }})" :aria-label="__('Delete :folder', ['folder' => $item->name])" />
                            @endcan
                        </div>
                    @endforeach
                </div>
            </aside>

            <div class="space-y-4">
                <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search media')" :placeholder="__('Name, original file name, alt text, caption, or description')" />
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <flux:select wire:model.live="type" :label="__('Type')"><flux:select.option value="">{{ __('All types') }}</flux:select.option>@foreach(MediaType::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model.live="uploader" :label="__('Uploader')"><flux:select.option value="">{{ __('All uploaders') }}</flux:select.option>@foreach($this->uploaders as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model.live="visibility" :label="__('Visibility')"><flux:select.option value="">{{ __('Any visibility') }}</flux:select.option>@foreach(MediaVisibility::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('Any status') }}</flux:select.option>@foreach(MediaStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                    <flux:select wire:model.live="extension" :label="__('Extension')"><flux:select.option value="">{{ __('All extensions') }}</flux:select.option>@foreach($this->extensions as $item)<flux:select.option :value="$item">.{{ $item }}</flux:select.option>@endforeach</flux:select>
                    <flux:input type="date" wire:model.live="date" :label="__('Upload date')" />
                    <flux:select wire:model.live="sort" :label="__('Sort')"><flux:select.option value="newest">{{ __('Newest') }}</flux:select.option><flux:select.option value="oldest">{{ __('Oldest') }}</flux:select.option><flux:select.option value="alphabetical">{{ __('Alphabetical') }}</flux:select.option><flux:select.option value="largest">{{ __('Largest') }}</flux:select.option><flux:select.option value="smallest">{{ __('Smallest') }}</flux:select.option></flux:select>
                    <flux:select wire:model.live="perPage" :label="__('Per page')"><flux:select.option value="24">24</flux:select.option><flux:select.option value="48">48</flux:select.option><flux:select.option value="96">96</flux:select.option></flux:select>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Reset filters') }}</flux:button>
                    <div class="flex rounded-lg border border-slate-200 p-1 dark:border-zinc-700">
                        <flux:button size="sm" :variant="$view === 'grid' ? 'primary' : 'ghost'" icon="squares-2x2" wire:click="setView('grid')" :aria-label="__('Grid view')" />
                        <flux:button size="sm" :variant="$view === 'list' ? 'primary' : 'ghost'" icon="list-bullet" wire:click="setView('list')" :aria-label="__('List view')" />
                    </div>
                </div>
            </div>
        </div>

        @if ($selected !== [])
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-church-gold-50 px-4 py-3 sm:px-6 dark:border-zinc-800 dark:bg-church-gold-400/10">
                <flux:badge color="amber">{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</flux:badge>
                @unless($trash)
                    <flux:button size="sm" wire:click="openBulk('move')">{{ __('Move') }}</flux:button>
                    <flux:button size="sm" wire:click="openBulk('visibility')">{{ __('Visibility') }}</flux:button>
                    <flux:button size="sm" wire:click="openBulk('archive')">{{ __('Archive') }}</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="openBulk('delete')">{{ __('Delete') }}</flux:button>
                @else
                    <flux:button size="sm" wire:click="openBulk('restore')">{{ __('Restore') }}</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="openBulk('force-delete')">{{ __('Delete forever') }}</flux:button>
                @endunless
                <flux:button size="sm" variant="ghost" wire:click="$set('selected', [])">{{ __('Clear') }}</flux:button>
            </div>
        @endif

        <div class="relative p-4 sm:p-6">
            <div wire:loading.flex wire:target="search,type,folder,uploader,visibility,status,extension,date,sort,perPage" class="absolute inset-0 z-10 items-center justify-center bg-white/80 dark:bg-zinc-900/80"><flux:icon.arrow-path class="size-5 animate-spin" /></div>
            @if ($this->assets->isEmpty())
                <x-admin.empty-state
                    icon="photo"
                    :title="$trash ? __('Trash is empty') : ($search !== '' ? __('No media matched your filters') : __('No media assets yet'))"
                    :description="$trash ? __('Deleted assets will appear here during the retention period.') : __('Upload an asset or reset the filters to get started.')"
                />
            @elseif ($view === 'grid')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($this->assets as $media)
                        <article wire:key="media-card-{{ $media->id }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                            <button type="button" wire:click="openDetail({{ $media->id }})" class="block w-full text-left">
                                <x-media.thumbnail :media="$media" size="lg" class="rounded-none" />
                            </button>
                            <div class="space-y-3 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:checkbox wire:model.live="selected" :value="$media->id" :aria-label="__('Select :name', ['name' => $media->name])" />
                                    <div class="min-w-0 flex-1"><button type="button" wire:click="openDetail({{ $media->id }})" class="block w-full truncate text-left text-sm font-semibold hover:underline" title="{{ $media->name }}">{{ $media->name }}</button><p class="mt-1 text-xs uppercase text-slate-500">.{{ $media->extension }} · {{ Number::fileSize($media->size) }}</p></div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-media.type-badge :media="$media" />
                                    <flux:badge :color="$media->visibility === MediaVisibility::Public ? 'green' : 'zinc'">{{ $media->visibility->label() }}</flux:badge>
                                    @if($media->status === MediaStatus::Archived)<flux:badge color="amber">{{ __('Archived') }}</flux:badge>@endif
                                </div>
                                <div class="flex items-center justify-between gap-3 text-xs text-slate-500"><span>{{ $media->created_at->format('M j, Y') }}</span><flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" wire:click="openDetail({{ $media->id }})" :aria-label="__('Open details for :name', ['name' => $media->name])" /></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns><flux:table.column class="ps-4">{{ __('Select') }}</flux:table.column><flux:table.column>{{ __('Preview') }}</flux:table.column><flux:table.column>{{ __('Name') }}</flux:table.column><flux:table.column>{{ __('Type') }}</flux:table.column><flux:table.column>{{ __('Folder') }}</flux:table.column><flux:table.column>{{ __('Size') }}</flux:table.column><flux:table.column class="hidden lg:table-cell">{{ __('Uploaded by') }}</flux:table.column><flux:table.column class="hidden md:table-cell">{{ __('Visibility') }}</flux:table.column><flux:table.column align="end" class="pe-4">{{ __('Actions') }}</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @foreach($this->assets as $media)
                                <flux:table.row :key="$media->id" wire:key="media-row-{{ $media->id }}">
                                    <flux:table.cell class="ps-4"><flux:checkbox wire:model.live="selected" :value="$media->id" :aria-label="__('Select :name', ['name' => $media->name])" /></flux:table.cell>
                                    <flux:table.cell><x-media.thumbnail :media="$media" size="sm" /></flux:table.cell>
                                    <flux:table.cell><p class="max-w-64 truncate font-semibold" title="{{ $media->name }}">{{ $media->name }}</p><p class="text-xs text-slate-500">.{{ $media->extension }} · {{ $media->created_at->format('M j, Y') }}</p></flux:table.cell>
                                    <flux:table.cell><x-media.type-badge :media="$media" /></flux:table.cell>
                                    <flux:table.cell>{{ $media->folder?->name ?? __('Root') }}</flux:table.cell>
                                    <flux:table.cell>{{ Number::fileSize($media->size) }}</flux:table.cell>
                                    <flux:table.cell class="hidden lg:table-cell">{{ $media->uploader?->name ?? __('Unknown') }}</flux:table.cell>
                                    <flux:table.cell class="hidden md:table-cell"><flux:badge :color="$media->visibility === MediaVisibility::Public ? 'green' : 'zinc'">{{ $media->visibility->label() }}</flux:badge></flux:table.cell>
                                    <flux:table.cell align="end" class="pe-4"><flux:button size="sm" icon="eye" wire:click="openDetail({{ $media->id }})">{{ __('Details') }}</flux:button></flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
            <div class="mt-6"><flux:pagination :paginator="$this->assets" /></div>
        </div>
    </section>

    <flux:modal wire:model="showUploadModal" class="max-w-3xl">
        <form
            wire:submit="saveMedia"
            class="space-y-5"
            x-data="{ temporaryUploading: false, progress: 0, uploadError: null }"
            x-on:livewire-upload-start="temporaryUploading = true; progress = 0; uploadError = null"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            x-on:livewire-upload-finish="temporaryUploading = false; progress = 100"
            x-on:livewire-upload-error="temporaryUploading = false; progress = 0; uploadError = @js(__('The temporary upload failed. Check the file size and try again.'))"
            x-on:livewire-upload-cancel="temporaryUploading = false; progress = 0; uploadError = null"
        >
            <div><flux:heading size="lg">{{ __('Upload media') }}</flux:heading><flux:text class="mt-2">{{ __('Select or drop up to 20 approved files. SVG, scripts, HTML, and executables are not accepted.') }}</flux:text></div>
            <label class="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center focus-within:ring-2 focus-within:ring-church-maroon-700 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:icon.cloud-arrow-up class="size-9 text-church-maroon-700 dark:text-church-gold-400" />
                <span class="mt-3 font-semibold">{{ __('Choose files or drag them here') }}</span>
                <span class="mt-1 text-xs text-slate-500">{{ __('Images 10 MB · documents 25 MB · audio 50 MB · archives 100 MB · video 250 MB') }}</span>
                <input type="file" wire:model="files" multiple class="sr-only" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.mp3,.wav,.m4a,.ogg,.pdf,.doc,.docx,.txt,.rtf,.xls,.xlsx,.csv,.ppt,.pptx,.zip">
            </label>
            <div x-show="temporaryUploading" x-cloak class="w-full" role="status" aria-live="polite">
                <flux:progress x-bind:value="progress" max="100" />
                <p class="mt-2 text-sm text-slate-500">{{ __('Uploading to the secure temporary area…') }} <span x-text="`${progress}%`"></span></p>
            </div>
            <p x-show="uploadError" x-cloak x-text="uploadError" class="text-sm font-medium text-red-600 dark:text-red-400" role="alert"></p>
            <flux:error name="files" />
            @foreach($files as $index => $file)
                <div wire:key="pending-file-{{ $index }}" class="rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0"><p class="truncate text-sm font-medium">{{ $file->getClientOriginalName() }}</p><p class="text-xs text-slate-500">{{ Number::fileSize($file->getSize()) }}</p></div>
                        <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removePendingFile({{ $index }})" :aria-label="__('Remove pending file')" />
                    </div>
                    <flux:error name="files.{{ $index }}" />
                </div>
            @endforeach
            <div class="flex justify-end gap-3">
                <flux:button
                    type="button"
                    variant="ghost"
                    wire:click="closeUploadModal"
                    x-on:click="$wire.$cancelUpload('files'); temporaryUploading = false; progress = 0; uploadError = null"
                >{{ __('Cancel') }}</flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                    :loading="false"
                    wire:target="saveMedia"
                    wire:loading.attr="disabled"
                    x-bind:disabled="temporaryUploading || {{ $files === [] ? 'true' : 'false' }}"
                >
                    <span wire:loading.remove wire:target="saveMedia">{{ __('Upload files') }}</span>
                    <span wire:loading wire:target="saveMedia">{{ __('Saving and processing…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showFolderModal" class="max-w-xl">
        <form wire:submit="saveFolder" class="space-y-5">
            <flux:heading size="lg">{{ $folderId ? __('Edit folder') : __('Create folder') }}</flux:heading>
            <flux:input wire:model="folderName" :label="__('Name')" required />
            <flux:textarea wire:model="folderDescription" :label="__('Description')" rows="3" />
            <flux:select wire:model="folderParent" :label="__('Parent folder')"><flux:select.option value="">{{ __('Root') }}</flux:select.option>@foreach($this->folders as $item)@if($item->id !== $folderId)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endif @endforeach</flux:select>
            <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showFolderModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary">{{ __('Save folder') }}</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showDetailModal" class="max-w-5xl">
        @if($this->detail)
            <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <div class="space-y-4">
                    <x-media.thumbnail :media="$this->detail" size="lg" class="h-72" />
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div><dt class="text-slate-500">{{ __('Original name') }}</dt><dd class="break-all font-medium">{{ $this->detail->original_name }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('MIME type') }}</dt><dd class="break-all font-medium">{{ $this->detail->mime_type }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Size') }}</dt><dd class="font-medium">{{ Number::fileSize($this->detail->size) }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Dimensions') }}</dt><dd class="font-medium">{{ $this->detail->width ? $this->detail->width.' × '.$this->detail->height : __('Not available') }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Uploader') }}</dt><dd class="font-medium">{{ $this->detail->uploader?->name ?? __('Unknown') }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Uses') }}</dt><dd class="font-medium">{{ $this->detail->usages_count }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-2">
                        @can('download', $this->detail)<flux:button :href="route('media.download', $this->detail)" icon="arrow-down-tray">{{ __('Download') }}</flux:button>@endcan
                        @if($this->detail->publicUrl())<div x-data="{ copied: false }"><flux:button type="button" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($this->detail->publicUrl())); copied = true"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy URL'))"></span></flux:button></div>@endif
                    </div>
                </div>
                <form wire:submit="saveMetadata" class="space-y-4">
                    <flux:heading size="lg">{{ __('Media details') }}</flux:heading>
                    <flux:input wire:model="name" :label="__('Display name')" required />
                    @if($this->detail->media_type === MediaType::Image)<flux:input wire:model="altText" :label="__('Alt text')" :description="__('Describe meaningful images. Leave blank only when the image is intentionally decorative.')" maxlength="500" />@endif
                    <flux:textarea wire:model="caption" :label="__('Caption')" rows="2" />
                    <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
                    <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="credit" :label="__('Credit')" /><flux:input wire:model="copyright" :label="__('Copyright')" /></div>
                    <flux:input wire:model="sourceUrl" type="url" :label="__('Source URL')" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:select wire:model="editFolder" :label="__('Folder')"><flux:select.option value="">{{ __('Root') }}</flux:select.option>@foreach($this->folders as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="editVisibility" :label="__('Visibility')">@foreach(MediaVisibility::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                        <flux:select wire:model="editStatus" :label="__('Status')">@foreach(MediaStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>
                    </div>
                    <flux:switch wire:model="isFeatured" :label="__('Featured asset')" />
                    @can('update', $this->detail)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"><p class="text-sm font-semibold">{{ __('Replace physical file') }}</p><p class="mt-1 text-xs text-amber-800 dark:text-amber-300">{{ __('This affects every place where the asset is used. The old file is removed only after the replacement succeeds.') }}</p><input type="file" wire:model="replacementFile" class="mt-3 block w-full text-sm"><flux:error name="replacementFile" /><flux:button type="button" class="mt-3" wire:click="replaceFile" wire:loading.attr="disabled">{{ __('Replace file') }}</flux:button></div>
                    @endcan
                    <div class="flex flex-wrap justify-between gap-3">
                        <div class="flex flex-wrap gap-2">
                            @unless($this->detail->trashed())
                                @can('update', $this->detail)<flux:button type="button" wire:click="confirmMedia({{ $this->detail->id }}, '{{ $this->detail->status === MediaStatus::Archived ? 'activate' : 'archive' }}')">{{ $this->detail->status === MediaStatus::Archived ? __('Restore active') : __('Archive') }}</flux:button>@endcan
                                @can('delete', $this->detail)<flux:button type="button" variant="danger" wire:click="confirmMedia({{ $this->detail->id }}, 'delete')">{{ __('Move to trash') }}</flux:button>@endcan
                            @endunless
                        </div>
                        @can('update', $this->detail)<flux:button type="submit" variant="primary">{{ __('Save details') }}</flux:button>@endcan
                    </div>
                </form>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showConfirmModal" class="max-w-lg">
        <form wire:submit="executeConfirmed" class="space-y-5"><div><flux:heading size="lg">{{ __('Confirm action') }}</flux:heading><flux:text class="mt-2">{{ $pendingAction === 'force-delete' ? __('This permanently removes the database record and physical file. It cannot be undone.') : ($pendingAction === 'delete-folder' ? __('Only empty folders can be deleted. No assets or physical files will be removed.') : __('This action will be recorded in the activity log.')) }}</flux:text></div><div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" :variant="in_array($pendingAction, ['delete', 'force-delete', 'delete-folder']) ? 'danger' : 'primary'">{{ __('Confirm') }}</flux:button></div></form>
    </flux:modal>

    <flux:modal wire:model="showBulkModal" class="max-w-lg">
        <form wire:submit="executeBulk" class="space-y-5"><div><flux:heading size="lg">{{ __('Bulk action') }}</flux:heading><flux:text class="mt-2">{{ __('Apply :action to :count selected assets.', ['action' => str($pendingAction)->headline(), 'count' => count($selected)]) }}</flux:text></div>@if($pendingAction === 'move')<flux:select wire:model="bulkFolder" :label="__('Destination folder')"><flux:select.option value="">{{ __('Root') }}</flux:select.option>@foreach($this->folders as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach</flux:select>@elseif($pendingAction === 'visibility')<flux:select wire:model="bulkVisibility" :label="__('Visibility')">@foreach(MediaVisibility::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>@endif <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showBulkModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" :variant="in_array($pendingAction, ['delete', 'force-delete']) ? 'danger' : 'primary'">{{ __('Apply') }}</flux:button></div></form>
    </flux:modal>
</div>
