<?php

use App\MediaType;
use App\Models\Event;
use App\Models\Media;
use App\Models\Ministry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\ServiceSchedule;
use App\Models\User;
use App\PermissionName;
use App\Settings\SettingManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
    app(SettingManager::class)->initializeDefaults();
    $this->page = Page::factory()->published()->create(['is_homepage' => true, 'content' => null]);
    $this->hero = PageSection::factory()->for($this->page)->create([
        'section_type' => 'hero', 'heading' => 'The Land of Overflow', 'subheading' => 'Welcome to TWM',
        'content' => null, 'settings' => [], 'sort_order' => 10,
    ]);
});

function redesignMedia(array $attributes = []): Media
{
    $media = Media::factory()->create($attributes);
    Storage::disk($media->disk)->put($media->path, 'fixture media');

    return $media;
}

test('homepage navigation and footer survive serialized cache reads and legacy cached objects', function (): void {
    config(['cache.default' => 'database']);
    $about = Page::factory()->published()->create(['title' => 'Our Church Story', 'is_homepage' => false, 'show_in_navigation' => true]);
    $schedule = ServiceSchedule::factory()->create(['name' => 'Sunday Worship from Cache', 'is_active' => true]);
    Cache::put('pages.public-navigation', collect([$about]));
    Cache::put('system-settings.public.service-schedules', collect([$schedule]));

    foreach (range(1, 2) as $request) {
        $this->get(route('home'))->assertOk()
            ->assertSee($schedule->name);
    }

    expect(Cache::get('pages.public-navigation'))->toBeArray();
    expect(Cache::get('pages.public-navigation')[0]['slug'])->toBe($about->slug);
    expect(Cache::get('system-settings.public.service-schedules'))->toBeArray();
});

test('cinematic hero uses one library video with a poster and accessible playback control', function (): void {
    $video = redesignMedia(['media_type' => MediaType::Video, 'mime_type' => 'video/mp4', 'extension' => 'mp4']);
    $poster = redesignMedia();
    $this->hero->update(['settings' => ['hero_video_media_id' => $video->id, 'hero_poster_media_id' => $poster->id]]);
    $response = $this->get(route('home'))->assertOk()
        ->assertSee('data-src="'.$video->publicUrl().'"', false)
        ->assertSee('poster="'.$poster->publicImageUrl().'"', false)
        ->assertSee('autoplay muted loop playsinline preload="metadata"', false)
        ->assertSee('data-video-toggle', false);
    expect(substr_count($response->getContent(), '<video '))->toBe(1)
        ->and(substr_count($response->getContent(), '<h1 '))->toBe(1);
});

test('unavailable video uses the poster and missing media uses the branded fallback', function (array $attributes): void {
    $video = redesignMedia(['media_type' => MediaType::Video, 'mime_type' => 'video/mp4', ...$attributes]);
    $poster = redesignMedia();
    $this->hero->update(['settings' => ['hero_video_media_id' => $video->id, 'hero_poster_media_id' => $poster->id]]);
    $this->get(route('home'))->assertOk()->assertDontSee('<video ', false)->assertSee($poster->publicImageUrl(), false);
    $poster->delete();
    $this->get(route('home'))->assertOk()->assertDontSee('data-hero-poster', false)->assertSee('The Land of Overflow');
})->with(['private' => [['visibility' => 'private']], 'archived' => [['status' => 'archived']], 'unsupported' => [['mime_type' => 'video/quicktime']]]);

test('deleted video files do not generate broken video sources', function (): void {
    $video = redesignMedia(['media_type' => MediaType::Video, 'mime_type' => 'video/mp4']);
    $this->hero->update(['settings' => ['hero_video_media_id' => $video->id]]);
    Storage::disk($video->disk)->delete($video->path);
    $this->get(route('home'))->assertOk()->assertDontSee('<video ', false);
});

