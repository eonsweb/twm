<?php

namespace App\Livewire\Forms;

use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\Topic;
use App\SermonMediaPlatform;
use App\SermonMediaType;
use App\Sermons\ExternalMedia;
use App\SermonStatus;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Form;

class SermonForm extends Form
{
    public ?int $sermonId = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $summary = '';

    public string $description = '';

    public string $scriptureReference = '';

    public string $sermonDate = '';

    public string $duration = '';

    public string $externalMediaUrl = '';

    public string $mediaPlatform = 'other';

    public string $mediaType = 'video';

    public string $embedUrl = '';

    public string $externalThumbnailUrl = '';

    public mixed $thumbnail = null;

    public bool $removeThumbnail = false;

    public int|string|null $speakerId = null;

    public int|string|null $sermonSeriesId = null;

    /** @var array<int, int|string> */
    public array $topicIds = [];

    /** @var list<int|string> */
    public array $ministryIds = [];

    public string $serviceName = '';

    public string $location = '';

    public string $status = 'draft';

    public string $publishedAt = '';

    public string $scheduledAt = '';

    public bool $isFeatured = false;

    public int $displayOrder = 0;

    public string $seoTitle = '';

    public string $seoDescription = '';

    public function setSermon(Sermon $sermon): void
    {
        $sermon->loadMissing(['topics:id', 'ministries:id']);
        $this->sermonId = $sermon->id;
        $this->title = $sermon->title;
        $this->slug = $sermon->slug;
        $this->slugManuallyEdited = true;
        $this->summary = $sermon->summary ?? '';
        $this->description = $sermon->description ?? '';
        $this->scriptureReference = $sermon->scripture_reference ?? '';
        $this->sermonDate = $sermon->sermon_date->toDateString();
        $this->duration = $sermon->formattedDuration() ?? '';
        $this->externalMediaUrl = $sermon->external_media_url;
        $this->mediaPlatform = $sermon->media_platform->value;
        $this->mediaType = $sermon->media_type->value;
        $this->embedUrl = $sermon->embed_url ?? '';
        $this->externalThumbnailUrl = $sermon->external_thumbnail_url ?? '';
        $this->speakerId = $sermon->speaker_id;
        $this->sermonSeriesId = $sermon->sermon_series_id;
        $this->topicIds = array_values(
            $sermon->topics->map(fn (Topic $topic): int => $topic->id)->all(),
        );
        $this->ministryIds = array_values($sermon->ministries->modelKeys());
        $this->serviceName = $sermon->service_name ?? '';
        $this->location = $sermon->location ?? '';
        $this->status = $sermon->status->value;
        $this->publishedAt = $sermon->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->scheduledAt = $sermon->scheduled_at?->format('Y-m-d\TH:i') ?? '';
        $this->isFeatured = $sermon->is_featured;
        $this->displayOrder = $sermon->display_order;
        $this->seoTitle = $sermon->seo_title ?? '';
        $this->seoDescription = $sermon->seo_description ?? '';
    }

    public function updatedTitle(string $title): void
    {
        if (! $this->slugManuallyEdited && $this->sermonId === null) {
            $this->slug = Str::slug($title);
        }
    }

    public function markSlugAsEdited(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function normalize(ExternalMedia $externalMedia): void
    {
        $this->title = Str::squish($this->title);
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);
        $this->speakerId = filled($this->speakerId) ? (int) $this->speakerId : null;
        $this->sermonSeriesId = filled($this->sermonSeriesId) ? (int) $this->sermonSeriesId : null;
        $this->topicIds = array_values(
            collect($this->topicIds)
                ->map(fn (int|string $id): int => (int) $id)
                ->unique()
                ->all(),
        );
        $this->ministryIds = array_values(collect($this->ministryIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all());

        try {
            $media = $externalMedia->inspect($this->externalMediaUrl);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.externalMediaUrl' => $exception->getMessage(),
            ]);
        }

        $this->externalMediaUrl = $media['original_url'];
        $this->mediaPlatform = $media['platform']->value;
        $this->embedUrl = $media['embed_url'] ?? '';
        $this->externalThumbnailUrl = $media['thumbnail_url'] ?? '';
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'alpha_dash:ascii',
                'max:255',
                Rule::notIn(['admin', 'create', 'edit', 'preview', 'feed', 'api']),
                Rule::unique(Sermon::class, 'slug')->ignore($this->sermonId),
            ],
            'summary' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'scriptureReference' => ['nullable', 'string', 'max:255'],
            'sermonDate' => ['required', 'date'],
            'duration' => ['nullable', 'regex:/^(?:\d{1,3}:)?[0-5]?\d:[0-5]\d$/'],
            'externalMediaUrl' => ['required', 'string', 'max:2048', 'url:https'],
            'mediaPlatform' => ['required', Rule::enum(SermonMediaPlatform::class)],
            'mediaType' => ['required', Rule::enum(SermonMediaType::class)],
            'embedUrl' => ['nullable', 'string', 'max:2048', 'url:https'],
            'externalThumbnailUrl' => ['nullable', 'string', 'max:2048', 'url:https'],
            'thumbnail' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
            'removeThumbnail' => ['boolean'],
            'speakerId' => ['required', 'integer', Rule::exists(Person::class, 'id')->where('is_active', true)],
            'sermonSeriesId' => ['nullable', 'integer', Rule::exists(SermonSeries::class, 'id')->withoutTrashed()],
            'topicIds' => ['array', 'max:20'],
            'topicIds.*' => ['integer', 'distinct', Rule::exists(Topic::class, 'id')->where('is_active', true)],
            'ministryIds' => ['array', 'max:20'],
            'ministryIds.*' => ['integer', 'distinct', Rule::exists(Ministry::class, 'id')->withoutTrashed()],
            'serviceName' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SermonStatus::class)],
            'publishedAt' => [
                Rule::requiredIf($this->status === SermonStatus::Published->value),
                'nullable',
                'date',
            ],
            'scheduledAt' => [
                Rule::requiredIf($this->status === SermonStatus::Scheduled->value),
                'nullable',
                'date',
                Rule::when($this->status === SermonStatus::Scheduled->value, ['after:now']),
            ],
            'isFeatured' => ['boolean'],
            'displayOrder' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'seoTitle' => ['nullable', 'string', 'max:70'],
            'seoDescription' => ['nullable', 'string', 'max:170'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sermonData(): array
    {
        return collect([
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'scripture_reference' => $this->scriptureReference,
            'sermon_date' => $this->sermonDate,
            'duration_seconds' => $this->durationSeconds(),
            'external_media_url' => $this->externalMediaUrl,
            'media_platform' => $this->mediaPlatform,
            'media_type' => $this->mediaType,
            'embed_url' => $this->embedUrl,
            'external_thumbnail_url' => $this->externalThumbnailUrl,
            'speaker_id' => $this->speakerId,
            'sermon_series_id' => $this->sermonSeriesId,
            'service_name' => $this->serviceName,
            'location' => $this->location,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
            'scheduled_at' => $this->scheduledAt,
            'is_featured' => $this->isFeatured,
            'display_order' => $this->displayOrder,
            'seo_title' => $this->seoTitle,
            'seo_description' => $this->seoDescription,
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }

    private function durationSeconds(): ?int
    {
        if ($this->duration === '') {
            return null;
        }

        $parts = array_map('intval', explode(':', $this->duration));

        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }

        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }
}
