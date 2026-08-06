<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use App\PageSectionType;
use Database\Factories\PageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $page_id
 * @property PageSectionType $section_type
 * @property string $name
 * @property string|null $heading
 * @property string|null $subheading
 * @property string|null $content
 * @property array<string, mixed>|null $settings
 * @property int $sort_order
 * @property bool $is_visible
 * @property int|null $background_image_id
 * @property int|null $created_by
 * @property int|null $updated_by
 */
#[Fillable(['page_id', 'section_type', 'name', 'heading', 'subheading', 'content', 'settings', 'sort_order', 'is_visible', 'background_image_id', 'created_by', 'updated_by'])]
class PageSection extends Model
{
    /** @use HasFactory<PageSectionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = ['sort_order' => 0, 'is_visible' => true];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function backgroundImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'background_image_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['section_type' => PageSectionType::class, 'settings' => 'array', 'is_visible' => 'boolean'];
    }
}
