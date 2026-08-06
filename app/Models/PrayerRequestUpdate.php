<?php

namespace App\Models;

use App\PrayerRequestUpdateType;
use Database\Factories\PrayerRequestUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['prayer_request_id', 'user_id', 'type', 'note', 'is_private'])]
class PrayerRequestUpdate extends Model
{
    /** @use HasFactory<PrayerRequestUpdateFactory> */
    use HasFactory;

    /** @return BelongsTo<PrayerRequest, $this> */
    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(PrayerRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['type' => PrayerRequestUpdateType::class, 'is_private' => 'boolean'];
    }
}
