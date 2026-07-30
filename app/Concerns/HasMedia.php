<?php

namespace App\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasMedia
{
    /** @return MorphToMany<Media, $this> */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->withPivot(['collection', 'position', 'metadata'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return MorphToMany<Media, $this> */
    public function mediaCollection(string $collection): MorphToMany
    {
        return $this->media()->wherePivot('collection', $collection);
    }
}
