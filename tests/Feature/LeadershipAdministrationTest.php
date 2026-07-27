<?php

use App\Models\LeadershipAssignment;
use App\Models\LeadershipPosition;
use App\Models\Person;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot access leadership administration', function () {
    $this->get(route('leadership.index'))->assertRedirect(route('login'));
});

test('authorized users can view leadership and unauthorized users receive forbidden', function () {
    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo(PermissionName::LeadershipView->value);

    $this->actingAs($authorizedUser)
        ->get(route('leadership.index'))
        ->assertOk()
        ->assertSee('Leadership');

    $this->actingAs(User::factory()->create())
        ->get(route('leadership.index'))
        ->assertForbidden();
});

test('authorized users can create a person with multiple assignments', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        PermissionName::LeadershipCreate->value,
        PermissionName::LeadershipPublish->value,
    ]);
    $founder = LeadershipPosition::factory()->create(['name' => 'Founder', 'slug' => 'founder']);
    $leadPastor = LeadershipPosition::factory()->create(['name' => 'Lead Pastor', 'slug' => 'lead-pastor']);

    Livewire::actingAs($user)
        ->test('pages::leadership.create')
        ->set('form.title', 'Prophet')
        ->set('form.firstName', 'Joseph')
        ->set('form.middleName', 'John')
        ->set('form.lastName', 'Darko')
        ->set('form.slug', 'prophet-joseph-john-darko')
        ->set('form.positionIds', [$founder->id, $leadPastor->id])
        ->set('form.primaryPositionId', $founder->id)
        ->set('form.displayTitle', 'Founder & Lead Pastor')
        ->set('form.sortOrder', 10)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('leadership.index');

    $person = Person::query()->where('slug', 'prophet-joseph-john-darko')->firstOrFail();

    expect($person->leadershipAssignments()->count())->toBe(2)
        ->and($person->primaryLeadershipAssignment()->firstOrFail()->leadership_position_id)->toBe($founder->id);
});

test('a person can exist without a leadership assignment', function () {
    $guestSpeaker = Person::factory()->create();

    expect($guestSpeaker->leadershipAssignments()->exists())->toBeFalse()
        ->and(Person::query()->leaders()->whereKey($guestSpeaker)->exists())->toBeFalse();
});

test('duplicate slugs are rejected', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::LeadershipCreate->value);
    $position = LeadershipPosition::factory()->create();
    Person::factory()->create(['slug' => 'known-speaker']);

    Livewire::actingAs($user)
        ->test('pages::leadership.create')
        ->set('form.firstName', 'Known')
        ->set('form.lastName', 'Speaker')
        ->set('form.slug', 'known-speaker')
        ->set('form.positionIds', [$position->id])
        ->set('form.primaryPositionId', $position->id)
        ->call('save')
        ->assertHasErrors(['form.slug' => ['unique']]);
});

test('invalid portrait files are rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::LeadershipCreate->value);
    $position = LeadershipPosition::factory()->create();
    $executable = UploadedFile::fake()->create('portrait.php', 20, 'application/x-php');

    Livewire::actingAs($user)
        ->test('pages::leadership.create')
        ->set('form.firstName', 'Guest')
        ->set('form.lastName', 'Minister')
        ->set('form.slug', 'guest-minister')
        ->set('form.positionIds', [$position->id])
        ->set('form.primaryPositionId', $position->id)
        ->set('form.portrait', $executable)
        ->call('save')
        ->assertHasErrors('form.portrait');

    expect(Person::query()->where('slug', 'guest-minister')->exists())->toBeFalse();
});

test('authorized users can update a leadership profile', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::LeadershipUpdate->value);
    $person = Person::factory()->create(['first_name' => 'Old']);
    LeadershipAssignment::factory()->for($person)->create();

    Livewire::actingAs($user)
        ->test('pages::leadership.edit', ['person' => $person])
        ->set('form.firstName', 'Updated')
        ->set('form.websiteUrl', 'example.com/profile')
        ->call('save')
        ->assertHasNoErrors();

    expect($person->refresh())
        ->first_name->toBe('Updated')
        ->website_url->toBe('https://example.com/profile');
});

test('unauthorized users cannot delete a profile', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::LeadershipView->value);
    $person = Person::factory()->create();
    LeadershipAssignment::factory()->for($person)->create();

    Livewire::actingAs($user)
        ->test('pages::leadership.index')
        ->call('delete', $person->id)
        ->assertForbidden();

    $this->assertModelExists($person);
});

test('deactivating preserves assignments while deleting cascades them', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        PermissionName::LeadershipView->value,
        PermissionName::LeadershipUpdate->value,
        PermissionName::LeadershipDelete->value,
    ]);
    $person = Person::factory()->create();
    $assignment = LeadershipAssignment::factory()->for($person)->create();

    Livewire::actingAs($user)
        ->test('pages::leadership.index')
        ->call('toggleActive', $person->id)
        ->assertHasNoErrors();

    expect($person->refresh()->is_active)->toBeFalse();
    $this->assertModelExists($assignment);

    Livewire::actingAs($user)
        ->test('pages::leadership.index')
        ->call('delete', $person->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($person);
    $this->assertModelMissing($assignment);
});

test('permission restricted leadership navigation is hidden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::DashboardView->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('leadership.index'));
});

test('super admin can access the leadership module', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin);
    $person = Person::factory()->create();
    LeadershipAssignment::factory()->for($person)->create();

    $this->actingAs($superAdmin);

    foreach ([
        route('leadership.index'),
        route('leadership.create'),
        route('leadership.edit', $person),
        route('leadership.positions'),
        route('leadership.ordering'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

test('dashboard and settings pages still render', function () {
    $editor = User::factory()->create();
    $editor->assignRole(RoleName::Editor);

    $this->actingAs($editor)
        ->get(route('dashboard'))
        ->assertOk();

    $this->get(route('profile.edit'))->assertOk();
});
