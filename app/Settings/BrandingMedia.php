<?php

namespace App\Settings;

use App\Models\Media;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class BrandingMedia
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    public function urls(array $values): array
    {
        $mediaIds = collect($values)
            ->filter(fn (mixed $value): bool => $this->isMediaId($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
        $media = Media::query()
            ->images()
            ->public()
            ->active()
            ->whereKey($mediaIds)
            ->get()
            ->keyBy('id');

        return collect($values)
            ->mapWithKeys(function (mixed $value, string $key) use ($media): array {
                if ($this->isMediaId($value)) {
                    return [$key => $media->get((int) $value)?->publicImageUrl()];
                }

                if (! is_string($value) || blank($value) || ! Storage::disk('public')->exists($value)) {
                    return [$key => null];
                }

                return [$key => Storage::disk('public')->url($value)];
            })
            ->all();
    }

    public function isReferenced(Media $media): bool
    {
        return SystemSetting::query()
            ->where('group', 'branding')
            ->where('type', 'image')
            ->where('value', (string) $media->getKey())
            ->exists();
    }

    private function isMediaId(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }
}
