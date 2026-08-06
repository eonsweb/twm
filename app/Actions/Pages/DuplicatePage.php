<?php

namespace App\Actions\Pages;

use App\Activity\ActivityLogger;
use App\Models\Page;
use App\Models\User;
use App\PageStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DuplicatePage
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, Page $page): Page
    {
        Gate::forUser($actor)->authorize('duplicate', $page);
        $copy = DB::transaction(function () use ($actor, $page): Page {
            $copy = $page->replicate();
            $copy->fill([
                'title' => $page->title.' (Copy)',
                'slug' => Page::uniqueSlug($page->slug),
                'status' => PageStatus::Draft,
                'published_at' => null,
                'is_homepage' => false,
                'show_in_navigation' => false,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $copy->save();
            foreach ($page->sections as $section) {
                $clone = $section->replicate();
                $clone->fill(['page_id' => $copy->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
                $clone->save();
            }

            return $copy->load('sections');
        });
        $this->logger->log('pages', 'page.duplicated', 'Duplicated page "'.$page->title.'".', $copy, $actor, properties: ['source_page_id' => $page->id]);

        return $copy;
    }
}
