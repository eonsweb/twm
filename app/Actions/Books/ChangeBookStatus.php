<?php

namespace App\Actions\Books;

use App\Activity\ActivityLogger;
use App\BookStatus;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeBookStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function publish(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('publish', $book);

        if ($book->status === BookStatus::Archived) {
            throw ValidationException::withMessages([
                'book' => __('Restore this archived book to draft before publishing it.'),
            ]);
        }

        $this->assertPublishable($book);

        return $this->change($actor, $book, BookStatus::Published, 'published', [
            'published_at' => $book->published_at ?? now(),
        ]);
    }

    public function unpublish(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('publish', $book);

        return $this->change($actor, $book, BookStatus::Draft, 'unpublished', ['is_featured' => false]);
    }

    public function archive(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('archive', $book);

        return $this->change($actor, $book, BookStatus::Archived, 'archived', ['is_featured' => false]);
    }

    public function restore(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('restore', $book);

        return $this->change($actor, $book, BookStatus::Draft, 'restored', ['is_featured' => false]);
    }

    public function toggleFeatured(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('feature', $book);

        if (! $book->is_featured && ! $book->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'book' => __('Only currently published books may be featured.'),
            ]);
        }

        $featured = ! $book->is_featured;
        DB::transaction(function () use ($book, $featured, $actor): void {
            if ($featured) {
                Book::query()->where('is_featured', true)->whereKeyNot($book->id)->update(['is_featured' => false]);
            }

            $book->update(['is_featured' => $featured, 'updated_by' => $actor->id]);
        });

        $book->refresh();
        $event = $featured ? 'featured' : 'unfeatured';
        $this->activityLogger->log(
            logName: 'books',
            event: "book.{$event}",
            description: str($event)->headline()->toString()." book \"{$book->title}\".",
            subject: $book,
            causer: $actor,
            oldValues: ['is_featured' => ! $featured],
            newValues: ['is_featured' => $featured],
        );

        return $book;
    }

    /** @param array<string, mixed> $extra */
    private function change(User $actor, Book $book, BookStatus $status, string $event, array $extra = []): Book
    {
        $old = ['status' => $book->status->value, 'is_featured' => $book->is_featured];
        $book->update([
            'status' => $status,
            'updated_by' => $actor->id,
            ...$extra,
        ]);
        $book->refresh();

        $this->activityLogger->log(
            logName: 'books',
            event: "book.{$event}",
            description: str($event)->headline()->toString()." book \"{$book->title}\".",
            subject: $book,
            causer: $actor,
            oldValues: $old,
            newValues: ['status' => $book->status->value, 'is_featured' => $book->is_featured],
        );

        return $book;
    }

    private function assertPublishable(Book $book): void
    {
        $book->loadMissing('cover');
        $cover = $book->cover;

        if (! filled($book->short_description)
            || $cover === null
            || $cover->media_type !== MediaType::Image
            || $cover->visibility !== MediaVisibility::Public
            || $cover->status !== MediaStatus::Active) {
            throw ValidationException::withMessages([
                'book' => __('A short description and an active public cover image are required before publishing.'),
            ]);
        }

        if (! $book->is_free && $book->price === null) {
            throw ValidationException::withMessages(['book' => __('Paid books require a price before publishing.')]);
        }

        if ($book->format->isDigital() && ! filled($book->download_url) && ! filled($book->purchase_url)) {
            throw ValidationException::withMessages(['book' => __('Digital books require a download or purchase URL.')]);
        }
    }
}
