<?php

use App\Actions\Pages\DuplicatePage;
use App\Actions\Pages\ManagePage;
use App\Actions\Pages\SavePage;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validPageData(array $overrides = []): array
{
    return array_merge(['title' => 'About Our Church', 'slug' => 'about-our-church', 'page_type' => 'about', 'template' => 'default', 'excerpt' => 'Welcome to our church.', 'content' => '<p>Safe content</p>', 'featured_image_id' => null, 'status' => 'draft', 'visibility' => 'public', 'is_homepage' => false, 'show_in_navigation' => true, 'navigation_label' => 'About', 'navigation_order' => 10, 'parent_id' => null, 'published_at' => null, 'meta_title' => null, 'meta_description' => null, 'meta_keywords' => null, 'canonical_url' => null, 'robots_index' => true, 'robots_follow' => true, 'og_title' => null, 'og_description' => null, 'og_image_id' => null], $overrides);
}

/** @return array{hero: PageSection, welcome: PageSection, services: PageSection, events: PageSection} */
function createOrderedHomepageSections(Page $page): array
{
    return [
        'hero' => PageSection::factory()->for($page)->create(['section_type' => 'hero', 'name' => 'Hero', 'heading' => 'Hero Public', 'sort_order' => 10]),
        'welcome' => PageSection::factory()->for($page)->create(['section_type' => 'welcome', 'name' => 'Welcome', 'heading' => 'Welcome Public', 'sort_order' => 20]),
        'services' => PageSection::factory()->for($page)->create(['section_type' => 'service-times', 'name' => 'Services', 'heading' => 'Services Public', 'sort_order' => 40]),
        'events' => PageSection::factory()->for($page)->create(['section_type' => 'upcoming-events', 'name' => 'Events', 'heading' => 'Events Public', 'sort_order' => 80]),
    ];
}

/** @return list<string> */
function orderedSectionNames(Page $page): array
{
    return $page->sections()->pluck('name')->all();
}

test('page administration requires permission', function (): void {
    $this->actingAs(User::factory()->create())->get(route('pages.index'))->assertForbidden();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionName::PagesView->value);
    $this->actingAs($viewer)->get(route('pages.index'))->assertOk();
});

test('authorized administrators create sanitized pages with audit records', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesCreate->value);
    $page = app(SavePage::class)->handle($actor, validPageData(['content' => '<p>Welcome</p><script>alert(1)</script>']));
    expect($page->content)->not->toContain('<script')->and($page->creator->is($actor))->toBeTrue()->and(ActivityLog::where('event', 'page.created')->exists())->toBeTrue();
});

test('reserved slugs are rejected and duplicate slugs remain unique', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesCreate->value);
    Livewire::actingAs($actor)->test('pages::pages.create')->set('form.title', 'Admin')->set('form.slug', 'admin')->call('save')->assertHasErrors(['form.slug']);
    Page::factory()->create(['slug' => 'our-story']);
    $page = app(SavePage::class)->handle($actor, validPageData(['slug' => 'our-story']));
    expect($page->slug)->toBe('our-story-2');
});

test('homepage switching is atomic and requires a published public page', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::PagesCreate->value, PermissionName::PagesUpdate->value, PermissionName::PagesPublish->value]);
    $first = app(SavePage::class)->handle($actor, validPageData(['title' => 'First Home', 'slug' => 'first-home', 'status' => 'published', 'published_at' => now()->subMinute(), 'is_homepage' => true]));
    $second = app(SavePage::class)->handle($actor, validPageData(['title' => 'Second Home', 'slug' => 'second-home', 'status' => 'published', 'published_at' => now()->subMinute(), 'is_homepage' => true]));
    expect($first->refresh()->is_homepage)->toBeFalse()->and($second->is_homepage)->toBeTrue();
    expect(fn () => app(SavePage::class)->handle($actor, validPageData(['title' => 'Draft Home', 'slug' => 'draft-home', 'is_homepage' => true])))->toThrow(ValidationException::class);
});

