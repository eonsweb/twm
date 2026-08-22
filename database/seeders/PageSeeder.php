<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use App\Pages\HomepageSectionSynchronizer;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(HomepageSectionSynchronizer $homepageSections): void
    {
        $actorId = User::query()->value('id');
        $definitions = [
            ['title' => 'Home', 'slug' => 'welcome', 'page_type' => 'homepage', 'template' => 'homepage', 'excerpt' => 'Welcome to Triumphant World Ministry.', 'is_homepage' => true, 'show_in_navigation' => false],
            ['title' => 'About Us', 'slug' => 'about-us', 'page_type' => 'about', 'template' => 'default', 'excerpt' => 'Learn about our church family, mission, and calling.', 'show_in_navigation' => true, 'navigation_order' => 10],
            ['title' => 'Our History', 'slug' => 'our-history', 'page_type' => 'about', 'template' => 'default', 'excerpt' => 'The story of God’s faithfulness through Triumphant World Ministry.', 'show_in_navigation' => true, 'navigation_order' => 20],
            ['title' => 'Our Beliefs', 'slug' => 'our-beliefs', 'page_type' => 'about', 'template' => 'default', 'excerpt' => 'The biblical convictions that shape our worship and ministry.', 'show_in_navigation' => true, 'navigation_order' => 30],
            ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'page_type' => 'legal', 'template' => 'legal', 'excerpt' => 'How Triumphant World Ministry handles personal information.', 'show_in_navigation' => false],
            ['title' => 'Terms and Conditions', 'slug' => 'terms-and-conditions', 'page_type' => 'legal', 'template' => 'legal', 'excerpt' => 'Terms governing use of this website.', 'show_in_navigation' => false],
        ];
        foreach ($definitions as $definition) {
            $page = Page::query()->updateOrCreate(['slug' => $definition['slug']], [...$definition, 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay(), 'created_by' => $actorId, 'updated_by' => $actorId, 'robots_index' => true, 'robots_follow' => true]);
            if ($page->is_homepage) {
                $page->sections()->firstOrCreate(['section_type' => 'hero'], ['name' => 'Homepage hero', 'heading' => 'Welcome to Triumphant World Ministry', 'subheading' => 'A place to believe, belong, and become', 'content' => '<p>Join us as we worship Jesus, grow in faith, and serve our world.</p>', 'settings' => ['primary_label' => 'Plan Your Visit', 'primary_url' => '/contact', 'secondary_label' => 'Watch Sermons', 'secondary_url' => '/sermons'], 'sort_order' => 10, 'is_visible' => true, 'created_by' => $actorId, 'updated_by' => $actorId]);
                $page->sections()->firstOrCreate(['section_type' => 'service-times'], ['name' => 'Service times', 'heading' => 'Join Us This Week', 'settings' => [], 'sort_order' => 20, 'is_visible' => true, 'created_by' => $actorId, 'updated_by' => $actorId]);
                $homepageSections->sync($page);
                $page->sections()->firstOrCreate(
                    ['section_type' => 'featured-sermons'],
                    [
                        'name' => 'Latest sermon',
                        'heading' => 'Latest Sermons',
                        'settings' => ['limit' => 3],
                        'sort_order' => 40,
                        'is_visible' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ],
                );
                $page->sections()->firstOrCreate(
                    ['section_type' => 'upcoming-events'],
                    [
                        'name' => 'Upcoming events',
                        'heading' => 'Upcoming Events',
                        'settings' => ['limit' => 3],
                        'sort_order' => 50,
                        'is_visible' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ],
                );
            }
        }
    }
}
