<?php

namespace App\Actions\Books;

use App\Activity\ActivityLogger;
use App\BookStatus;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Book;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveBook
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?Book $book = null): Book
    {
        $creating = $book === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $book ?? Book::class);
        $book ??= new Book;
        $this->authorizeWorkflow($actor, $book, $data);
        $this->validateBusinessRules($data);

        $oldValues = $creating ? [] : $this->auditValues($book);
        $newStatus = BookStatus::from((string) $data['status']);
        $data['slug'] = Book::uniqueSlug(
            (string) ($data['slug'] ?: $data['title']),
            $book->exists ? $book->id : null,
        );
        $data['is_featured'] = $newStatus === BookStatus::Published && (bool) ($data['is_featured'] ?? false);
        $data['published_at'] = $newStatus === BookStatus::Published
            ? ($data['published_at'] ?: now())
            : $data['published_at'];
        $data['price'] = (bool) ($data['is_free'] ?? false) ? null : $data['price'];
        $data['created_by'] ??= $creating ? $actor->id : $book->created_by;
        $data['updated_by'] = $actor->id;

        $saved = DB::transaction(function () use ($book, $data): Book {
            if ($data['is_featured']) {
                Book::query()->where('is_featured', true)->whereKeyNot($book->getKey() ?? 0)->update(['is_featured' => false]);
            }

            $book->fill($data)->save();

            return $book->refresh()->load(['cover', 'leadership', 'speaker']);
        });

        $newValues = $this->auditValues($saved);
        $keys = $creating ? array_keys($newValues) : collect($newValues)
            ->filter(fn (mixed $value, string $key): bool => ($oldValues[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if ($keys !== []) {
            $this->activityLogger->log(
                logName: 'books',
                event: $creating ? 'book.created' : 'book.updated',
                description: ($creating ? 'Created' : 'Updated')." book \"{$saved->title}\".",
                subject: $saved,
                causer: $actor,
                oldValues: Arr::only($oldValues, $keys),
                newValues: Arr::only($newValues, $keys),
            );
        }

        $this->logNotableChanges($actor, $saved, $oldValues, $newValues);

        return $saved;
    }

    /** @param array<string, mixed> $data */
    private function authorizeWorkflow(User $actor, Book $book, array $data): void
    {
        $newStatus = BookStatus::from((string) $data['status']);
        $oldStatus = $book->exists ? $book->status : BookStatus::Draft;

        if ($oldStatus === BookStatus::Archived && $newStatus === BookStatus::Published) {
            throw ValidationException::withMessages([
                'status' => __('Restore this archived book to draft before publishing it.'),
            ]);
        }

        if ($newStatus !== $oldStatus) {
            if ($newStatus === BookStatus::Published || $oldStatus === BookStatus::Published) {
                Gate::forUser($actor)->authorize('publish', $book);
            }

            if ($newStatus === BookStatus::Archived) {
                Gate::forUser($actor)->authorize('archive', $book);
            }

            if ($oldStatus === BookStatus::Archived) {
                Gate::forUser($actor)->authorize('restore', $book);
            }
        }

        if ((bool) ($data['is_featured'] ?? false) !== ($book->is_featured ?? false)) {
            Gate::forUser($actor)->authorize('feature', $book);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateBusinessRules(array $data): void
    {
        if (filled($data['leadership_id'] ?? null) && filled($data['speaker_id'] ?? null)) {
            throw ValidationException::withMessages([
                'author_name' => __('Choose either a leadership author or a sermon speaker, not both.'),
            ]);
        }

        if (! (bool) ($data['is_free'] ?? false) && ! is_numeric($data['price'] ?? null)) {
            throw ValidationException::withMessages(['price' => __('Paid books require a valid price.')]);
        }

        $media = filled($data['media_id'] ?? null)
            ? Media::query()->find((int) $data['media_id'])
            : null;
        if ($media !== null && ($media->media_type !== MediaType::Image
            || $media->visibility !== MediaVisibility::Public
            || $media->status !== MediaStatus::Active)) {
            throw ValidationException::withMessages([
                'media_id' => __('The cover must be an active, public image from the Media Library.'),
            ]);
        }

        if (($data['status'] ?? null) === BookStatus::Published->value
            && ($media === null || ! filled($data['short_description'] ?? null))) {
            throw ValidationException::withMessages([
                'status' => __('Published books require a cover and short description.'),
            ]);
        }

        if ((bool) ($data['is_featured'] ?? false)
            && (! filled($data['published_at'] ?? null) || Carbon::parse($data['published_at'])->isFuture())) {
            throw ValidationException::withMessages([
                'is_featured' => __('Only a currently published book may be featured.'),
            ]);
        }
    }

    /** @param array<string, mixed> $old
     * @param  array<string, mixed>  $new
     */
    private function logNotableChanges(User $actor, Book $book, array $old, array $new): void
    {
        $changes = [
            'status' => match ($book->status) {
                BookStatus::Published => 'published',
                BookStatus::Archived => 'archived',
                BookStatus::Draft => ($old['status'] ?? null) === BookStatus::Archived->value ? 'restored' : 'unpublished',
            },
            'is_featured' => $book->is_featured ? 'featured' : 'unfeatured',
            'media_id' => 'cover-changed',
            'price' => 'price-changed',
            'availability_status' => 'availability-changed',
        ];

        foreach ($changes as $key => $event) {
            if (! array_key_exists($key, $old) || ($old[$key] ?? null) === ($new[$key] ?? null)) {
                continue;
            }

            $this->activityLogger->log(
                logName: 'books',
                event: "book.{$event}",
                description: str($event)->headline()->toString()." book \"{$book->title}\".",
                subject: $book,
                causer: $actor,
                oldValues: [$key => $old[$key]],
                newValues: [$key => $new[$key]],
            );
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(Book $book): array
    {
        return [
            'title' => $book->title,
            'slug' => $book->slug,
            'author_name' => $book->author_name,
            'leadership_id' => $book->leadership_id,
            'speaker_id' => $book->speaker_id,
            'format' => $book->format->value,
            'price' => $book->price,
            'currency' => $book->currency,
            'availability_status' => $book->availability_status->value,
            'media_id' => $book->media_id,
            'is_featured' => $book->is_featured,
            'is_free' => $book->is_free,
            'status' => $book->status->value,
            'published_at' => $book->published_at?->toIso8601String(),
        ];
    }
}
