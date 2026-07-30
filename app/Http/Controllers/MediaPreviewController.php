<?php

namespace App\Http\Controllers;

use App\MediaStatus;
use App\Models\Media;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaPreviewController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        Gate::authorize('view', $media);
        abort_unless($media->status === MediaStatus::Active && $media->existsOnDisk(), 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'none'; sandbox",
        ]);
    }
}
