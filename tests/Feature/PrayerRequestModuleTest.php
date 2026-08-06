<?php

use App\Actions\PrayerRequests\AddPrayerRequestUpdate;
use App\Actions\PrayerRequests\ChangePrayerRequestWorkflow;
use App\Actions\PrayerRequests\DeletePrayerRequest;
use App\Actions\PrayerRequests\PublishPrayerRequest;
use App\Actions\PrayerRequests\SavePrayerRequest;
use App\Models\ActivityLog;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Notifications\PrayerRequestAssignedNotification;
use App\PermissionName;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestStatus;
use App\PrayerRequestUpdateType;
use Database\Seeders\PrayerRequestSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function prayerActor(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (PermissionName $permission): string => $permission->value)->all());

    return $user;
}

function prayerData(array $overrides = []): array
{
    return array_merge(['name' => 'Test Requester', 'subject' => 'Prayer for wisdom', 'request' => 'Please pray for wisdom and peace while making an important family decision.', 'privacy_level' => PrayerRequestPrivacy::Private->value, 'is_anonymous' => false, 'allow_contact' => false, 'allow_publication' => false], $overrides);
}

test('prayer requests automatically receive unique public tokens', function (): void {
    $first = PrayerRequest::factory()->create();
    $second = PrayerRequest::factory()->create();

    expect($first->public_token)->not->toBeEmpty()
        ->and(Str::isUlid($first->public_token))->toBeTrue()
        ->and($second->public_token)->not->toBe($first->public_token);
});

test('explicit prayer request public tokens are preserved', function (): void {
    $token = (string) Str::ulid();

    $prayerRequest = PrayerRequest::factory()->create(['public_token' => $token]);

    expect($prayerRequest->public_token)->toBe($token);
});

test('prayer request seeder succeeds when model events are disabled', function (): void {
    PrayerRequest::withoutEvents(function (): void {
        $this->seed(PrayerRequestSeeder::class);
    });

    expect(PrayerRequest::query()->where('reference_number', 'like', 'PR-DEMO-%')->count())->toBe(4)
        ->and(PrayerRequest::query()->whereNull('public_token')->exists())->toBeFalse()
        ->and(PrayerRequest::query()->distinct()->count('public_token'))->toBe(4);
});

test('public submissions are confidential by default and receive non-access references', function (): void {
    $request = app(SavePrayerRequest::class)->handle(null, prayerData(), publicSubmission: true, metadata: ['ip_address' => '127.0.0.1', 'user_agent' => 'Pest']);
    expect($request->reference_number)->toMatch('/^PR-\d{4}-\d{6}$/')->and($request->public_token)->not->toBe($request->reference_number)->and($request->status)->toBe(PrayerRequestStatus::New)->and($request->privacy_level)->toBe(PrayerRequestPrivacy::Private)->and($request->source->value)->toBe('website');
});

test('public submission form creates an anonymous request and resets sensitive state', function (): void {
    Livewire::test('pages::public.prayer-requests.create')->set('formStartedAt', now()->subSeconds(5)->timestamp)->set('form.isAnonymous', true)->set('form.subject', 'Please pray for strength')->set('form.request', 'Please pray for strength and grace throughout this challenging season.')->set('form.termsAccepted', true)->call('submit')->assertHasNoErrors()->assertSet('form.request', '')->assertSet('successReference', fn ($value): bool => str_starts_with((string) $value, 'PR-'));
    $this->assertDatabaseHas('prayer_requests', ['subject' => 'Please pray for strength', 'is_anonymous' => true, 'name' => null]);
});

test('administration routes require prayer request permissions', function (): void {
    $this->actingAs(User::factory()->create())->get(route('prayer-requests.index'))->assertForbidden();
    $viewer = prayerActor([PermissionName::PrayerRequestsView]);
    $this->actingAs($viewer)->get(route('prayer-requests.index'))->assertOk();
});