test('only published due pages are public', function (): void {
    $visible = Page::factory()->published()->create(['slug' => 'visible-page', 'title' => 'Visible Page']);
    $draft = Page::factory()->create(['slug' => 'draft-page']);
    $future = Page::factory()->scheduled()->create(['slug' => 'future-page']);
    $this->get('/'.$visible->slug)->assertOk()->assertSee('Visible Page');
    $this->get('/'.$draft->slug)->assertNotFound();
    $this->get('/'.$future->slug)->assertNotFound();
});

test('scheduled publishing is idempotent', function (): void {
    $due = Page::factory()->due()->create();
    $future = Page::factory()->scheduled()->create();
    $this->artisan('pages:publish-scheduled')->assertSuccessful();
    $this->artisan('pages:publish-scheduled')->assertSuccessful();
    expect($due->refresh()->status->value)->toBe('published')->and($future->refresh()->status->value)->toBe('scheduled')->and(ActivityLog::where('event', 'page.published')->count())->toBe(1);
});

test('page sections are managed only by authorized users', function (): void {
    $page = Page::factory()->create();
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    Livewire::actingAs($actor)->test('pages::pages.sections', ['page' => $page])->set('name', 'Welcome')->set('sectionType', 'hero')->set('settings', '{"primary_label":"Visit"}')->call('save')->assertHasNoErrors();
    expect($page->sections()->where('name', 'Welcome')->exists())->toBeTrue();
    $outsider = User::factory()->create();
    Livewire::actingAs($outsider)->test('pages::pages.sections', ['page' => $page])->assertForbidden();
});

test('moving a section up swaps it with only the immediately previous section', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $page = Page::factory()->create(['is_homepage' => true]);
    $sections = createOrderedHomepageSections($page);

    Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('move', $sections['services']->id, 'up');

    expect(orderedSectionNames($page))->toBe(['Hero', 'Services', 'Welcome', 'Events']);
});

test('moving a section down swaps it with only the immediately next section', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $page = Page::factory()->create(['is_homepage' => true]);
    $sections = createOrderedHomepageSections($page);

    Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('move', $sections['welcome']->id, 'down');

    expect(orderedSectionNames($page))->toBe(['Hero', 'Services', 'Welcome', 'Events']);
});

test('section movement respects boundaries and permits sections beside them to take their place', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $page = Page::factory()->create(['is_homepage' => true]);
    $sections = createOrderedHomepageSections($page);
    $component = Livewire::actingAs($actor)->test('pages::pages.sections', ['page' => $page]);

    $component->call('move', $sections['hero']->id, 'up');
    $component->call('move', $sections['events']->id, 'down');
    expect(orderedSectionNames($page))->toBe(['Hero', 'Welcome', 'Services', 'Events']);

    $component->call('move', $sections['welcome']->id, 'up');
    expect(orderedSectionNames($page))->toBe(['Welcome', 'Hero', 'Services', 'Events']);

    $component->call('move', $sections['services']->id, 'down');
    expect(orderedSectionNames($page))->toBe(['Welcome', 'Hero', 'Events', 'Services']);
});

test('a one-section page renders both movement controls as disabled actions', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $page = Page::factory()->create();
    PageSection::factory()->for($page)->create(['name' => 'Only section', 'sort_order' => 10]);

    $html = Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $page])
        ->html();

    expect($html)
        ->toMatch('/<button(?=[^>]*disabled)(?=[^>]*aria-label="Move Only section up")[^>]*>/')
        ->toMatch('/<button(?=[^>]*disabled)(?=[^>]*aria-label="Move Only section down")[^>]*>/')
        ->not->toContain('wire:click="move(');
});