test('default watch action follows livestream settings and preserves explicit custom actions', function (): void {
    app(SettingManager::class)->put('social', 'livestream_enabled', true);
    app(SettingManager::class)->put('social', 'livestream_url', 'https://example.org/twm-live');
    $response = $this->get(route('home'))->assertOk()->assertSee('href="#visit"', false);
    expect($response->getContent())->toMatch('/class="twm-button twm-button-accent" href="https:\/\/example.org\/twm-live"/');
    $this->hero->update(['settings' => ['primary_url' => '/events', 'primary_label' => 'Join the celebration']]);
    $this->get(route('home'))->assertOk()->assertSee('href="/events"', false)->assertSee('Join the celebration');
});

test('homepage editor saves library video poster and next step images without upload controls', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo([PermissionName::PagesManageSections->value, PermissionName::MediaView->value]);
    $video = redesignMedia(['media_type' => MediaType::Video, 'mime_type' => 'video/mp4']);
    $image = redesignMedia();
    $editor = Livewire::actingAs($admin)->test('pages::pages.sections', ['page' => $this->page]);
    $editor->call('edit', $this->hero->id)->set('sectionMediaIds.hero_video', [$video->id])
        ->set('sectionMediaIds.hero_poster', [$image->id])->call('save')->assertHasNoErrors();
    expect($this->hero->fresh()->settings)->toMatchArray(['hero_video_media_id' => $video->id, 'hero_poster_media_id' => $image->id]);
    $editor->call('edit', $this->hero->id)->assertSet('sectionMediaIds.hero_video', [$video->id]);
    $editor->call('resetForm')->set('sectionType', 'next-steps')->set('name', 'Next Steps')
        ->set('sectionMediaIds.salvation', [$image->id])->call('save')->assertHasNoErrors();
    $this->get(route('home'))->assertOk()->assertSee($image->publicImageUrl(), false)->assertSee('data-next-steps', false);
});

test('homepage media picker rejects invalid video selections', function (array $attributes): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo([PermissionName::PagesManageSections->value, PermissionName::MediaView->value]);
    $media = redesignMedia($attributes);
    Livewire::actingAs($admin)->test('pages::pages.sections', ['page' => $this->page])
        ->call('edit', $this->hero->id)->set('sectionMediaIds.hero_video', [$media->id])
        ->call('save')->assertHasErrors('sectionMediaIds.hero_video');
})->with(['image' => [[]], 'unsupported video' => [['media_type' => MediaType::Video, 'mime_type' => 'video/quicktime']]]);

test('event library image venue sermon scripture and database schedules render together', function (): void {
    foreach (['service-times', 'upcoming-events', 'featured-sermons'] as $type) {
        PageSection::factory()->for($this->page)->create(['section_type' => $type]);
    }
    $image = redesignMedia();
    Event::factory()->published()->create(['title' => 'Community worship', 'featured_image_id' => $image->id, 'venue_name' => 'TWM Worship Centre']);
    Sermon::factory()->published()->featured()->create(['title' => 'Living in faith', 'scripture_reference' => 'Hebrews 11:1']);
    ServiceSchedule::factory()->create(['name' => 'Database Sunday Service', 'is_active' => true]);
    $this->get(route('home'))->assertOk()->assertSee($image->publicImageUrl(), false)
        ->assertSee('TWM Worship Centre')->assertSee('Hebrews 11:1')->assertSee('Database Sunday Service')->assertDontSee('<iframe', false);
});

test('who we are reuses the published about page', function (): void {
    $about = Page::factory()->published()->create(['slug' => 'about-us', 'is_homepage' => false, 'excerpt' => 'Our TWM story and purpose.']);
    PageSection::factory()->for($this->page)->create(['section_type' => 'welcome', 'content' => null]);
    $this->get(route('home'))->assertOk()->assertSee(route('public.pages.show', $about), false)->assertSee('More About TWM');
});

