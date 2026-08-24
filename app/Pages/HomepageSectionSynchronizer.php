<?php

namespace App\Pages;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\PageSectionType;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\DB;

final class HomepageSectionSynchronizer
{
    public function __construct(private readonly SettingManager $settings) {}

    public function sync(Page $page): ?PageSection
    {
        if (! $page->is_homepage) {
            return null;
        }

        return DB::transaction(function () use ($page): ?PageSection {
            $homepage = Page::query()->lockForUpdate()->find($page->id);

            if (! $homepage?->is_homepage) {
                return null;
            }

            $this->ensureMinistries($homepage);
            $this->ensureFeaturedBook($homepage);

            $existing = $homepage->sections()
                ->withTrashed()
                ->where('section_type', PageSectionType::Welcome->value)
                ->oldest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $legacy = $homepage->sections()
                ->withTrashed()
                ->where('section_type', PageSectionType::WelcomeUpcomingEvent->value)
                ->oldest('id')
                ->first();

            if ($legacy) {
                if ($legacy->trashed()) {
                    return $legacy;
                }

                $legacy->update([
                    'section_type' => PageSectionType::Welcome,
                    'name' => in_array($legacy->name, [
                        'Welcome, sermon, and upcoming events',
                        'Welcome, sermon, and events',
                        'Legacy welcome band',
                    ], true) ? 'Welcome section' : $legacy->name,
                ]);

                return $legacy->refresh();
            }

            $sortOrder = $this->welcomeSortOrder($homepage);
            $homepage->sections()
                ->where('sort_order', '>=', $sortOrder)
                ->increment('sort_order', 10);

            $welcomeImageId = $this->settings->get('homepage', 'welcome_image_id');
            $welcomeImageId = filled($welcomeImageId)
                ? Media::query()->whereKey((int) $welcomeImageId)->value('id')
                : null;

            return $homepage->sections()->create([
                'section_type' => PageSectionType::Welcome,
                'name' => 'Welcome section',
                'heading' => $this->settings->get('homepage', 'welcome_heading', 'Welcome Home!'),
                'content' => $this->settings->get(
                    'homepage',
                    'welcome_message',
                    $this->settings->get('homepage', 'welcome_body'),
                ),
                'settings' => array_filter([
                    'welcome_signature' => $this->settings->get('homepage', 'welcome_signature'),
                    'welcome_pastor_name' => $this->settings->get('homepage', 'welcome_pastor_name'),
                    'welcome_pastor_role' => $this->settings->get(
                        'homepage',
                        'welcome_pastor_role',
                        $this->settings->get('homepage', 'welcome_pastor_title'),
                    ),
                    'pastor_image_alt' => $this->settings->get('homepage', 'welcome_image_alt'),
                ], fn (mixed $value): bool => filled($value)),
                'sort_order' => $sortOrder,
                'is_visible' => true,
                'background_image_id' => $welcomeImageId,
                'created_by' => $homepage->created_by,
                'updated_by' => $homepage->updated_by,
            ]);
        });
    }

    private function welcomeSortOrder(Page $homepage): int
    {
        $serviceTimesOrder = $homepage->sections()
            ->where('section_type', PageSectionType::ServiceTimes->value)
            ->value('sort_order');

        if ($serviceTimesOrder !== null) {
            return (int) $serviceTimesOrder + 10;
        }

        $heroOrder = $homepage->sections()
            ->where('section_type', PageSectionType::Hero->value)
            ->value('sort_order');

        return $heroOrder === null ? 10 : (int) $heroOrder + 10;
    }

    private function ensureFeaturedBook(Page $homepage): void
    {
        $exists = $homepage->sections()
            ->withTrashed()
            ->where('section_type', PageSectionType::FeaturedBook->value)
            ->exists();

        if ($exists) {
            return;
        }

        $sortOrder = (int) $homepage->sections()->max('sort_order') + 10;

        $homepage->sections()->create([
            'section_type' => PageSectionType::FeaturedBook,
            'name' => 'Featured book',
            'heading' => 'Featured Book',
            'settings' => [],
            'sort_order' => $sortOrder,
            'is_visible' => true,
            'created_by' => $homepage->created_by,
            'updated_by' => $homepage->updated_by,
        ]);
    }

    private function ensureMinistries(Page $homepage): void
    {
        $exists = $homepage->sections()
            ->withTrashed()
            ->where('section_type', PageSectionType::MinistriesGrid->value)
            ->exists();

        if ($exists) {
            return;
        }

        $featuredBookOrder = $homepage->sections()
            ->where('section_type', PageSectionType::FeaturedBook->value)
            ->value('sort_order');
        $sortOrder = $featuredBookOrder === null
            ? (int) $homepage->sections()->max('sort_order') + 10
            : (int) $featuredBookOrder;

        if ($featuredBookOrder !== null) {
            $homepage->sections()
                ->where('sort_order', '>=', $sortOrder)
                ->increment('sort_order', 10);
        }

        $homepage->sections()->create([
            'section_type' => PageSectionType::MinistriesGrid,
            'name' => 'Ministries carousel',
            'heading' => 'Our Ministries',
            'settings' => [],
            'sort_order' => $sortOrder,
            'is_visible' => true,
            'created_by' => $homepage->created_by,
            'updated_by' => $homepage->updated_by,
        ]);
    }
}
