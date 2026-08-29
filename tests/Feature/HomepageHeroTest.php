<?php

use App\MediaType;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Pages\HomepageSectionSynchronizer;
use App\PageSectionType;
use App\PermissionName;
use App\Settings\SettingManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function homepageHeroPage(): Page
{
    return Page::factory()->published()->create([
        'title' => 'Triumphant World Ministry',
        'is_homepage' => true,
        'content' => null,
    ]);
}

function homepageHeroAdministrator(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        PermissionName::PagesManageSections->value,
        PermissionName::MediaView->value,
    ]);

    return $user;
}

function homepageHeroImage(array $attributes = []): Media
{
    $media = Media::factory()->create($attributes);
    Storage::disk($media->disk)->put($media->path, 'hero-image');

    return $media;
}

function saveHomepageHero(User $administrator, Page $page, array $overrides = []): void
{
    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('sectionType', 'hero')
        ->set('name', 'Homepage hero')
        ->set('heading', 'Welcome Home')
        ->set('subheading', 'Believe, belong, become')
        ->set('content', '<p>Join us this Sunday.</p>')
        ->set('heroVariant', 'default')
        ->set('heroPrimaryLabel', 'Plan Your Visit')
        ->set('heroPrimaryUrl', '/contact')
        ->set('settings', '{}')
        ->set('backgroundMediaIds', $overrides['backgroundMediaIds'] ?? [])
        ->set('backgroundAlt', $overrides['backgroundAlt'] ?? '')
        ->call('save')
        ->assertHasNoErrors();
}

test('an authorized administrator can access the homepage sections editor', function (): void {
    $page = homepageHeroPage();

    $this->actingAs(homepageHeroAdministrator())
        ->get(route('pages.sections', $page))
        ->assertOk()
        ->assertSee('Add section');
});

test('administrators can configure and reload the anniversary hero with media library assets', function (): void {
    $page = homepageHeroPage();
    $administrator = homepageHeroAdministrator();
    $background = homepageHeroImage(['name' => 'Anniversary congregation']);
    $emblem = homepageHeroImage(['name' => '20 years anniversary emblem']);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('sectionType', 'hero')
        ->set('name', 'Homepage hero')
        ->set('heading', '20th Anniversary')
        ->set('subheading', 'CELEBRATING 20 YEARS')
        ->set('content', '<p>We celebrate two decades of grace.</p>')
        ->set('heroVariant', 'anniversary')
        ->set('heroAnniversaryNumber', '20')
        ->set('heroAnniversaryUnit', 'YEARS')
        ->set('heroScriptHeading', 'Celebration')
        ->set('heroTheme', 'Your Faithfulness and Grace Has Brought Us This Far')
        ->set('heroPrimaryLabel', 'Join the Celebration')
        ->set('heroPrimaryUrl', '/events/anniversary')
        ->set('heroSecondaryLabel', 'View Anniversary Events')
        ->set('heroSecondaryUrl', '/events?type=anniversary')
        ->set('heroEmblemMediaIds', [$emblem->id])
        ->set('backgroundMediaIds', [$background->id])
        ->set('backgroundAlt', 'The anniversary congregation')
        ->set('settings', '{"show_scroll_indicator":false}')
        ->call('save')
        ->assertHasNoErrors();

    $section = $page->sections()->where('section_type', 'hero')->firstOrFail();

    expect($section->background_image_id)->toBe($background->id)
        ->and($section->settings)->toMatchArray([
            'variant' => 'anniversary',
            'anniversary_number' => '20',
            'anniversary_unit' => 'YEARS',
            'script_heading' => 'Celebration',
            'theme' => 'Your Faithfulness and Grace Has Brought Us This Far',
            'emblem_media_id' => $emblem->id,
            'primary_label' => 'Join the Celebration',
            'primary_url' => '/events/anniversary',
            'secondary_label' => 'View Anniversary Events',
            'secondary_url' => '/events?type=anniversary',
            'show_scroll_indicator' => false,
        ]);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('edit', $section->id)
        ->assertSee('Variant')
        ->assertSee('Script heading')
        ->assertSee('Anniversary theme')
        ->assertSee('Anniversary emblem')
        ->assertSee('Primary CTA label')
        ->assertSee('Secondary CTA URL')
        ->assertSet('heroVariant', 'anniversary')
        ->assertSet('heroScriptHeading', 'Celebration')
        ->assertSet('heroTheme', 'Your Faithfulness and Grace Has Brought Us This Far')
        ->assertSet('heroEmblemMediaIds', [$emblem->id])
        ->assertSet('backgroundMediaIds', [$background->id]);
});

