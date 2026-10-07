<?php

namespace App\Pages;

use App\Models\Media;
use App\Models\Ministry;
use Illuminate\Support\Collection;

final class HomepageMedia
{
    public function image(?string $path, ?int $mediaId = null): ?string
    {
        if (! $path && ! $mediaId) {
            return null;
        }

        return Media::query()->images()->public()->active()
            ->when($mediaId, fn ($query) => $query->whereKey($mediaId), fn ($query) => $query->where('disk', 'public')->where('path', $path))
            ->first()?->publicImageUrl();
    }

    /**
     * @param  Collection<int, Ministry>  $ministries
     * @param  array<string, mixed>  $settings
     * @return array<int, string|null>
     */
    public function ministries(Collection $ministries, array $settings = []): array
    {
        $ids = $ministries->map(fn (Ministry $ministry): mixed => $settings['ministry_'.$ministry->id.'_media_id'] ?? null)->filter();
        $media = Media::query()->images()->public()->active()
            ->where(fn ($query) => $query->whereIn('id', $ids)->orWhere(fn ($query) => $query->where('disk', 'public')->whereIn('path', $ministries->pluck('featured_image')->filter())))
            ->get();
        $byId = $media->keyBy('id');
        $byPath = $media->where('disk', 'public')->keyBy('path');

        return $ministries->mapWithKeys(function (Ministry $ministry) use ($settings, $byId, $byPath): array {
            $id = $settings['ministry_'.$ministry->id.'_media_id'] ?? null;
            $image = $id ? $byId->get($id) : $byPath->get($ministry->featured_image);

            return [$ministry->id => $image?->publicImageUrl()];
        })->all();
    }
}