test('redesign migration arranges existing sections without duplicating content or enabling hidden sections', function (): void {
    foreach (['service-times', 'welcome', 'featured-sermons', 'upcoming-events', 'ministries-grid', 'prayer-giving'] as $index => $type) {
        PageSection::factory()->for($this->page)->create(['section_type' => $type, 'sort_order' => ($index + 2) * 10, 'is_visible' => $type !== 'prayer-giving']);
    }
    $original = $this->hero->fresh()->only(['heading', 'settings', 'is_visible']);
    $migration = require database_path('migrations/2026_10_05_002517_arrange_existing_homepage_redesign_sections.php');
    $migration->up();
    $migration->up();
    expect($this->page->sections()->pluck('section_type')->map(fn ($type) => $type->value)->all())
        ->toBe(['hero', 'welcome', 'service-times', 'next-steps', 'upcoming-events', 'ministries-grid', 'featured-sermons', 'prayer-giving']);
    expect($this->hero->fresh()->only(['heading', 'settings', 'is_visible']))->toBe($original);
    expect($this->page->sections()->where('section_type', 'prayer-giving')->first()->is_visible)->toBeFalse();
    $this->get(route('home'))->assertOk()->assertDontSee('data-prayer-giving', false);
});

test('homepage ministry and sermon artwork is selected from the media library', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo([PermissionName::PagesManageSections->value, PermissionName::MediaView->value]);
    $ministry = Ministry::factory()->published()->create(['featured_image' => 'legacy/unregistered.jpg']);
    $sermon = Sermon::factory()->published()->create(['thumbnail_path' => 'legacy/sermon.jpg', 'external_thumbnail_url' => null]);
    $ministryImage = redesignMedia();
    $sermonImage = redesignMedia();
    $ministries = PageSection::factory()->for($this->page)->create(['section_type' => 'ministries-grid', 'settings' => []]);
    $sermons = PageSection::factory()->for($this->page)->create(['section_type' => 'featured-sermons', 'settings' => []]);
    $this->get(route('home'))->assertOk()->assertDontSee('legacy/unregistered.jpg', false)->assertDontSee('legacy/sermon.jpg', false);
    $editor = Livewire::actingAs($admin)->test('pages::pages.sections', ['page' => $this->page]);
    $editor->call('edit', $ministries->id)->set('sectionMediaIds.ministry_'.$ministry->id, [$ministryImage->id])->call('save')->assertHasNoErrors();
    $editor->call('edit', $sermons->id)->set('sectionMediaIds.sermon_image', [$sermonImage->id])->call('save')->assertHasNoErrors();
    $this->get(route('home'))->assertOk()->assertSee($ministryImage->publicImageUrl(), false)->assertSee($sermonImage->publicImageUrl(), false);
    $ministryImage->update(['visibility' => 'private']);
    $sermonImage->delete();
    $this->get(route('home'))->assertOk()->assertDontSee(Storage::disk($ministryImage->disk)->url($ministryImage->path), false)
        ->assertDontSee(Storage::disk($sermonImage->disk)->url($sermonImage->path), false);
});

test('library-backed legacy ministry paths are reused without a second media selection', function (): void {
    $image = redesignMedia();
    $ministry = Ministry::factory()->published()->create(['featured_image' => $image->path]);
    PageSection::factory()->for($this->page)->create(['section_type' => 'ministries-grid', 'settings' => []]);
    $this->get(route('home'))->assertOk()->assertSee($image->publicImageUrl(), false)->assertSee($ministry->name);
});

test('legacy welcome sections retain library artwork and the about page link', function (): void {
    $portrait = redesignMedia();
    $thumbnail = redesignMedia();
    $leader = Person::factory()->create(['is_active' => true, 'is_public' => true, 'photo_path' => $portrait->path]);
    app(SettingManager::class)->put('homepage', 'welcome_leader_id', $leader->id);
    $about = Page::factory()->published()->create(['slug' => 'about-us', 'is_homepage' => false]);
    Sermon::factory()->published()->create(['thumbnail_path' => $thumbnail->path, 'external_thumbnail_url' => null]);
    PageSection::factory()->for($this->page)->create(['section_type' => 'welcome-upcoming-event', 'settings' => []]);

    $this->get(route('home'))->assertOk()
        ->assertSee($portrait->publicImageUrl(), false)
        ->assertSee($thumbnail->publicImageUrl(), false)
        ->assertSee(route('public.pages.show', $about), false);

    $portrait->update(['visibility' => 'private']);
    $thumbnail->update(['status' => 'archived']);
    $this->get(route('home'))->assertOk()
        ->assertDontSee(Storage::disk('public')->url($portrait->path), false)
        ->assertDontSee(Storage::disk('public')->url($thumbnail->path), false);
});
