<?php

namespace App\Actions\Media;

use App\Media\MediaFileService;
use App\MediaStatus;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\User;
use App\Settings\BrandingMedia;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageMedia
{
    public function __construct(
        private readonly MediaFileService $files,
        private readonly BrandingMedia $brandingMedia,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(User $actor, Media $media, array $data): Media
    {
        Gate::forUser($actor)->authorize('update', $media);
        $validated = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'media_folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'visibility' => ['required', Rule::enum(MediaVisibility::class)],
            'status' => ['required', Rule::enum(MediaStatus::class)],
            'is_featured' => ['boolean'],
        ])->validate();

        if ($validated['visibility'] === MediaVisibility::Private->value
            && ! $actor->can('managePrivate', $media)) {
            throw new AuthorizationException;
        }

        $visibility = MediaVisibility::from($validated['visibility']);
        if ($media->visibility !== $visibility) {
            $this->files->changeVisibility($media, $visibility);
        }

        $media->update([
            ...$validated,
            'name' => Str::squish($validated['name']),
            'slug' => Str::slug($validated['name']) ?: null,
            'updated_by' => $actor->id,
        ]);

        return $media->refresh();
    }

    public function archive(User $actor, Media $media): Media
    {
        Gate::forUser($actor)->authorize('update', $media);
        $media->update(['status' => MediaStatus::Archived, 'updated_by' => $actor->id]);

        return $media;
    }

    public function restoreArchive(User $actor, Media $media): Media
    {
        Gate::forUser($actor)->authorize('update', $media);
        $media->update(['status' => MediaStatus::Active, 'updated_by' => $actor->id]);

        return $media;
    }

    public function delete(User $actor, Media $media): void
    {
        Gate::forUser($actor)->authorize('delete', $media);
        $this->ensureNotUsedByBranding($media);
        $media->delete();
    }

    public function restore(User $actor, Media $media): void
    {
        Gate::forUser($actor)->authorize('restore', $media);
        $media->restore();
    }

    public function forceDelete(User $actor, Media $media): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $media);
        $this->ensureNotUsedByBranding($media);

        if ($media->usages()->exists()) {
            throw ValidationException::withMessages(['media' => 'This asset is still in use and cannot be permanently deleted.']);
        }

        $this->files->forceDelete($media);
    }

    private function ensureNotUsedByBranding(Media $media): void
    {
        if ($this->brandingMedia->isReferenced($media)) {
            throw ValidationException::withMessages([
                'media' => 'This asset is used by Branding and cannot be deleted until the branding reference is removed.',
            ]);
        }
    }
}
