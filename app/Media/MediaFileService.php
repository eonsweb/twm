<?php

namespace App\Media;

use App\MediaType;
use App\MediaVisibility;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaFileService
{
    /** @return list<string> */
    public function allowedExtensions(): array
    {
        $extensions = [];

        foreach ($this->typeConfiguration() as $configuration) {
            $extensions = [...$extensions, ...$configuration['extensions']];
        }

        return array_values(array_unique($extensions));
    }

    public function maximumKilobytes(): int
    {
        $limits = array_column($this->typeConfiguration(), 'max_kilobytes');

        return $limits === [] ? 0 : max($limits);
    }

    public function store(
        UploadedFile $file,
        User $actor,
        ?MediaFolder $folder = null,
        MediaVisibility $visibility = MediaVisibility::Public,
    ): Media {
        [$type, $extension, $mimeType] = $this->inspect($file);
        $disk = $visibility === MediaVisibility::Private
            ? (string) config('media.private_disk', 'local')
            : (string) config('media.disk', 'public');
        $directory = 'media/'.$this->directoryName($type).'/'.now()->format('Y/m');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $fileName, $disk);

        if (! is_string($path) || $path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Media could not be stored permanently.');
        }

        try {
            return DB::transaction(function () use ($file, $actor, $folder, $visibility, $type, $extension, $mimeType, $disk, $directory, $fileName, $path): Media {
                [$width, $height, $metadata] = $this->imageMetadata($file, $type);

                return Media::query()->create([
                    'media_folder_id' => $folder?->id,
                    'uploaded_by' => $actor->id,
                    'name' => Str::of($file->getClientOriginalName())->beforeLast('.')->squish()->limit(255)->toString(),
                    'original_name' => Str::of($file->getClientOriginalName())->basename()->limit(255)->toString(),
                    'file_name' => $fileName,
                    'slug' => Str::slug(Str::beforeLast($file->getClientOriginalName(), '.')) ?: null,
                    'disk' => $disk,
                    'directory' => $directory,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'media_type' => $type,
                    'size' => Storage::disk($disk)->size($path),
                    'width' => $width,
                    'height' => $height,
                    'visibility' => $visibility,
                    'metadata' => $metadata,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function replace(Media $media, UploadedFile $file, User $actor): Media
    {
        [$type, $extension, $mimeType] = $this->inspect($file);
        $disk = $media->visibility === MediaVisibility::Private
            ? (string) config('media.private_disk')
            : (string) config('media.disk');
        $directory = 'media/'.$this->directoryName($type).'/'.now()->format('Y/m');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $newPath = $file->storeAs($directory, $fileName, $disk);

        if (! is_string($newPath)) {
            throw new RuntimeException('The replacement file could not be stored.');
        }

        $oldDisk = $media->disk;
        $oldPath = $media->path;

        try {
            DB::transaction(function () use ($media, $file, $actor, $type, $extension, $mimeType, $disk, $directory, $fileName, $newPath): void {
                [$width, $height, $metadata] = $this->imageMetadata($file, $type);
                $media->update([
                    'updated_by' => $actor->id,
                    'original_name' => Str::of($file->getClientOriginalName())->basename()->limit(255)->toString(),
                    'file_name' => $fileName,
                    'disk' => $disk,
                    'directory' => $directory,
                    'path' => $newPath,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'media_type' => $type,
                    'size' => Storage::disk($disk)->size($newPath),
                    'width' => $width,
                    'height' => $height,
                    'metadata' => $metadata,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($newPath);
            throw $exception;
        }

        Storage::disk($oldDisk)->delete($oldPath);

        return $media->refresh();
    }

    public function changeVisibility(Media $media, MediaVisibility $visibility): Media
    {
        $targetDisk = $visibility === MediaVisibility::Private
            ? (string) config('media.private_disk')
            : (string) config('media.disk');

        if ($media->disk === $targetDisk) {
            $media->update(['visibility' => $visibility]);

            return $media->refresh();
        }

        $sourceDisk = $media->disk;
        $stream = Storage::disk($sourceDisk)->readStream($media->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The existing file could not be read.');
        }

        try {
            if (! Storage::disk($targetDisk)->put($media->path, $stream)) {
                throw new RuntimeException('The file could not be moved to the requested storage.');
            }
        } finally {
            fclose($stream);
        }

        try {
            DB::transaction(fn () => $media->update([
                'disk' => $targetDisk,
                'visibility' => $visibility,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($targetDisk)->delete($media->path);
            throw $exception;
        }

        Storage::disk($sourceDisk)->delete($media->path);

        return $media->refresh();
    }

    public function forceDelete(Media $media): void
    {
        $disk = $media->disk;
        $path = $media->path;

        DB::transaction(fn () => $media->forceDelete());
        Storage::disk($disk)->delete($path);
    }

    /** @return array{MediaType, string, string} */
    private function inspect(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $mimeType = Str::lower((string) $file->getMimeType());
        $typeName = null;
        $type = null;

        foreach ($this->typeConfiguration() as $configuredType => $settings) {
            if (in_array($extension, $settings['extensions'], true)) {
                $typeName = $configuredType;
                $type = $settings;
                break;
            }
        }

        if ($type === null || $typeName === null || MediaType::tryFrom($typeName) === null) {
            throw ValidationException::withMessages(['files' => "The .{$extension} file type is not allowed."]);
        }

        $mimeTypes = $type['mime_types'];
        $officeZip = in_array($extension, ['docx', 'xlsx', 'pptx'], true)
            && in_array($mimeType, ['application/zip', 'application/octet-stream'], true);

        if (! in_array($mimeType, $mimeTypes, true) && ! $officeZip) {
            throw ValidationException::withMessages(['files' => "The detected file content does not match .{$extension}."]);
        }

        if ($file->getSize() > ((int) $type['max_kilobytes'] * 1024)) {
            throw ValidationException::withMessages(['files' => "The {$typeName} file exceeds its configured upload limit."]);
        }

        return [MediaType::from($typeName), $extension, $mimeType];
    }

    private function directoryName(MediaType $type): string
    {
        return match ($type) {
            MediaType::Image => 'images',
            MediaType::Video => 'videos',
            MediaType::Audio => 'audio',
            MediaType::Archive => 'archives',
            MediaType::Spreadsheet => 'spreadsheets',
            MediaType::Presentation => 'presentations',
            default => 'documents',
        };
    }

    /** @return array{int|null, int|null, array<string, float|string>|null} */
    private function imageMetadata(UploadedFile $file, MediaType $type): array
    {
        if ($type !== MediaType::Image) {
            return [null, null, null];
        }

        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            return [null, null, null];
        }

        [$width, $height] = $dimensions;

        return [$width, $height, [
            'orientation' => $width >= $height ? 'landscape' : 'portrait',
            'aspect_ratio' => $height > 0 ? round($width / $height, 4) : 0,
        ]];
    }

    /**
     * @return array<string, array{extensions: list<string>, mime_types: list<string>, max_kilobytes: int}>
     */
    private function typeConfiguration(): array
    {
        $configured = config('media.types', []);

        if (! is_array($configured)) {
            return [];
        }

        $types = [];

        foreach ($configured as $name => $settings) {
            if (! is_string($name) || ! is_array($settings)) {
                continue;
            }

            $extensions = $settings['extensions'] ?? [];
            $mimeTypes = $settings['mime_types'] ?? [];
            $maxKilobytes = $settings['max_kilobytes'] ?? 0;

            if (! is_array($extensions) || ! is_array($mimeTypes) || ! is_int($maxKilobytes)) {
                continue;
            }

            $extensions = array_values(array_filter($extensions, is_string(...)));
            $mimeTypes = array_values(array_filter($mimeTypes, is_string(...)));

            $types[$name] = [
                'extensions' => $extensions,
                'mime_types' => $mimeTypes,
                'max_kilobytes' => $maxKilobytes,
            ];
        }

        return $types;
    }
}
