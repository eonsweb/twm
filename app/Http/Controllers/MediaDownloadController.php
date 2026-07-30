<?php

namespace App\Http\Controllers;

use App\Activity\ActivityLogger;
use App\MediaStatus;
use App\Models\Media;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDownloadController extends Controller
{
    public function __invoke(Media $media, ActivityLogger $logger): StreamedResponse
    {
        Gate::authorize('download', $media);
        abort_unless($media->status === MediaStatus::Active && $media->existsOnDisk(), 404);

        $name = Str::of($media->original_name)->basename()->limit(255)->toString();
        $response = Storage::disk($media->disk)->download($media->path, $name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        Media::query()->whereKey($media)->increment('download_count');

        if ($media->visibility->value === 'private') {
            $logger->log(
                logName: 'media',
                event: 'media.private-downloaded',
                description: "Downloaded private media {$media->name}.",
                subject: $media,
            );
        }

        return $response;
    }
}
