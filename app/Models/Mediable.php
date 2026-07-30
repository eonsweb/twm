<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['media_id', 'mediable_type', 'mediable_id', 'collection', 'position', 'metadata'])]
class Mediable extends Model
{
    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
