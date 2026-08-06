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
