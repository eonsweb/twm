<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('media:audit {--cleanup : Delete orphaned physical files after confirmation}')]
#[Description('Report missing media files, orphaned physical files, and expired trash records')]
class AuditMedia extends Command
{
    public function handle(): int
    {
        $records = Media::withTrashed()->get(['id', 'disk', 'path', 'deleted_at']);
        $missing = $records->filter(fn (Media $media): bool => ! Storage::disk($media->disk)->exists($media->path));
        $knownByDisk = $records->groupBy('disk')->map->pluck('path')->map->flip();
        $orphaned = collect();

        foreach (array_unique([(string) config('media.disk'), (string) config('media.private_disk')]) as $disk) {
            foreach (Storage::disk($disk)->allFiles('media') as $path) {
                if (! isset($knownByDisk[$disk][$path])) {
                    $orphaned->push(['disk' => $disk, 'path' => $path]);
                }
            }
        }

        $expired = $records->filter(
            fn (Media $media): bool => $media->deleted_at?->lt(now()->subDays((int) config('media.retention_days'))) ?? false,
        );

        $this->components->info('Media audit completed.');
        $this->table(['Check', 'Count'], [
            ['Database records with missing files', $missing->count()],
            ['Physical files without records', $orphaned->count()],
            ['Trash records past retention', $expired->count()],
        ]);

        foreach ($missing as $media) {
            $this->line("Missing: media #{$media->id} ({$media->disk})");
        }
        foreach ($orphaned as $file) {
            $this->line("Orphan: {$file['disk']}:{$file['path']}");
        }

        if (! $this->option('cleanup') || $orphaned->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->confirm('Delete the listed orphaned physical files?', false)) {
            $this->components->warn('Cleanup cancelled. No files were deleted.');

            return self::SUCCESS;
        }

        $orphaned->each(fn (array $file) => Storage::disk($file['disk'])->delete($file['path']));
        $this->components->info("Deleted {$orphaned->count()} orphaned physical files.");

        return self::SUCCESS;
    }
}
