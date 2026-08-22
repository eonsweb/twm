<?php

use App\Models\Book;
use App\Models\Event;
use App\Models\LeadershipAssignment;
use App\Models\Ministry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Sermon;
use App\Pages\HomepageSectionSynchronizer;
use App\Sermons\ExternalMedia;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    app(SettingManager::class)->initializeDefaults();
});

test('the public homepage renders its shared layout and primary calls to action', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Main navigation')
        ->assertSee('Watch Live')
        ->assertSee('Submit Prayer Request')
        ->assertSee('Quick Links')
        ->assertSee('welcome-upcoming-event')
        ->assertSee('application/ld+json', false);
});

test('the latest publicly available sermon is shown while drafts are excluded', function (): void {
    Sermon::factory()->published()->create(['title' => 'Older Published Message', 'sermon_date' => now()->subWeek()]);
    Sermon::factory()->published()->create(['title' => 'Newest Published Message', 'sermon_date' => now()->subDay()]);
    Sermon::factory()->create(['title' => 'Private Draft Message', 'sermon_date' => now()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Newest Published Message')
        ->assertDontSee('Older Published Message')
        ->assertDontSee('Private Draft Message');
});

test('the latest sermon section renders a YouTube URL with unrelated parameters', function (): void {
    $url = 'https://www.youtube.com/watch?v=YsHMyGcDyuI&source_ve_path=MTc4NDI0';
    $media = app(ExternalMedia::class)->inspect($url);
    $sermon = Sermon::factory()->published()->create([
        'title' => 'YouTube Embed Message',
        'external_media_url' => $media['original_url'],
        'media_platform' => $media['platform'],
        'embed_url' => $media['embed_url'],
        'external_thumbnail_url' => $media['thumbnail_url'],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($sermon->title)
        ->assertSee(route('public.sermons.show', $sermon), false);
});

test('the managed featured sermon section renders the featured video and details', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create([
        'name' => 'Featured sermon',
        'section_type' => 'featured-sermons',
        'heading' => 'Featured Sermon',
        'sort_order' => 30,
    ]);
    $newest = Sermon::factory()->published()->create([
        'title' => 'Newest Regular Message',
        'sermon_date' => now()->subDay(),
    ]);
    $speaker = Person::factory()->create(['first_name' => 'Joseph', 'last_name' => 'Darko']);
    $featured = Sermon::factory()->for($speaker, 'speaker')->published()->featured()->create([
        'title' => 'Let the Fire Fall',
        'summary' => 'A powerful message about walking in faith and allowing the Holy Spirit to transform our lives.',
        'sermon_date' => now()->subWeek(),
    ]);
    Sermon::factory()->featured()->create(['title' => 'Featured Draft Message']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Latest Sermon')
        ->assertSee($featured->title)
        ->assertSee($speaker->full_name)
        ->assertSee($featured->summary)
        ->assertDontSee($newest->title)
        ->assertDontSee('Featured Draft Message')
        ->assertSee($featured->embed_url, false)
        ->assertSee('referrerpolicy="strict-origin-when-cross-origin"', false)
        ->assertSee('aspect-video', false)
        ->assertSee('lg:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]', false)
        ->assertSee('data-featured-sermon', false)
        ->assertSee('Watch Now')
        ->assertSee(route('public.sermons.show', $featured), false)
        ->assertSee('View All Sermons')
        ->assertSee(route('public.sermons.index'), false)
        ->assertSeeInOrder([$featured->embed_url, $featured->title, 'Watch Now', 'View All Sermons'], false)
        ->assertDontSee('<video', false)
        ->assertDontSee('Sermon Series')
        ->assertDontSee('Sermon Topics');
});

test('the featured sermon presentation omits an unavailable speaker', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    $section = PageSection::factory()->for($page)->create([
        'section_type' => 'featured-sermons',
        'heading' => 'Featured Sermon',
    ]);
    $sermon = Sermon::factory()->published()->create(['title' => 'A Grace-Filled Message']);
    $sermon->setRelation('speaker', null);

    $html = Blade::render(
        '<x-public.page-section :section="$section" :data="[\'items\' => collect([$sermon])]" />',
        compact('section', 'sermon'),
    );

    expect($html)->toContain('A Grace-Filled Message')
        ->not->toContain('Speaker:');
});

test('a managed featured sermon section without content remains safe', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create([
        'name' => 'Featured sermon',
        'section_type' => 'featured-sermons',
        'heading' => 'Featured Sermon',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('No sermons are available yet.')
        ->assertDontSee('<iframe', false);
});

test('a hidden featured sermon section remains absent from the managed homepage', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create([
        'name' => 'Hidden featured sermon',
        'section_type' => 'featured-sermons',
        'heading' => 'Hidden Sermon Heading',
        'is_visible' => false,
    ]);
    Sermon::factory()->published()->featured()->create(['title' => 'Hidden Featured Message']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Hidden Sermon Heading')
        ->assertDontSee('Hidden Featured Message');
});

test('upcoming published events are ordered and past events are excluded', function (): void {
    Event::factory()->published()->create(['title' => 'Later Gathering', 'starts_at' => now()->addDays(8), 'ends_at' => now()->addDays(8)->addHour()]);
    Event::factory()->published()->create(['title' => 'Third Gathering', 'starts_at' => now()->addDays(6), 'ends_at' => now()->addDays(6)->addHour()]);
    Event::factory()->published()->create(['title' => 'Next Gathering', 'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHour()]);
    Event::factory()->published()->create(['title' => 'Soon Gathering', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    Event::factory()->published()->past()->create(['title' => 'Past Gathering']);
    Event::factory()->create(['title' => 'Draft Gathering', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Soon Gathering', 'Next Gathering', 'Third Gathering'])
        ->assertDontSee('Later Gathering')
        ->assertDontSee('Past Gathering')
        ->assertDontSee('Draft Gathering');
});

test('the first-class welcome section follows service times in the managed section order', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create(['name' => 'Service times', 'section_type' => 'service-times', 'heading' => 'Join Us This Week', 'sort_order' => 20]);
    PageSection::factory()->for($page)->create(['name' => 'Latest sermon', 'section_type' => 'featured-sermons', 'heading' => 'Legacy Sermons', 'sort_order' => 30]);
    PageSection::factory()->for($page)->create(['name' => 'Upcoming events', 'section_type' => 'upcoming-events', 'heading' => 'Legacy Events', 'sort_order' => 40]);
    app(HomepageSectionSynchronizer::class)->sync($page);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Join Us This Week', 'Welcome Home!', 'Legacy Sermons', 'Legacy Events']);
});

test('published ministries are shown and scheduled ministries are excluded', function (): void {
    Ministry::factory()->published()->create(['name' => 'Prayer Ministry', 'slug' => 'prayer-ministry']);
    Ministry::factory()->scheduled()->create(['name' => 'Hidden Ministry', 'slug' => 'hidden-ministry']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Prayer Ministry')
        ->assertDontSee('Hidden Ministry');
});

test('the administrator-selected public leader is used for the welcome section', function (): void {
    $leader = Person::factory()->create(['first_name' => 'Joseph', 'last_name' => 'Darko']);
    LeadershipAssignment::factory()->for($leader)->create(['display_title' => 'Founder & Senior Pastor']);
    Person::factory()->create(['first_name' => 'Another', 'last_name' => 'Leader']);

    app(SettingManager::class)->put('homepage', 'welcome_leader_id', $leader->id);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Joseph')
        ->assertSee('Darko')
        ->assertSee('Founder &amp; Senior Pastor', false)
        ->assertDontSee('Another Leader');
});

test('the featured available book is displayed', function (): void {
    Book::factory()->published()->create(['title' => 'Regular Resource']);
    Book::factory()->published()->featured()->create(['title' => 'Grace Upon Grace']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Grace Upon Grace')
        ->assertDontSee('Regular Resource');
});

test('configured testimonials render safely', function (): void {
    app(SettingManager::class)->put('homepage', 'testimonials', [[
        'quote' => 'Lives are changed through the Word.',
        'name' => 'Nana Ama',
        'role' => 'Member',
        'enabled' => true,
    ]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Lives are changed through the Word.')
        ->assertSee('Nana Ama');
});

test('administrators can disable optional homepage sections', function (): void {
    app(SettingManager::class)->put('homepage', 'enabled_sections', ['services', 'welcome']);
    Ministry::factory()->published()->create(['name' => 'Youth Ministry', 'slug' => 'youth-ministry']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Join Us This Week')
        ->assertDontSee('Our Ministries')
        ->assertDontSee('Youth Ministry');
});

test('the homepage remains useful when all content collections are empty', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Service times will be announced soon')
        ->assertSee('No sermons available yet.')
        ->assertSee('No upcoming events at the moment.')
        ->assertSee('Featured resources will be available here soon');
});

test('the homepage uses a bounded number of database queries', function (): void {
    Sermon::factory()->published()->create();
    Event::factory()->published()->count(3)->create();
    Ministry::factory()->published()->count(7)->create();
    Book::factory()->published()->featured()->create();

    app(SettingManager::class)->forgetGroup('general');
    app(SettingManager::class)->forgetGroup('homepage');
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('home'))->assertOk();

    expect($queries)->toBeLessThanOrEqual(60);
});

test('existing administration routes remain protected', function (): void {
    $this->get(route('media.index'))->assertRedirect(route('login'));
});