test('the public anniversary hero resolves content media and dynamic brand colors', function (): void {
    app(SettingManager::class)->initializeDefaults();
    app(SettingManager::class)->put('branding', 'primary_color', '#572033');
    app(SettingManager::class)->put('branding', 'accent_color', '#f2c94c');

    $page = homepageHeroPage();
    $administrator = homepageHeroAdministrator();
    $background = homepageHeroImage(['name' => 'Anniversary group photograph']);
    $emblem = homepageHeroImage(['name' => 'Anniversary emblem', 'alt_text' => '20 years emblem']);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('sectionType', 'hero')
        ->set('name', 'Homepage hero')
        ->set('heading', '20th Anniversary')
        ->set('subheading', 'CELEBRATING 20 YEARS')
        ->set('content', '<p>Twenty years of faithfulness.</p>')
        ->set('heroVariant', 'anniversary')
        ->set('heroAnniversaryNumber', '20')
        ->set('heroAnniversaryUnit', 'YEARS')
        ->set('heroScriptHeading', 'Celebration')
        ->set('heroTheme', 'Your Faithfulness and Grace Has Brought Us This Far')
        ->set('heroPrimaryLabel', 'Join the Celebration')
        ->set('heroPrimaryUrl', '/events/celebration')
        ->set('heroSecondaryLabel', 'View Anniversary Events')
        ->set('heroSecondaryUrl', '/events')
        ->set('heroEmblemMediaIds', [$emblem->id])
        ->set('backgroundMediaIds', [$background->id])
        ->set('settings', '{}')
        ->call('save')
        ->assertHasNoErrors();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('20th Anniversary')
        ->assertSee('Celebration')
        ->assertSee('Your Faithfulness and Grace Has Brought Us This Far')
        ->assertSee('Twenty years of faithfulness.')
        ->assertSee('Join the Celebration')
        ->assertSee('View Anniversary Events')
        ->assertSee(Storage::disk('public')->url($background->path), false)
        ->assertSee(Storage::disk('public')->url($emblem->path), false)
        ->assertSee('alt="20 years emblem"', false)
        ->assertSee('--hero-primary: #572033', false)
        ->assertSee('--hero-accent: #f2c94c', false)
        ->assertSee('overflow-hidden', false)
        ->assertSee('flex-col', false);
});

test('an unauthorized user cannot access or update homepage hero settings', function (): void {
    $page = homepageHeroPage();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('pages.sections', $page))->assertForbidden();
    Livewire::actingAs($outsider)
        ->test('pages::pages.sections', ['page' => $page])
        ->assertForbidden();
});

test('an available media library image can be selected and persisted', function (): void {
    $page = homepageHeroPage();
    $image = homepageHeroImage(['name' => 'Sunday Worship']);

    saveHomepageHero(homepageHeroAdministrator(), $page, [
        'backgroundMediaIds' => [$image->id],
        'backgroundAlt' => 'Congregation worshipping together',
    ]);

    $section = $page->sections()->where('section_type', 'hero')->firstOrFail();

    expect($section->background_image_id)->toBe($image->id)
        ->and($section->settings)->toHaveKey('background_alt', 'Congregation worshipping together');
});

test('non-image media and arbitrary media ids are rejected', function (): void {
    $page = homepageHeroPage();
    $administrator = homepageHeroAdministrator();
    $document = Media::factory()->create([
        'media_type' => MediaType::Document,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
    ]);
    Storage::disk($document->disk)->put($document->path, 'document');

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('sectionType', 'hero')
        ->set('name', 'Homepage hero')
        ->set('settings', '{}')
        ->set('backgroundMediaIds', [$document->id])
        ->call('save')
        ->assertHasErrors('backgroundMediaIds');

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('sectionType', 'hero')
        ->set('name', 'Homepage hero')
        ->set('settings', '{}')
        ->set('backgroundMediaIds', [999999])
        ->call('save')
        ->assertHasErrors('backgroundMediaIds.0');
});

