<?php

namespace App\Actions\Pages;

use App\Activity\ActivityLogger;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\PageStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManagePage
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function publish(User $actor, Page $page): Page
    {
        Gate::forUser($actor)->authorize('publish', $page);
        $page->update(['status' => PageStatus::Published, 'published_at' => now(), 'updated_by' => $actor->id]);
        $this->log($actor, $page, 'published');

        return $page->refresh();
    }

    public function unpublish(User $actor, Page $page): Page
    {
        Gate::forUser($actor)->authorize('publish', $page);
        if ($page->is_homepage) {
            throw ValidationException::withMessages(['page' => __('Assign another homepage before unpublishing this page.')]);
        } $page->update(['status' => PageStatus::Draft, 'published_at' => null, 'updated_by' => $actor->id]);
        $this->log($actor, $page, 'unpublished');

        return $page->refresh();
    }

    public function archive(User $actor, Page $page): Page
    {
        Gate::forUser($actor)->authorize('publish', $page);
        if ($page->is_homepage) {
            throw ValidationException::withMessages(['page' => __('Assign another homepage before archiving this page.')]);
        } $page->update(['status' => PageStatus::Archived, 'updated_by' => $actor->id]);
        $this->log($actor, $page, 'archived');

        return $page->refresh();
    }

    public function delete(User $actor, Page $page): void
    {
        Gate::forUser($actor)->authorize('delete', $page);
        $page->delete();
        $this->log($actor, $page, 'deleted');
    }

    public function restore(User $actor, Page $page): void
    {
        Gate::forUser($actor)->authorize('restore', $page);
        if (Page::query()->where('slug', $page->slug)->exists()) {
            throw ValidationException::withMessages(['page' => __('Another page is already using this slug.')]);
        } $page->restore();
        PageSection::withTrashed()->where('page_id', $page->id)->restore();
        $this->log($actor, $page, 'restored');
    }

    public function forceDelete(User $actor, Page $page): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $page);
        if (Page::withTrashed()->where('parent_id', $page->id)->exists()) {
            throw ValidationException::withMessages(['page' => __('Move or delete child pages first.')]);
        } $this->log($actor, $page, 'force-deleted');
        PageSection::withTrashed()->where('page_id', $page->id)->forceDelete();
        $page->forceDelete();
    }

    private function log(User $actor, Page $page, string $event): void
    {
        Cache::forget('pages.public-navigation');
        $this->logger->log('pages', 'page.'.$event, str($event)->headline().' page "'.$page->title.'".', $page, $actor);
    }
}
