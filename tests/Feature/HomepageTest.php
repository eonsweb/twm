<?php

use App\MediaType;
use App\Models\Book;
use App\Models\Event;
use App\Models\EventType;
use App\Models\LeadershipAssignment;
use App\Models\Media;
use App\Models\Ministry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Sermon;
use App\Pages\HomepageSectionSynchronizer;
use App\Pages\SectionDataResolver;
use App\Sermons\ExternalMedia;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        ->assertSee('data-next-steps', false)
        ->assertSee('application/ld+json', false);
});

test('the public layout keeps the fixed navbar outside a neutral main element', function (): void {
    app(SettingManager::class)->put('branding', 'primary_color', '#245f4f');
    app(SettingManager::class)->put('branding', 'accent_color', '#c47a20');

    $response = $this->get(route('home'))->assertOk();
    $html = $response->getContent();
    $navbar = Str::between($html, '<header', '</header>');

    expect($html)
        ->toContain('<main id="public-main">')
        ->not->toContain('<main id="public-main" class="bg-gray-900')
        ->and(strpos($html, 'x-data="navbar"'))->toBeLessThan(strpos($html, '<main id="public-main">'))
        ->and(substr_count($navbar, 'background-color: #c47a20'))->toBe(2)
        ->and(substr_count($navbar, 'color: #245f4f'))->toBe(2)
        ->and($navbar)->not->toContain('bg-black')
        ->and($navbar)->not->toContain('bg-red-700');

    $response
        ->assertSee('fixed top-0 left-0 z-50', false)
        ->assertSee("isScrolled ? 'shadow-lg' : 'bg-transparent'", false)
        ->assertSee("backgroundColor: isScrolled ? '#245f4f' : 'transparent'", false)
        ->assertDontSee('bg-gray-900/95', false)
        ->assertSee('backdrop-blur-[4px]', false);

    app(SettingManager::class)->put('branding', 'primary_color', '#315d88');
    app(SettingManager::class)->put('branding', 'accent_color', '#a64073');

    $sermonsResponse = $this->get(route('public.sermons.index'))
        ->assertOk()
        ->assertSee("backgroundColor: isScrolled ? '#315d88' : 'transparent'", false)
        ->assertDontSee("backgroundColor: isScrolled ? '#245f4f' : 'transparent'", false)
        ->assertDontSee('bg-gray-900/95', false);

    $sermonsNavbar = Str::between($sermonsResponse->getContent(), '<header', '</header>');

    expect(substr_count($sermonsNavbar, 'background-color: #a64073'))->toBe(2)
        ->and(substr_count($sermonsNavbar, 'color: #315d88'))->toBe(2)
        ->and($sermonsNavbar)->not->toContain('background-color: #c47a20')
        ->and($sermonsNavbar)->not->toContain('color: #245f4f');
});

test('public index pages render a maroon overlay hero above white content', function (string $routeName): void {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('bg-church-maroon-950', false)
        ->assertSee('pt-28', false)
        ->assertSee('bg-white', false);
})->with([
    'sermons' => 'public.sermons.index',
    'books' => 'public.books.index',
    'events' => 'public.events.index',
    'ministries' => 'public.ministries.index',
    'give' => 'public.give',
    'contact' => 'public.contact',
]);

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

