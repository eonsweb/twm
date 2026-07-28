<?php

use App\Activity\ActivityLogger;
use App\Models\ActivityLog;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function activityAdministrator(RoleName $role = RoleName::SuperAdmin): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('super admin and explicitly authorized administrators can view activity logs', function () {
    $superAdmin = activityAdministrator();
    $this->actingAs($superAdmin)
        ->get(route('activity-logs.index'))
        ->assertOk()
        ->assertSee('Activity logs');

    $administrator = activityAdministrator(RoleName::Administrator);
    $this->actingAs($administrator)
        ->get(route('activity-logs.index'))
        ->assertOk();
});

test('unauthorized users and normal members cannot access activity logs', function () {
    $this->get(route('activity-logs.index'))->assertRedirect(route('login'));

    $member = User::factory()->create();
    $this->actingAs($member)
        ->get(route('activity-logs.index'))
        ->assertForbidden();
});

test('activity listing filters by causer event module date role and search', function () {
    $viewer = activityAdministrator();
    $editor = activityAdministrator(RoleName::Editor);
    $logger = app(ActivityLogger::class);
    $this->actingAs($editor);

    $matching = $logger->log(
        logName: 'leadership',
        event: 'leader.updated',
        description: 'Updated the searchable leadership profile.',
        causer: $editor,
    );
    $logger->log(
        logName: 'users',
        event: 'user.updated',
        description: 'Updated an unrelated account.',
        causer: $viewer,
    );

    $component = Livewire::actingAs($viewer)
        ->test('pages::activity-logs.index')
        ->set('causerId', (string) $editor->id)
        ->set('role', RoleName::Editor->value)
        ->set('event', 'leader.updated')
        ->set('logName', 'leadership')
        ->set('dateFrom', now()->toDateString())
        ->set('dateTo', now()->toDateString())
        ->set('search', 'searchable');

    $activities = $component->get('activities');

    expect($activities->total())->toBe(1)
        ->and($activities->first()->is($matching))->toBeTrue();
});

test('activity listing paginates large result sets', function () {
    $viewer = activityAdministrator();
    $logger = app(ActivityLogger::class);

    foreach (range(1, 30) as $number) {
        $logger->log(
            logName: 'system',
            event: 'test.generated',
            description: "Generated activity {$number}.",
            causer: $viewer,
        );
    }

    $activities = Livewire::actingAs($viewer)
        ->test('pages::activity-logs.index')
        ->get('activities');

    expect($activities->count())->toBe(25)
        ->and($activities->total())->toBeGreaterThanOrEqual(30);
});

test('detail view contains sanitized properties and handles deleted subjects', function () {
    $viewer = activityAdministrator();
    $subject = User::factory()->create(['name' => 'Soon Deleted']);
    $activity = app(ActivityLogger::class)->log(
        logName: 'users',
        event: 'user.updated',
        description: 'Updated a user before deletion.',
        subject: $subject,
        causer: $viewer,
        properties: [
            'safe_note' => 'Visible',
            'api_secret' => 'never-render-this-secret',
        ],
        oldValues: ['name' => '=Before'],
        newValues: ['name' => 'After'],
    );
    $subject->delete();

    Livewire::actingAs($viewer)
        ->test('activity-logs.details')
        ->call('show', $activity->id)
        ->assertSet('showModal', true)
        ->assertSet('details.subject', 'Soon Deleted')
        ->assertSee('Visible')
        ->assertDontSee('never-render-this-secret');
});

test('deleted subjects without a stored model are labeled clearly', function () {
    $viewer = activityAdministrator();
    $subject = User::factory()->create(['name' => 'Deleted Record']);
    $activity = app(ActivityLogger::class)->log(
        logName: 'users',
        event: 'user.deleted',
        description: 'Deleted user Deleted Record.',
        subject: $subject,
        causer: $viewer,
    );
    $subject->delete();

    Livewire::actingAs($viewer)
        ->test('activity-logs.details')
        ->call('show', $activity->id)
        ->assertSet('details.subject', 'Deleted Record');
});

test('activity export requires permission and recent password confirmation', function () {
    $member = User::factory()->create();
    $this->actingAs($member)
        ->get(route('activity-logs.export'))
        ->assertForbidden();

    $administrator = activityAdministrator(RoleName::Administrator);
    $this->actingAs($administrator)
        ->get(route('activity-logs.export'))
        ->assertRedirect(route('password.confirm'));
});

test('CSV export respects filters and prevents spreadsheet formula injection', function () {
    $viewer = activityAdministrator();
    $logger = app(ActivityLogger::class);
    $logger->log(
        logName: 'users',
        event: 'user.formula_test',
        description: '=2+2',
        causer: $viewer,
    );
    $logger->log(
        logName: 'leadership',
        event: 'leader.unrelated',
        description: 'Should not be exported.',
        causer: $viewer,
    );

    $response = $this->actingAs($viewer)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('activity-logs.export', ['log_name' => 'users']));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain("'=2+2")
        ->not->toContain('Should not be exported.')
        ->and(ActivityLog::query()->where('event', 'activity_logs.exported')->exists())->toBeTrue();
});

test('activity records reject normal updates and individual deletes', function () {
    $activity = app(ActivityLogger::class)->log(
        logName: 'system',
        event: 'test.append_only',
        description: 'Append-only activity.',
    );

    expect(fn () => $activity->forceFill(['description' => 'Changed'])->save())->toThrow(LogicException::class)
        ->and(fn () => $activity->delete())->toThrow(LogicException::class);
});

test('non administrative roles receive no activity permissions by default', function () {
    $editor = activityAdministrator(RoleName::Editor);

    expect($editor->cannot(PermissionName::ActivityLogsView))->toBeTrue()
        ->and($editor->cannot(PermissionName::ActivityLogsExport))->toBeTrue()
        ->and($editor->cannot(PermissionName::ActivityLogsPrune))->toBeTrue();
});
