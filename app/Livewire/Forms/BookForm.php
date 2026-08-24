<?php

namespace App\Livewire\Forms;

use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Book;
use App\Models\Media;
use App\Models\Person;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class BookForm extends Form
{
    public ?int $bookId = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $subtitle = '';

    public string $authorName = '';

    public int|string|null $leadershipId = null;

    public int|string|null $speakerId = null;

    public string $description = '';

    public string $shortDescription = '';

    public string $isbn = '';

    public string $publisher = '';

    public string $publicationDate = '';

    public string $edition = '';

    public string $language = 'English';

    public int|string|null $pageCount = null;

    public string $format = 'physical';

    public float|string|null $price = null;

    public string $currency = 'GHS';

    public int|string|null $stockQuantity = null;

    public string $availabilityStatus = 'available';

    public string $purchaseUrl = '';

    public string $downloadUrl = '';

    /** @var list<int> */
    public array $mediaIds = [];

    /** @var list<int> */
    public array $audioSampleMediaIds = [];

    public bool $isFeatured = false;

    public bool $isFree = false;

    public string $status = 'draft';

    public string $publishedAt = '';

    public function setBook(Book $book): void
    {
        $this->bookId = $book->id;
        $this->title = $book->title;
        $this->slug = $book->slug;
        $this->slugManuallyEdited = true;
        $this->subtitle = $book->subtitle ?? '';
        $this->authorName = $book->author_name;
        $this->leadershipId = $book->leadership_id;
        $this->speakerId = $book->speaker_id;
        $this->description = $book->description ?? '';
        $this->shortDescription = $book->short_description ?? '';
        $this->isbn = $book->isbn ?? '';
        $this->publisher = $book->publisher ?? '';
        $this->publicationDate = $book->publication_date?->format('Y-m-d') ?? '';
        $this->edition = $book->edition ?? '';
        $this->language = $book->language;
        $this->pageCount = $book->page_count;
        $this->format = $book->format->value;
        $this->price = $book->price;
        $this->currency = $book->currency;
        $this->stockQuantity = $book->stock_quantity;
        $this->availabilityStatus = $book->availability_status->value;
        $this->purchaseUrl = (string) ($book->getRawOriginal('purchase_url') ?? '');
        $this->downloadUrl = $book->download_url ?? '';
        $this->mediaIds = $book->media_id === null ? [] : [$book->media_id];
        $this->audioSampleMediaIds = $book->audio_sample_media_id === null ? [] : [$book->audio_sample_media_id];
        $this->isFeatured = $book->is_featured;
        $this->isFree = $book->is_free;
        $this->status = $book->status->value;
        $this->publishedAt = $book->published_at?->format('Y-m-d\TH:i') ?? '';
    }

    public function updatedTitle(string $title): void
    {
        if (! $this->slugManuallyEdited && $this->bookId === null) {
            $this->slug = Str::slug($title);
        }
    }

    public function markSlugAsEdited(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function updatedLeadershipId(int|string|null $leadershipId): void
    {
        if (filled($leadershipId)) {
            $this->speakerId = null;
            $person = Person::query()->whereKey((int) $leadershipId)->first();
            if ($person !== null) {
                $this->authorName = $person->full_name;
            }
        }
    }

    public function updatedSpeakerId(int|string|null $speakerId): void
    {
        if (filled($speakerId)) {
            $this->leadershipId = null;
            $person = Person::query()->whereKey((int) $speakerId)->first();
            if ($person !== null) {
                $this->authorName = $person->full_name;
            }
        }
    }

    public function normalize(): void
    {
        foreach (['title', 'subtitle', 'authorName', 'isbn', 'publisher', 'edition', 'language', 'currency'] as $property) {
            $this->{$property} = Str::squish($this->{$property});
        }

        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);
        $this->leadershipId = filled($this->leadershipId) ? (int) $this->leadershipId : null;
        $this->speakerId = filled($this->speakerId) ? (int) $this->speakerId : null;
        $this->pageCount = filled($this->pageCount) ? (int) $this->pageCount : null;
        $this->stockQuantity = filled($this->stockQuantity) ? (int) $this->stockQuantity : null;
        $this->price = $this->isFree || ! filled($this->price) ? null : (float) $this->price;
        $this->currency = Str::upper($this->currency);
        $this->mediaIds = array_slice(array_values(array_unique(array_map('intval', $this->mediaIds))), 0, 1);
        $this->audioSampleMediaIds = array_slice(array_values(array_unique(array_map('intval', $this->audioSampleMediaIds))), 0, 1);

        if ($this->status !== BookStatus::Published->value) {
            $this->isFeatured = false;
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique(Book::class, 'slug')->ignore($this->bookId)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authorName' => ['required', 'string', 'max:255'],
            'leadershipId' => ['nullable', 'integer', Rule::exists(Person::class, 'id')],
            'speakerId' => ['nullable', 'integer', Rule::exists(Person::class, 'id')],
            'description' => ['nullable', 'string', 'max:100000'],
            'shortDescription' => ['nullable', 'string', 'max:1000'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publicationDate' => ['nullable', 'date'],
            'edition' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:64'],
            'pageCount' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'format' => ['required', Rule::enum(BookFormat::class)],
            'price' => [Rule::requiredIf(! $this->isFree), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'stockQuantity' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'availabilityStatus' => ['required', Rule::enum(BookAvailabilityStatus::class)],
            'purchaseUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'downloadUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'mediaIds' => ['array', 'max:1'],
            'mediaIds.*' => ['integer', 'distinct', Rule::exists(Media::class, 'id')->withoutTrashed()],
            'audioSampleMediaIds' => ['array', 'max:1'],
            'audioSampleMediaIds.*' => ['integer', 'distinct', Rule::exists(Media::class, 'id')->withoutTrashed()],
            'isFeatured' => ['boolean'],
            'isFree' => ['boolean'],
            'status' => ['required', Rule::enum(BookStatus::class)],
            'publishedAt' => ['nullable', 'date'],
        ];
    }

    public function validateBusinessRules(): void
    {
        if ($this->leadershipId !== null && $this->speakerId !== null) {
            throw ValidationException::withMessages([
                'form.authorName' => __('Choose either a leadership author or a sermon speaker, not both.'),
            ]);
        }

        $media = $this->mediaIds === [] ? null : Media::find($this->mediaIds[0]);
        if ($media !== null) {
            Gate::authorize('view', $media);

            if ($media->media_type !== MediaType::Image
                || $media->visibility !== MediaVisibility::Public
                || $media->status !== MediaStatus::Active) {
                throw ValidationException::withMessages([
                    'form.mediaIds' => __('The cover must be an active, public image from the Media Library.'),
                ]);
            }
        }

        $audioSample = $this->audioSampleMediaIds === [] ? null : Media::find($this->audioSampleMediaIds[0]);
        if ($audioSample !== null) {
            Gate::authorize('view', $audioSample);

            if ($audioSample->media_type !== MediaType::Audio
                || $audioSample->visibility !== MediaVisibility::Public
                || $audioSample->status !== MediaStatus::Active) {
                throw ValidationException::withMessages([
                    'form.audioSampleMediaIds' => __('The audio sample must be an active, public audio file from the Media Library.'),
                ]);
            }
        }

        if ($this->status === BookStatus::Published->value) {
            $messages = [];

            if ($media === null) {
                $messages['form.mediaIds'] = __('A cover image is required before publishing.');
            }

            if (! filled($this->shortDescription)) {
                $messages['form.shortDescription'] = __('A short description is required before publishing.');
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        }
    }

    /** @return array<string, mixed> */
    public function bookData(): array
    {
        return collect([
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'author_name' => $this->authorName,
            'leadership_id' => $this->leadershipId,
            'speaker_id' => $this->speakerId,
            'description' => trim($this->description),
            'short_description' => trim($this->shortDescription),
            'isbn' => $this->isbn,
            'publisher' => $this->publisher,
            'publication_date' => $this->publicationDate,
            'edition' => $this->edition,
            'language' => $this->language,
            'page_count' => $this->pageCount,
            'format' => $this->format,
            'price' => $this->price,
            'currency' => $this->currency,
            'stock_quantity' => $this->stockQuantity,
            'availability_status' => $this->availabilityStatus,
            'purchase_url' => $this->purchaseUrl,
            'download_url' => $this->downloadUrl,
            'media_id' => $this->mediaIds[0] ?? null,
            'audio_sample_media_id' => $this->audioSampleMediaIds[0] ?? null,
            'is_featured' => $this->isFeatured,
            'is_free' => $this->isFree,
            'status' => $this->status,
            'published_at' => $this->publishedAt,
        ])->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all();
    }
}