test('the managed featured sermon section renders a lazy thumbnail and message details', function (): void {
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
        ->assertDontSee('<iframe', false)
        ->assertSee($featured->external_thumbnail_url, false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('aspect-video', false)
        ->assertSee('data-featured-sermon', false)
        ->assertSee('Watch Message')
        ->assertSee(route('public.sermons.show', $featured), false)
        ->assertSee('Explore all sermons')
        ->assertSee(route('public.sermons.index'), false)
        ->assertSeeInOrder([$featured->title, 'Watch Message', 'Explore all sermons'], false)
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

test('the managed upcoming events section features one event and shows the next three', function (): void {
    Storage::fake('public');
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    $section = PageSection::factory()->for($page)->create([
        'name' => 'Upcoming events',
        'section_type' => 'upcoming-events',
        'heading' => 'Upcoming Events',
        'content' => null,
        'settings' => ['limit' => 24],
    ]);
    $eventType = EventType::factory()->create(['name' => 'Special Church Event', 'slug' => 'special-church-event']);
    $featuredImage = Media::factory()->create();
    Storage::disk($featuredImage->disk)->put($featuredImage->path, 'featured event artwork');
    $featured = Event::factory()->for($eventType)->published()->featured()->create([
        'title' => 'Pastors Appreciation',
        'short_description' => 'Join us for a special celebration honouring our church leadership.',
        'featured_image_id' => $featuredImage->id,
        'starts_at' => now()->addDays(10)->setTime(18, 0),
        'ends_at' => now()->addDays(10)->setTime(20, 0),
    ]);
    $soonest = Event::factory()->for($eventType)->published()->create([
        'title' => 'Prayer Gathering',
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(12, 0),
    ]);
    $allDay = Event::factory()->for($eventType)->published()->create([
        'title' => 'Community Outreach',
        'is_all_day' => true,
        'starts_at' => now()->addDays(2)->startOfDay(),
        'ends_at' => now()->addDays(2)->endOfDay(),
    ]);
    $third = Event::factory()->published()->create([
        'title' => 'Youth Conference',
        'starts_at' => now()->addDays(3)->setTime(9, 0),
        'ends_at' => null,
    ]);
    Event::factory()->published()->create([
        'title' => 'Fifth Upcoming Event',
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(4)->addHour(),
    ]);
    Event::factory()->published()->past()->create(['title' => 'Expired Church Event']);
    Event::factory()->create(['title' => 'Draft Church Event', 'starts_at' => now()->addHours(2)]);

    $resolvedEvents = app(SectionDataResolver::class)->resolve($section)['items'];

    expect($featured->fresh()->is_featured)->toBeTrue()
        ->and($resolvedEvents->pluck('id')->all())->toBe([
            $featured->id,
            $soonest->id,
            $allDay->id,
            $third->id,
        ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-upcoming-events', false)
        ->assertSee('lg:grid-cols-3', false)
        ->assertSee($featured->imageUrl(), false)
        ->assertSee($featured->short_description)
        ->assertSee('Special Church Event')
        ->assertSee('10:00 AM')
        ->assertSee('All Day')
        ->assertSeeInOrder([$featured->title, $soonest->title, $allDay->title, $third->title])
        ->assertDontSee('Fifth Upcoming Event')
        ->assertDontSee('Expired Church Event')
        ->assertDontSee('Draft Church Event')
        ->assertSee(route('public.events.show', $featured), false)
        ->assertSee(route('public.events.show', $soonest), false)
        ->assertSee(route('public.events.index'), false)
        ->assertSee('View All Events')
        ->assertSee('data-event-image', false);

    expect(substr_count($response->getContent(), 'data-upcoming-event-item'))->toBe(4);
});

test('the managed upcoming events section handles one event and an empty state', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create([
        'name' => 'Upcoming events',
        'section_type' => 'upcoming-events',
        'heading' => 'Upcoming Events',
        'content' => null,
    ]);
    $event = Event::factory()->published()->create([
        'title' => 'Only Upcoming Event',
        'featured_image' => null,
        'featured_image_id' => null,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('data-event-image-fallback', false);

    expect(substr_count($response->getContent(), 'data-upcoming-event-item'))->toBe(1);

    $event->delete();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('No upcoming events at this time.')
        ->assertSee(route('public.events.index'), false);
});

test('the first-class welcome section follows service times in the managed section order', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create(['name' => 'Service times', 'section_type' => 'service-times', 'heading' => 'Join Us at TWM', 'sort_order' => 20]);
    PageSection::factory()->for($page)->create(['name' => 'Latest sermon', 'section_type' => 'featured-sermons', 'heading' => 'Legacy Sermons', 'sort_order' => 30]);
    PageSection::factory()->for($page)->create(['name' => 'Upcoming events', 'section_type' => 'upcoming-events', 'heading' => 'Legacy Events', 'sort_order' => 40]);
    app(HomepageSectionSynchronizer::class)->sync($page);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Join Us at TWM', 'Welcome Home!', 'Legacy Sermons', 'Legacy Events']);
});

test('the ministries carousel shows every published ministry in display order', function (): void {
    Storage::fake('public');
    $image = Media::factory()->create(['path' => 'ministries/featured.jpg']);
    Storage::disk('public')->put($image->path, 'ministry artwork');
    $featured = Ministry::factory()->published()->featured()->create([
        'name' => 'Featured Ministry',
        'slug' => 'featured-ministry',
        'display_order' => 99,
        'featured_image' => 'ministries/featured.jpg',
    ]);
    $orderedMinistries = collect(range(1, 8))->map(fn (int $position): Ministry => Ministry::factory()->published()->create([
        'name' => "Ministry {$position}",
        'slug' => "ministry-{$position}",
        'display_order' => $position,
    ]));
    Ministry::factory()->scheduled()->create(['name' => 'Hidden Ministry', 'slug' => 'hidden-ministry']);
    Ministry::factory()->inactive()->create(['name' => 'Inactive Ministry', 'slug' => 'inactive-ministry']);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-ministries-section', false)
        ->assertSee('data-ministries-swiper', false)
        ->assertSee('swiper-wrapper', false)
        ->assertSee('class="swiper-slide"', false)
        ->assertSee('data-ministries-prev', false)
        ->assertSee('data-ministries-next', false)
        ->assertSee('data-ministry-image-fallback', false)
        ->assertSee($image->publicImageUrl(), false)
        ->assertSee('loading="lazy"', false)
        ->assertSee(route('public.ministries.show', $featured), false)
        ->assertSeeInOrder([$featured->name, ...$orderedMinistries->pluck('name')->all()])
        ->assertDontSee('Hidden Ministry')
        ->assertDontSee('Inactive Ministry');

    expect(substr_count($response->getContent(), 'class="swiper-slide"'))->toBe(9);
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

test('the featured book section renders its media purchase link audio sample and books route', function (): void {
    Storage::fake('public');
    $cover = Media::factory()->create([
        'name' => 'Editorial Book Cover',
        'alt_text' => 'A gold and green book cover',
        'path' => 'media/images/featured-book.jpg',
    ]);
    $audio = Media::factory()->create([
        'name' => 'Featured Book Preview',
        'media_type' => MediaType::Audio,
        'mime_type' => 'audio/mpeg',
        'extension' => 'mp3',
        'path' => 'media/audio/featured-book-preview.mp3',
    ]);
    Storage::disk('public')->put($cover->path, 'cover');
    Storage::disk('public')->put($audio->path, 'audio');
    $book = Book::factory()->published()->featured()->create([
        'title' => 'Grace for the Journey',
        'short_description' => 'A concise guide to walking faithfully through every season of life.',
        'purchase_url' => 'https://books.example.test/grace-for-the-journey',
        'media_id' => $cover->id,
        'audio_sample_media_id' => $audio->id,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-featured-book', false)
        ->assertSee($book->title)
        ->assertSee($book->short_description)
        ->assertSee($cover->publicImageUrl(), false)
        ->assertSee('A gold and green book cover')
        ->assertSee('Get Your Copy Now')
        ->assertSee($book->purchase_url, false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee("Book's Audio Sample")
        ->assertSee('data-featured-book-actions', false)
        ->assertSee('flex flex-col gap-3 sm:flex-row sm:flex-wrap lg:flex-nowrap lg:items-center', false)
        ->assertSee('x-on:click="showAudio = ! showAudio"', false)
        ->assertSee('x-show="showAudio"', false)
        ->assertSee('data-featured-book-audio-icon', false)
        ->assertSee('<audio controls preload="metadata"', false)
        ->assertSee($audio->publicUrl(), false)
        ->assertSee('type="audio/mpeg"', false)
        ->assertSee('More Books')
        ->assertSee(route('public.books.index'), false)
        ->assertSee('lg:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.25fr)]', false);

    preg_match('/<a[^>]*data-featured-book-purchase[^>]*>/', $response->getContent(), $purchaseLink);

    expect($purchaseLink[0] ?? null)
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('wire:navigate');
});

test('the featured book section omits unavailable audio and hides without a featured book', function (): void {
    $regularBook = Book::factory()->published()->create(['title' => 'Not Selected for Homepage']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-featured-book', false)
        ->assertDontSee($regularBook->title);

    $featuredBook = Book::factory()->published()->featured()->create([
        'title' => 'Selected Without Audio',
        'purchase_url' => null,
        'download_url' => null,
        'audio_sample_media_id' => null,
    ]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee($featuredBook->title)
        ->assertSee('data-featured-book-actions', false)
        ->assertSee('Get Your Copy Now')
        ->assertSee(route('public.books.show', $featuredBook), false)
        ->assertDontSee('<audio', false)
        ->assertDontSee("Book's Audio Sample");

    preg_match('/<a[^>]*data-featured-book-purchase[^>]*>/', $response->getContent(), $purchaseLink);

    expect($purchaseLink[0] ?? null)
        ->toContain('wire:navigate')
        ->not->toContain('target="_blank"')
        ->not->toContain('rel="noopener noreferrer"');
});

test('the featured book section keeps its fallback purchase CTA with or without audio', function (): void {
    Storage::fake('public');
    $audio = Media::factory()->create([
        'name' => 'Audio-only Book Preview',
        'media_type' => MediaType::Audio,
        'mime_type' => 'audio/mpeg',
        'extension' => 'mp3',
        'path' => 'media/audio/audio-only-book-preview.mp3',
    ]);
    Storage::disk('public')->put($audio->path, 'audio');
    $book = Book::factory()->published()->featured()->create([
        'title' => 'Listen Before Buying',
        'purchase_url' => null,
        'download_url' => null,
        'audio_sample_media_id' => $audio->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-featured-book-actions', false)
        ->assertSee('Get Your Copy Now')
        ->assertSee(route('public.books.show', $book), false)
        ->assertSee("Book's Audio Sample")
        ->assertSee($audio->publicUrl(), false);

    $book->update(['audio_sample_media_id' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($book->title)
        ->assertSee('data-featured-book-actions', false)
        ->assertSee('Get Your Copy Now')
        ->assertSee(route('public.books.show', $book), false)
        ->assertDontSee("Book's Audio Sample")
        ->assertDontSee('<audio', false);
});

test('managed homepages receive and render the featured book section', function (): void {
    $page = Page::factory()->published()->create(['title' => 'Managed Home', 'is_homepage' => true]);
    $book = Book::factory()->published()->featured()->create(['title' => 'Managed Homepage Book']);

    app(HomepageSectionSynchronizer::class)->sync($page);

    expect($page->sections()->where('section_type', 'featured-book')->count())->toBe(1);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-featured-book', false)
        ->assertSee($book->title);
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
        ->assertSee('Join Us at TWM')
        ->assertDontSee('Our Ministries')
        ->assertDontSee('Youth Ministry');
});

test('the homepage remains useful when all content collections are empty', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Service times will be announced soon')
        ->assertSee('No sermons are available yet.')
        ->assertSee('No upcoming events at this time.')
        ->assertDontSee('data-ministries-section', false)
        ->assertDontSee('data-featured-book', false);
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