test('section movement remains scoped to its page and normalizes duplicate positions', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $homepage = Page::factory()->create(['is_homepage' => true]);
    $sections = createOrderedHomepageSections($homepage);
    $sections['welcome']->update(['sort_order' => 10]);
    $otherPage = Page::factory()->create();
    $otherSections = PageSection::factory()->count(2)->for($otherPage)->sequence(
        ['name' => 'Other First', 'sort_order' => 10],
        ['name' => 'Other Last', 'sort_order' => 20],
    )->create();

    Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $homepage])
        ->call('move', $sections['services']->id, 'up');

    expect(orderedSectionNames($homepage))->toBe(['Hero', 'Services', 'Welcome', 'Events'])
        ->and($homepage->sections()->pluck('sort_order')->duplicates())->toBeEmpty()
        ->and($otherPage->sections()->pluck('sort_order')->all())->toBe([10, 20])
        ->and($otherSections->map->refresh()->pluck('name')->all())->toBe(['Other First', 'Other Last']);
});

test('the public homepage follows the persisted section order including the hero', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);
    $page = Page::factory()->published()->create(['is_homepage' => true, 'content' => null]);
    $sections = createOrderedHomepageSections($page);

    Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $page])
        ->call('move', $sections['welcome']->id, 'up');

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Welcome Public', 'Hero Public', 'Services Public', 'Events Public']);
});

test('welcome sermon and event sections accept managed welcome content', function (): void {
    $page = Page::factory()->create();
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesManageSections->value);

    Livewire::actingAs($actor)
        ->test('pages::pages.sections', ['page' => $page])
        ->set('name', 'Welcome, sermon, and events')
        ->set('sectionType', 'welcome-upcoming-event')
        ->set('heading', 'Welcome Home!')
        ->set('content', '<p>Come and encounter God with us.</p>')
        ->set('settings', '{"signature_text":"Joseph John Darko","pastor_name":"Prophet Joseph John Darko","pastor_title":"Founder & Lead Pastor"}')
        ->call('save')
        ->assertHasNoErrors();

    $section = $page->sections()->where('section_type', 'welcome-upcoming-event')->firstOrFail();

    expect($section->heading)->toBe('Welcome Home!')
        ->and($section->settings)->toMatchArray([
            'signature_text' => 'Joseph John Darko',
            'pastor_name' => 'Prophet Joseph John Darko',
            'pastor_title' => 'Founder & Lead Pastor',
        ]);
});

test('page duplication copies sections as a draft without copying media', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::PagesCreate->value);
    $page = Page::factory()->published()->create(['is_homepage' => true]);
    PageSection::factory()->for($page)->create();
    $copy = app(DuplicatePage::class)->handle($actor, $page->load('sections'));
    expect($copy->status->value)->toBe('draft')->and($copy->is_homepage)->toBeFalse()->and($copy->published_at)->toBeNull()->and($copy->sections)->toHaveCount(1)->and($copy->featured_image_id)->toBe($page->featured_image_id);
});

test('homepage cannot be deleted and trash honors restore permission', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::PagesDelete->value, PermissionName::PagesRestore->value]);
    $home = Page::factory()->published()->create(['is_homepage' => true]);
    expect(fn () => app(ManagePage::class)->delete($actor, $home))->toThrow(AuthorizationException::class);
    $page = Page::factory()->create();
    app(ManagePage::class)->delete($actor, $page);
    app(ManagePage::class)->restore($actor, $page);
    expect($page->refresh()->trashed())->toBeFalse();
});

test('signed previews require authentication permission and a valid signature', function (): void {
    $page = Page::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionName::PagesPreview->value);
    $url = URL::temporarySignedRoute('pages.preview', now()->addMinute(), ['page' => $page]);
    $this->actingAs($viewer)->get($url)->assertOk()->assertSee($page->title)->assertSee('noindex,nofollow', false);
    $this->actingAs($viewer)->get(route('pages.preview', $page))->assertForbidden();
});

test('managed homepage renders at the public root', function (): void {
    $page = Page::factory()->published()->create(['title' => 'A Managed Welcome', 'is_homepage' => true]);
    PageSection::factory()->for($page)->create(['heading' => 'Grow Together']);
    $this->get(route('home'))->assertOk()->assertSee('A Managed Welcome')->assertSee('Grow Together');
});