test('workflow transitions create timestamps timeline and content-free activity audit', function (): void {
    $actor = prayerActor([PermissionName::PrayerRequestsUpdate, PermissionName::PrayerRequestsMarkAnswered]);
    $request = PrayerRequest::factory()->create();
    app(ChangePrayerRequestWorkflow::class)->changePriority($actor, $request, PrayerRequestPriority::Urgent);
    $request = app(ChangePrayerRequestWorkflow::class)->changeStatus($actor, $request->refresh(), PrayerRequestStatus::Answered);
    expect($request->answered_at)->not->toBeNull()->and($request->updates()->count())->toBe(2)->and(ActivityLog::query()->where('subject_type', PrayerRequest::class)->count())->toBe(2);
    expect(ActivityLog::query()->where('subject_id', $request->id)->get()->pluck('properties')->flatten()->join(' '))->not->toContain($request->request);
});

test('assignment is restricted to eligible users and sends a safe notification', function (): void {
    Notification::fake();
    $actor = prayerActor([PermissionName::PrayerRequestsAssign]);
    $assignee = prayerActor([PermissionName::PrayerRequestsUpdate]);
    $request = PrayerRequest::factory()->create();
    app(ChangePrayerRequestWorkflow::class)->assign($actor, $request, $assignee);
    Notification::assertSentTo($assignee, PrayerRequestAssignedNotification::class, fn ($notification): bool => ! str_contains(serialize($notification), $request->request));
    expect($request->refresh()->assigned_to)->toBe($assignee->id)->and($request->status)->toBe(PrayerRequestStatus::Assigned);
});

test('private requests cannot be published and public pages expose only approved copy', function (): void {
    $publisher = prayerActor([PermissionName::PrayerRequestsPublish]);
    $private = PrayerRequest::factory()->create(['allow_publication' => true, 'privacy_level' => PrayerRequestPrivacy::Private]);
    expect(fn () => app(PublishPrayerRequest::class)->publish($publisher, $private, ['public_title' => 'Safe', 'public_excerpt' => null, 'public_content' => 'Safe public copy.']))->toThrow(ValidationException::class);
    $request = PrayerRequest::factory()->create(['name' => 'Secret Person', 'request' => 'Confidential original wording.', 'allow_publication' => true, 'privacy_level' => PrayerRequestPrivacy::PrayerTeam]);
    app(PublishPrayerRequest::class)->publish($publisher, $request, ['public_title' => 'Hope restored', 'public_excerpt' => 'A safe summary.', 'public_content' => 'An anonymized testimony of hope.']);
    $this->get(route('public.prayer-requests.show', $request))->assertOk()->assertSee('Hope restored')->assertDontSee('Secret Person')->assertDontSee('Confidential original wording.');
});

test('notes require permission and activity logs never contain note content', function (): void {
    $request = PrayerRequest::factory()->create();
    $unauthorized = User::factory()->create();
    expect(fn () => app(AddPrayerRequestUpdate::class)->handle($unauthorized, $request, PrayerRequestUpdateType::Note, 'Highly confidential pastoral note.'))->toThrow(AuthorizationException::class);
    $actor = prayerActor([PermissionName::PrayerRequestsAddNotes]);
    app(AddPrayerRequestUpdate::class)->handle($actor, $request, PrayerRequestUpdateType::Note, 'Highly confidential pastoral note.');
    expect(json_encode(ActivityLog::query()->latest('id')->first()->properties))->not->toContain('Highly confidential pastoral note.');
});

test('soft delete unpublishes and restore returns the request for review', function (): void {
    $actor = prayerActor([PermissionName::PrayerRequestsDelete, PermissionName::PrayerRequestsRestore]);
    $request = PrayerRequest::factory()->published()->create();
    app(DeletePrayerRequest::class)->delete($actor, $request);
    expect($request->fresh()->trashed())->toBeTrue()->and($request->fresh()->is_published)->toBeFalse();
    $restored = app(DeletePrayerRequest::class)->restore($actor, $request->fresh());
    expect($restored->trashed())->toBeFalse()->and($restored->status)->toBe(PrayerRequestStatus::UnderReview);
});
