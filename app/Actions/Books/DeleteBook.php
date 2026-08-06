<?php

namespace App\Actions\Books;

use App\Activity\ActivityLogger;
use App\BookStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteBook
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, Book $book): void
    {
        Gate::forUser($actor)->authorize('delete', $book);
        $book->delete();

        $this->activityLogger->log(
            logName: 'books',
            event: 'book.deleted',
            description: "Moved book \"{$book->title}\" to trash.",
            subject: $book,
            causer: $actor,
        );
    }

    public function restore(User $actor, Book $book): Book
    {
        Gate::forUser($actor)->authorize('restore', $book);
        $book->restore();
        $book->update([
            'status' => BookStatus::Draft,
            'is_featured' => false,
            'updated_by' => $actor->id,
        ]);

        $this->activityLogger->log(
            logName: 'books',
            event: 'book.restored-from-trash',
            description: "Restored book \"{$book->title}\" from trash as a draft.",
            subject: $book,
            causer: $actor,
        );

        return $book->refresh();
    }
}
