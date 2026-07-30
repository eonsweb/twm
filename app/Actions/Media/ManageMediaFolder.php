<?php

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageMediaFolder
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?MediaFolder $folder = null): MediaFolder
    {
        Gate::forUser($actor)->authorize($folder === null ? 'create' : 'update', $folder ?? MediaFolder::class);
        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;
        $validated = validator($data, [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('media_folders', 'name')
                    ->where(fn ($query) => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId))
                    ->ignore($folder?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ])->validate();

        if ($folder !== null && $parentId === $folder->id) {
            throw ValidationException::withMessages(['parent_id' => 'A folder cannot be its own parent.']);
        }

        $parent = $parentId === null ? null : MediaFolder::findOrFail($parentId);
        if ($folder !== null && $parent?->isDescendantOf($folder)) {
            throw ValidationException::withMessages(['parent_id' => 'A folder cannot be moved inside one of its descendants.']);
        }

        $slug = Str::slug($validated['name']) ?: 'folder';
        $duplicateSlug = MediaFolder::query()
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->where('slug', $slug)
            ->when($folder !== null, fn ($query) => $query->whereKeyNot($folder))
            ->exists();

        if ($duplicateSlug) {
            throw ValidationException::withMessages(['name' => 'A folder with an equivalent URL-safe name already exists here.']);
        }

        $values = [
            ...$validated,
            'name' => Str::squish($validated['name']),
            'slug' => $slug,
            'updated_by' => $actor->id,
        ];

        if ($folder === null) {
            $values['created_by'] = $actor->id;
            $folder = MediaFolder::query()->create($values);
        } else {
            $folder->update($values);
        }

        return $folder->refresh();
    }

    public function delete(User $actor, MediaFolder $folder): void
    {
        Gate::forUser($actor)->authorize('delete', $folder);
        if ($folder->children()->exists() || $folder->media()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['folder' => 'Empty this folder before deleting it. No files were removed.']);
        }

        $folder->delete();
    }
}