test('the selected background and alt text render on the public homepage', function (): void {
    $page = homepageHeroPage();
    $image = homepageHeroImage([
        'name' => 'Church Gathering',
        'alt_text' => 'Media library fallback text',
    ]);
    saveHomepageHero(homepageHeroAdministrator(), $page, [
        'backgroundMediaIds' => [$image->id],
        'backgroundAlt' => 'Hero-specific worship alt text',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($image->path), false)
        ->assertSee('alt="Hero-specific worship alt text"', false)
        ->assertSee('Welcome Home')
        ->assertSee('Join us this Sunday.')
        ->assertSee('min-h-screen', false)
        ->assertSee('fetchpriority="high"', false);
});

test('the exact gradient remains when no hero image is selected', function (): void {
    $page = homepageHeroPage();
    saveHomepageHero(homepageHeroAdministrator(), $page);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('bg-[linear-gradient(to_right,rgb(195,20,50),rgb(36,11,54))]', false)
        ->assertSee('opacity-95', false)
        ->assertDontSee('<img', false);
});

test('missing and deleted hero media do not render a broken image', function (): void {
    $page = homepageHeroPage();
    $image = homepageHeroImage();
    saveHomepageHero(homepageHeroAdministrator(), $page, ['backgroundMediaIds' => [$image->id]]);

    Storage::disk('public')->delete($image->path);
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(Storage::disk('public')->url($image->path), false)
        ->assertSee('opacity-95', false);

    $image->delete();
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(Storage::disk('public')->url($image->path), false)
        ->assertSee('Welcome Home');
});

test('removing the selected image clears the relationship', function (): void {
    $page = homepageHeroPage();
    $administrator = homepageHeroAdministrator();
    $image = homepageHeroImage();
    saveHomepageHero($administrator, $page, ['backgroundMediaIds' => [$image->id]]);
    $section = $page->sections()->where('section_type', 'hero')->firstOrFail();

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('edit', $section->id)
        ->set('backgroundMediaIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($section->refresh()->background_image_id)->toBeNull();
});

test('administrative placeholder content is never rendered publicly', function (): void {
    $placeholder = '<p>Content for this page can be managed from the Pages administration module.</p>';
    $homepage = homepageHeroPage();
    saveHomepageHero(homepageHeroAdministrator(), $homepage);
    $homepage->update(['content' => $placeholder]);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertDontSee('Content for this page can be managed from the Pages administration module.');

    $publicPage = Page::factory()->published()->create([
        'slug' => 'placeholder-page',
        'content' => $placeholder,
        'is_homepage' => false,
    ]);

    $this->get(route('public.pages.show', $publicPage))
        ->assertSuccessful()
        ->assertDontSee('Content for this page can be managed from the Pages administration module.');

    $homepage->update(['content' => '<p>Welcome to our church family.</p>']);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('Welcome to our church family.');
});

test('the homepage editor exposes a welcome section without duplication controls', function (): void {
    $page = homepageHeroPage();

    $this->actingAs(homepageHeroAdministrator())
        ->get(route('pages.sections', $page))
        ->assertOk()
        ->assertSee('Welcome section')
        ->assertSee('Welcome')
        ->assertSee('Welcome Home!')
        ->assertSee('Move section up')
        ->assertSee('Move section down')
        ->assertSee('Hide section')
        ->assertSee('Edit section')
        ->assertSee('Delete section')
        ->assertDontSee('Duplicate section')
        ->assertDontSee('document-duplicate');
});

test('administrators can save and reload all welcome section fields from the media library', function (): void {
    $page = homepageHeroPage();
    $administrator = homepageHeroAdministrator();
    $image = homepageHeroImage([
        'name' => 'Founder portrait',
        'alt_text' => 'The founder of Triumphant World Ministry',
    ]);

    $section = app(HomepageSectionSynchronizer::class)->sync($page);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('edit', $section->id)
        ->set('heading', 'You Are Welcome Here')
        ->set('content', "First welcome paragraph.\n\nSecond welcome paragraph.")
        ->set('welcomeSignature', 'Joseph J. Darko')
        ->set('welcomePastorName', 'Prophet Joseph John Darko')
        ->set('welcomePastorRole', 'Founder & Lead Pastor')
        ->set('backgroundMediaIds', [$image->id])
        ->set('backgroundAlt', 'Prophet Joseph John Darko welcoming visitors')
        ->call('save')
        ->assertHasNoErrors();

    $section = $page->sections()->where('section_type', 'welcome')->firstOrFail();

    expect($section->heading)->toBe('You Are Welcome Here')
        ->and($section->content)->toContain('First welcome paragraph.')
        ->and($section->background_image_id)->toBe($image->id)
        ->and($section->settings)->toMatchArray([
            'welcome_signature' => 'Joseph J. Darko',
            'welcome_pastor_name' => 'Prophet Joseph John Darko',
            'welcome_pastor_role' => 'Founder & Lead Pastor',
            'pastor_image_alt' => 'Prophet Joseph John Darko welcoming visitors',
        ]);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('edit', $section->id)
        ->assertSet('heading', 'You Are Welcome Here')
        ->assertSet('welcomeSignature', 'Joseph J. Darko')
        ->assertSet('welcomePastorName', 'Prophet Joseph John Darko')
        ->assertSet('welcomePastorRole', 'Founder & Lead Pastor')
        ->assertSet('backgroundMediaIds', [$image->id]);
});

test('the managed welcome section renders after services and omits missing optional fields', function (): void {
    $page = homepageHeroPage();
    $image = homepageHeroImage(['alt_text' => 'Founder greeting the congregation']);

    PageSection::factory()->for($page)->create([
        'name' => 'Service times',
        'section_type' => 'service-times',
        'heading' => 'Join Us This Week',
        'sort_order' => 20,
    ]);
    PageSection::factory()->for($page)->create([
        'name' => 'Welcome Section',
        'section_type' => 'welcome',
        'heading' => 'Welcome Home, Friend',
        'content' => "We are delighted to worship with you.\n\nThere is a place for you here.",
        'settings' => [
            'welcome_signature' => 'Joseph Darko',
            'welcome_pastor_name' => 'Prophet Joseph John Darko',
            'welcome_pastor_role' => null,
        ],
        'background_image_id' => $image->id,
        'sort_order' => 30,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Join Us This Week', 'Welcome Home, Friend'])
        ->assertSee('We are delighted to worship with you.')
        ->assertSee('There is a place for you here.')
        ->assertSee('whitespace-pre-line', false)
        ->assertSee('Joseph Darko')
        ->assertSee('Prophet Joseph John Darko')
        ->assertSee(Storage::disk('public')->url($image->path), false)
        ->assertSee('alt="Founder greeting the congregation"', false)
        ->assertDontSee('Founder &amp; Lead Pastor', false);
});

test('a hidden managed welcome section is not rendered', function (): void {
    $page = homepageHeroPage();
    PageSection::factory()->for($page)->create([
        'name' => 'Service times',
        'section_type' => 'service-times',
        'heading' => 'Join Us This Week',
        'sort_order' => 20,
    ]);
    PageSection::factory()->for($page)->create([
        'name' => 'Welcome Section',
        'section_type' => 'welcome',
        'heading' => 'Hidden Pastoral Welcome',
        'is_visible' => false,
        'sort_order' => 30,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Hidden Pastoral Welcome');
});

test('homepage welcome synchronization is idempotent and inserts after service times', function (): void {
    app(SettingManager::class)->initializeDefaults();
    app(SettingManager::class)->put('homepage', 'welcome_heading', 'A Preserved Welcome');
    app(SettingManager::class)->put('homepage', 'welcome_body', 'This message came from the existing homepage settings.');
    app(SettingManager::class)->put('homepage', 'welcome_signature', 'Pastor Signature');

    $page = homepageHeroPage();
    PageSection::factory()->for($page)->create(['name' => 'Homepage hero', 'section_type' => 'hero', 'sort_order' => 10]);
    PageSection::factory()->for($page)->create(['name' => 'Service times', 'section_type' => 'service-times', 'sort_order' => 20]);
    PageSection::factory()->for($page)->create(['name' => 'Latest sermon', 'section_type' => 'featured-sermons', 'sort_order' => 30]);
    PageSection::factory()->for($page)->create(['name' => 'Upcoming events', 'section_type' => 'upcoming-events', 'sort_order' => 40]);
    $synchronizer = app(HomepageSectionSynchronizer::class);

    $first = $synchronizer->sync($page);
    $second = $synchronizer->sync($page);
    $sections = $page->sections()->orderBy('sort_order')->get();

    expect($first->is($second))->toBeTrue()
        ->and($sections->where('section_type', PageSectionType::Welcome))->toHaveCount(1)
        ->and($sections->where('section_type', PageSectionType::MinistriesGrid))->toHaveCount(1)
        ->and($sections->where('section_type', PageSectionType::FeaturedBook))->toHaveCount(1)
        ->and($sections->pluck('section_type')->map->value->all())->toBe([
            'hero',
            'service-times',
            'welcome',
            'featured-sermons',
            'upcoming-events',
            'ministries-grid',
            'featured-book',
        ])
        ->and($first->heading)->toBe('A Preserved Welcome')
        ->and($first->content)->toBe('This message came from the existing homepage settings.')
        ->and($first->settings)->toHaveKey('welcome_signature', 'Pastor Signature');
});

test('legacy welcome content is converted without changing its content or media', function (): void {
    $page = homepageHeroPage();
    $image = homepageHeroImage();
    $legacy = PageSection::factory()->for($page)->create([
        'name' => 'Welcome, sermon, and upcoming events',
        'section_type' => 'welcome-upcoming-event',
        'heading' => 'Existing Welcome Heading',
        'content' => '<p>Existing welcome message.</p>',
        'settings' => [
            'welcome_signature' => 'Existing Signature',
            'welcome_pastor_name' => 'Existing Pastor',
            'welcome_pastor_role' => 'Existing Role',
            'pastor_image_alt' => 'Existing image description',
        ],
        'background_image_id' => $image->id,
        'sort_order' => 30,
    ]);

    $welcome = app(HomepageSectionSynchronizer::class)->sync($page);

    expect($welcome->id)->toBe($legacy->id)
        ->and($welcome->section_type)->toBe(PageSectionType::Welcome)
        ->and($welcome->name)->toBe('Welcome section')
        ->and($welcome->heading)->toBe('Existing Welcome Heading')
        ->and($welcome->content)->toBe('<p>Existing welcome message.</p>')
        ->and($welcome->settings)->toMatchArray($legacy->settings)
        ->and($welcome->background_image_id)->toBe($image->id)
        ->and($page->sections()->where('section_type', 'welcome')->count())->toBe(1);
});

test('welcome visibility and ordering actions control the ordered public renderer', function (): void {
    $page = homepageHeroPage();
    PageSection::factory()->for($page)->create([
        'name' => 'Service times',
        'section_type' => 'service-times',
        'heading' => 'Services Before Welcome',
        'sort_order' => 20,
    ]);
    $welcome = PageSection::factory()->for($page)->create([
        'name' => 'Welcome section',
        'section_type' => 'welcome',
        'heading' => 'Ordered Welcome',
        'sort_order' => 30,
    ]);
    PageSection::factory()->for($page)->create([
        'name' => 'Latest sermon',
        'section_type' => 'featured-sermons',
        'heading' => 'Latest Message After Welcome',
        'sort_order' => 40,
    ]);
    $administrator = homepageHeroAdministrator();

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Services Before Welcome', 'Ordered Welcome', 'Latest Message After Welcome']);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('move', $welcome->id, 'down')
        ->assertHasNoErrors();

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Services Before Welcome', 'Latest Message After Welcome', 'Ordered Welcome']);

    Livewire::actingAs($administrator)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('toggle', $welcome->id)
        ->assertHasNoErrors();

    expect($welcome->refresh()->is_visible)->toBeFalse();
    $this->get(route('home'))->assertOk()->assertDontSee('Ordered Welcome');
});
