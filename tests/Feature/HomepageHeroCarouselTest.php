<?php

use App\MediaType;
use App\Models\HomepageHeroSlide;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->page = Page::factory()->published()->create(['is_homepage' => true, 'content' => null]);
    $this->hero = PageSection::factory()->for($this->page)->create([
        'section_type' => 'hero', 'heading' => 'Preserved anniversary', 'subheading' => 'Twenty years',
        'content' => 'Legacy supporting text', 'settings' => ['variant' => 'anniversary', 'theme' => 'Original theme'],
    ]);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([PermissionName::PagesManageSections->value, PermissionName::MediaView->value]);
});

function carouselMedia(array $attributes = []): Media
{
    $media = Media::factory()->create($attributes);
    Storage::disk($media->disk)->put($media->path, 'media');

    return $media;
}

function carouselEditor(object $test): Testable
{
    return Livewire::actingAs($test->admin)->test('pages::pages.hero-slides', ['page' => $test->page]);
}

test('only active slides render in stored order and reactivation restores the position', function (): void {
    $first = HomepageHeroSlide::factory()->for($this->page)->create(['title' => 'First visible slide', 'sort_order' => 1]);
    $middle = HomepageHeroSlide::factory()->for($this->page)->create(['title' => 'Temporarily disabled slide', 'sort_order' => 2, 'is_active' => false]);
    $last = HomepageHeroSlide::factory()->for($this->page)->create(['title' => 'Last visible slide', 'sort_order' => 3]);
    $this->get(route('home'))->assertOk()->assertSeeInOrder([$first->title, $last->title])->assertDontSee($middle->title);
    carouselEditor($this)->call('toggle', $middle->id)->assertHasNoErrors();
    $this->get(route('home'))->assertOk()->assertSeeInOrder([$first->title, $middle->title, $last->title]);
    carouselEditor($this)->call('toggle', $middle->id);
    expect($middle->fresh()->only(['title', 'media_id', 'sort_order', 'settings']))->toBe($middle->only(['title', 'media_id', 'sort_order', 'settings']));
    expect(HomepageHeroSlide::find($middle->id))->not->toBeNull();
    expect($middle->fresh()->is_active)->toBeFalse();
});

test('schedules use inclusive boundaries and manual disabling always wins', function (): void {
    $this->travelTo(now()->startOfSecond());
    foreach ([
        ['Unrestricted slide', null, null, true, true],
        ['Current slide', now()->subDay(), now()->addDay(), true, true],
        ['Starting now slide', now(), null, true, true],
        ['Ending now slide', null, now(), true, true],
        ['Future slide', now()->addDay(), null, true, false],
        ['Expired slide', null, now()->subSecond(), true, false],
        ['Disabled scheduled slide', now()->subDay(), now()->addDay(), false, false],
    ] as [$title, $start, $end, $active, $visible]) {
        $slide = HomepageHeroSlide::factory()->for($this->page)->create(['title' => $title, 'starts_at' => $start, 'ends_at' => $end, 'is_active' => $active]);
        expect(HomepageHeroSlide::visible()->whereKey($slide->id)->exists())->toBe($visible);
    }
    $this->get(route('home'))->assertOk()->assertSee('Unrestricted slide')->assertSee('Current slide')->assertDontSee('Future slide')->assertDontSee('Expired slide')->assertDontSee('Disabled scheduled slide');
});

test('zero eligible slides preserve the legacy hero and a single slide omits pagination', function (): void {
    $this->get(route('home'))->assertOk()->assertSee('Preserved anniversary')->assertSee('Original theme')->assertDontSee('data-hero-pagination', false);
    $slide = HomepageHeroSlide::factory()->for($this->page)->create(['title' => 'Single slide', 'is_active' => false]);
    $this->get(route('home'))->assertOk()->assertSee('Preserved anniversary')->assertDontSee('Single slide');
    $slide->update(['is_active' => true]);
    $this->get(route('home'))->assertOk()->assertSee('Single slide')->assertDontSee('data-hero-pagination', false);
});

test('promotional cards use library images mobile sources and video posters without loading extra videos', function (): void {
    $image = carouselMedia(['alt_text' => 'Congregation praising God']);
    $mobile = carouselMedia(['alt_text' => 'Mobile worship photograph']);
    $poster = carouselMedia();
    $video = carouselMedia(['media_type' => MediaType::Video, 'mime_type' => 'video/mp4', 'extension' => 'mp4']);
    HomepageHeroSlide::factory()->for($this->page)->create(['media_id' => $image->id, 'mobile_media_id' => $mobile->id, 'sort_order' => 1]);
    HomepageHeroSlide::factory()->for($this->page)->create(['media_id' => $video->id, 'media_type' => 'video', 'video_poster_media_id' => $poster->id, 'sort_order' => 2]);
    $this->get(route('home'))->assertOk()->assertSee($image->publicUrl(), false)->assertSee($mobile->publicUrl(), false)
        ->assertSee('Congregation praising God')->assertSee($poster->publicImageUrl(), false)
        ->assertSee('data-messages-swiper', false)->assertDontSee('data-src="'.$video->publicUrl().'"', false)
        ->assertDontSee('<video', false);
});

