<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use App\PermissionName;

class BookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::BooksView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::BooksCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksDelete);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksRestore);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Book $book): bool
    {
        return false;
    }

    public function publish(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksPublish);
    }

    public function archive(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksArchive);
    }

    public function feature(User $user, Book $book): bool
    {
        return $user->can(PermissionName::BooksPublish);
    }
}
