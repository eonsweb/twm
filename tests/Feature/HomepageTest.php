<?php

use App\Models\Book;
use App\Models\Event;
use App\Models\LeadershipAssignment;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use App\Settings\SettingManager;
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

test('upcoming published events are ordered and past events are excluded', function (): void {
    Event::factory()->published()->create(['title' => 'Later Gathering', 'starts_at' => now()->addDays(8), 'ends_at' => now()->addDays(8)->addHour()]);
    Event::factory()->published()->create(['title' => 'Soon Gathering', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    Event::factory()->published()->past()->create(['title' => 'Past Gathering']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Soon Gathering', 'Later Gathering'])
        ->assertDontSee('Past Gathering');
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
        ->assertSee('The next sermon will appear here')
        ->assertSee('New events are coming soon')
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

    expect($queries)->toBeLessThanOrEqual(25);
});

test('existing administration routes remain protected', function (): void {
    $this->get(route('media.index'))->assertRedirect(route('login'));
});
