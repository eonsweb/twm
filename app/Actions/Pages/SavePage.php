<?php

namespace App\Actions\Pages;

use App\Activity\ActivityLogger;
use App\Blog\HtmlSanitizer;
use App\Models\Page;
use App\Models\User;
use App\Pages\HomepageSectionSynchronizer;
use App\PageStatus;
use App\PageVisibility;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavePage
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly HomepageSectionSynchronizer $homepageSections,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?Page $page = null): Page
    {
        $creating = $page === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $page ?? Page::class);
        $page ??= new Page;
        if (! $creating && $this->wouldCreateCycle($page, $data['parent_id'] ?? null)) {
            throw ValidationException::withMessages(['form.parentId' => __('A circular page hierarchy is not allowed.')]);
        }
        if (($data['is_homepage'] ?? false) && ($data['status'] !== PageStatus::Published->value || $data['visibility'] !== PageVisibility::Public->value || blank($data['published_at']) || $data['published_at']->isFuture())) {
            throw ValidationException::withMessages(['form.isHomepage' => __('The homepage must already be published and public.')]);
        }
        if (in_array($data['status'], [PageStatus::Published->value, PageStatus::Scheduled->value], true)) {
            Gate::forUser($actor)->authorize('publish', $page);
        }
        if ($creating) {
            $data['created_by'] = $actor->id;
        }
        $data['updated_by'] = $actor->id;
        $data['slug'] = Page::uniqueSlug((string) ($data['slug'] ?: $data['title']), $page->exists ? $page->id : null);
        $data['content'] = $this->htmlSanitizer->sanitize((string) ($data['content'] ?? ''));
        $old = $creating ? [] : Arr::only($page->getAttributes(), array_keys($data));

        $saved = DB::transaction(function () use ($page, $data): Page {
            if ($data['is_homepage']) {
                Page::query()->where('is_homepage', true)->whereKeyNot($page->getKey())->lockForUpdate()->update(['is_homepage' => false]);
            }
            $page->fill($data)->save();

            return $page->refresh()->load(['parent:id,title,slug', 'updater:id,name', 'featuredImage']);
        });

        if ($saved->is_homepage) {
            $this->homepageSections->sync($saved);
        }

        $this->activityLogger->log('pages', $creating ? 'page.created' : 'page.updated', ($creating ? 'Created' : 'Updated').' page "'.$saved->title.'".', $saved, $actor, oldValues: $old, newValues: Arr::only($saved->getAttributes(), array_keys($data)));
        Cache::forget('pages.public-navigation');

        return $saved;
    }

    private function wouldCreateCycle(Page $page, int|string|null $parentId): bool
    {
        while ($parentId !== null) {
            if ((int) $parentId === $page->id) {
                return true;
            }
            $parentId = Page::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