test('the editor creates updates and deletes slides without deleting library assets', function (): void {
    $media = carouselMedia();
    $mobile = carouselMedia();
    $poster = carouselMedia();
    carouselEditor($this)->call('create')->set('form.title', 'Managed slide')->set('mediaIds', [$media->id])
        ->set('mobileMediaIds', [$mobile->id])->set('posterMediaIds', [$poster->id])
        ->set('form.cta_text', 'Visit')->set('form.cta_url', '/contact')->call('save')->assertHasNoErrors();
    $slide = HomepageHeroSlide::firstOrFail();
    expect($slide->media->is($media))->toBeTrue()->and($slide->mobileMedia->is($mobile))->toBeTrue()->and($slide->videoPosterMedia->is($poster))->toBeTrue();
    carouselEditor($this)->call('edit', $slide->id)->assertSet('mediaIds', [$media->id])->set('form.title', 'Updated slide')->call('save')->assertHasNoErrors();
    expect($slide->fresh()->title)->toBe('Updated slide');
    carouselEditor($this)->call('delete', $slide->id)->assertHasNoErrors();
    expect(HomepageHeroSlide::find($slide->id))->toBeNull()->and($media->fresh())->not->toBeNull();
    Storage::disk($media->disk)->assertExists($media->path);
});

test('ordering includes disabled slides and handles equal sort positions', function (): void {
    $first = HomepageHeroSlide::factory()->for($this->page)->create(['sort_order' => 0]);
    $second = HomepageHeroSlide::factory()->for($this->page)->create(['sort_order' => 0, 'is_active' => false]);
    $third = HomepageHeroSlide::factory()->for($this->page)->create(['sort_order' => 0]);
    carouselEditor($this)->call('move', $third->id, 'up')->assertHasNoErrors();
    expect(HomepageHeroSlide::orderBy('sort_order')->pluck('id')->all())->toBe([$first->id, $third->id, $second->id]);
    expect($second->fresh()->is_active)->toBeFalse();
});

test('invalid media unsafe URLs and reversed schedules are rejected', function (): void {
    $media = carouselMedia();
    carouselEditor($this)->call('create')->set('form.title', 'Invalid slide')->call('save')->assertHasErrors('mediaIds');
    carouselEditor($this)->call('create')->set('form.title', 'Invalid slide')->set('mediaIds', [$media->id])
        ->set('form.cta_url', 'javascript:alert(1)')->set('form.starts_at', '2026-12-10')->set('form.ends_at', '2026-12-01')
        ->call('save')->assertHasErrors(['form.cta_url', 'form.ends_at']);
    foreach ([['media_type' => MediaType::Document, 'mime_type' => 'application/pdf'], ['visibility' => 'private'], ['status' => 'archived'], ['media_type' => MediaType::Video, 'mime_type' => 'video/quicktime']] as $attributes) {
        $invalid = carouselMedia($attributes);
        carouselEditor($this)->call('create')->set('form.title', 'Invalid media')->set('mediaIds', [$invalid->id])->call('save')->assertHasErrors('mediaIds');
    }
    carouselEditor($this)->call('create')->set('form.title', 'Invalid ID')->set('mediaIds', [999999])->call('save')->assertHasErrors('mediaIds.0');
});

test('permissions and page ownership are enforced on every slide action', function (): void {
    Livewire::actingAs(User::factory()->create())->test('pages::pages.hero-slides', ['page' => $this->page])->assertForbidden();
    $other = HomepageHeroSlide::factory()->create();
    foreach (['edit', 'toggle', 'delete'] as $action) {
        expect(fn () => carouselEditor($this)->call($action, $other->id))->toThrow(ModelNotFoundException::class);
    }
    expect(fn () => carouselEditor($this)->call('move', $other->id, 'up'))->toThrow(ModelNotFoundException::class);
    $editor = carouselEditor($this)->call('create');
    $this->admin->revokePermissionTo(PermissionName::PagesManageSections->value);
    $editor->call('save')->assertForbidden();
});

test('the data migration preserves the anniversary presentation and legacy configuration', function (): void {
    $media = carouselMedia();
    $this->hero->update(['background_image_id' => $media->id]);
    $migration = require database_path('migrations/2026_10_04_004021_preserve_existing_homepage_hero_slides.php');
    $migration->up();
    $migration->up();
    $slide = HomepageHeroSlide::firstOrFail();
    expect(HomepageHeroSlide::count())->toBe(1)->and($slide->title)->toBe($this->hero->heading)
        ->and($slide->media_id)->toBe($media->id)->and($slide->settings['theme'])->toBe('Original theme');
    expect($this->hero->fresh()->heading)->toBe('Preserved anniversary');
    $this->get(route('home'))->assertOk()->assertSee('Preserved anniversary')->assertSee('Original theme')->assertSee('data-messages-swiper', false);
});

test('the homepage administration exposes the slide manager with library-only selection', function (): void {
    $this->actingAs($this->admin)->get(route('pages.sections', $this->page))->assertOk()->assertSee('Hero slides')->assertSee('Add Hero Slide');
    carouselEditor($this)->call('create')->assertSee('Main image or video')->assertSee('Video poster (optional)')->assertDontSee('Upload new file');
});
