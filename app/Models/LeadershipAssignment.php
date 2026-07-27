<?php

namespace App\Models;

use Database\Factories\LeadershipAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $person_id
 * @property int $leadership_position_id
 * @property string|null $display_title
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property bool $is_current
 * @property bool $is_primary
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'person_id',
    'leadership_position_id',
    'display_title',
    'started_at',
    'ended_at',
    'is_current',
    'is_primary',
    'sort_order',
])]
class LeadershipAssignment extends Model
{
    /** @use HasFactory<LeadershipAssignmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_current' => true,
        'is_primary' => false,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<LeadershipPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(LeadershipPosition::class, 'leadership_position_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'is_current' => 'boolean',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
