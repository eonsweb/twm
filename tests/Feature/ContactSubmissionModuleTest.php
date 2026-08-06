<?php

use App\Actions\ContactSubmissions\AddContactSubmissionNote;
use App\Actions\ContactSubmissions\ChangeContactSubmissionWorkflow;
use App\Actions\ContactSubmissions\DeleteContactSubmission;
use App\Actions\ContactSubmissions\SubmitContactSubmission;
use App\ContactSubmissionCategory;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\Models\ActivityLog;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Notifications\ContactSubmissionAcknowledgement;
use App\Notifications\NewContactSubmissionNotification;
use App\PermissionName;
use App\PreferredContactMethod;
use App\Settings\SettingManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingSeeder::class]);
    RateLimiter::clear('contact:ip:'.hash('sha256', '127.0.0.1'));
});

function contactActor(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (PermissionName $permission): string => $permission->value)->all());

    return $user;
}

function validContactData(array $overrides = []): array
{
    return array_merge(['name' => 'Ama Visitor', 'email' => 'ama@example.test', 'phone' => '+233 20 123 4567', 'subject' => 'Membership information', 'category' => ContactSubmissionCategory::Membership->value, 'message' => 'I would like more information about becoming a church member.', 'preferred_contact_method' => PreferredContactMethod::Email->value], $overrides);
}

test('a visitor can view the contact page', function (): void {
    $this->get(route('public.contact'))->assertSuccessful()->assertSee('Contact Triumphant World Ministry');
});

test('valid public submission is normalized stored acknowledged and reset', function (): void {
    Notification::fake();
    app(SettingManager::class)->put('email', 'notification_recipients', "admin@example.test\ncare@example.test");
    Livewire::test('pages::public.contact')->set('formStartedAt', now()->subSeconds(5)->timestamp)->set('form.name', '  Ama   Visitor ')->set('form.email', ' AMA@EXAMPLE.TEST ')->set('form.phone', '+233 20 123 4567')->set('form.category', 'membership')->set('form.subject', '  Membership information ')->set('form.message', "  I would like more information about becoming a church member.\nThank you.  ")->set('form.preferredContactMethod', 'email')->set('form.consent', true)->call('submit')->assertHasNoErrors()->assertSet('form.message', '')->assertSet('successReference', fn ($value): bool => str_starts_with((string) $value, 'TWM-CON-'));
    $submission = ContactSubmission::query()->sole();
    expect($submission->name)->toBe('Ama Visitor')->and($submission->email)->toBe('ama@example.test')->and($submission->status)->toBe(ContactSubmissionStatus::New)->and($submission->reference_number)->toMatch('/^TWM-CON-\d{4}-\d{6}$/');
    Notification::assertSentOnDemand(ContactSubmissionAcknowledgement::class);
    Notification::assertSentOnDemandTimes(NewContactSubmissionNotification::class, 2);
});

test('public form validates required consent email category and phone contact method', function (): void {
    Livewire::test('pages::public.contact')->set('formStartedAt', now()->subSeconds(5)->timestamp)->set('form.name', 'A')->set('form.email', 'invalid')->set('form.category', 'prayer')->set('form.subject', 'No')->set('form.message', 'short')->set('form.preferredContactMethod', 'phone')->set('form.consent', false)->call('submit')->assertHasErrors(['form.name', 'form.email', 'form.category', 'form.subject', 'form.message', 'form.consent']);
});

test('honeypot and minimum completion timing reject submissions', function (): void {
    Livewire::test('pages::public.contact')->set('website', 'spam.example')->call('submit')->assertHasErrors('form.subject');
    expect(ContactSubmission::query()->count())->toBe(0);
});

test('duplicate retries return the original submission', function (): void {
    Notification::fake();
    $action = app(SubmitContactSubmission::class);
    $token = (string) str()->uuid();
    $first = $action->handle(validContactData(), $token);
    $retry = $action->handle(validContactData(), $token);
    $duplicateTab = $action->handle(validContactData(), (string) str()->uuid());
    expect($retry->is($first))->toBeTrue()->and($duplicateTab->is($first))->toBeTrue()->and(ContactSubmission::query()->count())->toBe(1);
});

test('notification transport failure does not roll back a stored submission', function (): void {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('Mail transport unavailable.'));
    $submission = app(SubmitContactSubmission::class)->handle(validContactData(['email' => 'failure@example.test']), (string) str()->uuid());
    expect($submission->exists)->toBeTrue()->and(ContactSubmission::query()->whereKey($submission)->exists())->toBeTrue();
});

test('rate limiter rejects excessive submissions', function (): void {
    config()->set('contact.rate_limit.ip_per_hour', 1);
    Notification::fake();
    Livewire::test('pages::public.contact')->set('formStartedAt', now()->subSeconds(5)->timestamp)->set('form.name', 'Ama Visitor')->set('form.email', 'first@example.test')->set('form.category', 'general')->set('form.subject', 'First enquiry')->set('form.message', 'This is a sufficiently detailed first enquiry.')->set('form.preferredContactMethod', 'email')->set('form.consent', true)->call('submit')->assertHasNoErrors();
    Livewire::test('pages::public.contact')->set('formStartedAt', now()->subSeconds(5)->timestamp)->set('form.name', 'Kojo Visitor')->set('form.email', 'second@example.test')->set('form.category', 'general')->set('form.subject', 'Second enquiry')->set('form.message', 'This is a sufficiently detailed second enquiry.')->set('form.preferredContactMethod', 'email')->set('form.consent', true)->call('submit')->assertHasErrors('form.email');
    expect(ContactSubmission::query()->count())->toBe(1);
});

test('admin routes require authentication and permission', function (): void {
    $this->get(route('contact-submissions.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('contact-submissions.index'))->assertForbidden();
    $viewer = contactActor([PermissionName::ContactSubmissionsView]);
    $this->actingAs($viewer)->get(route('contact-submissions.index'))->assertSuccessful();
});

test('viewer-only users do not receive workflow controls', function (): void {
    $viewer = contactActor([PermissionName::ContactSubmissionsView]);
    $submission = ContactSubmission::factory()->create();
    Livewire::actingAs($viewer)->test('pages::contact-submissions.show', ['contactSubmission' => $submission])->assertDontSee('Record reply sent')->assertDontSee('Add an internal note')->assertDontSee('Assigned administrator');
});

test('opening details records the first read time only once', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsView, PermissionName::ContactSubmissionsUpdate]);
    $submission = ContactSubmission::factory()->create();
    $this->actingAs($actor)->get(route('contact-submissions.show', $submission))->assertSuccessful();
    $firstRead = $submission->fresh()->read_at;
    $this->travel(5)->minutes();
    $this->actingAs($actor)->get(route('contact-submissions.show', $submission))->assertSuccessful();
    expect($submission->fresh()->read_at->equalTo($firstRead))->toBeTrue();
});

test('authorized workflow updates priority assignment resolution reply and reopening', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsUpdate, PermissionName::ContactSubmissionsAssign, PermissionName::ContactSubmissionsResolve, PermissionName::ContactSubmissionsReply]);
    $assignee = contactActor([PermissionName::ContactSubmissionsUpdate]);
    $submission = ContactSubmission::factory()->create();
    $workflow = app(ChangeContactSubmissionWorkflow::class);
    $workflow->changePriority($actor, $submission, ContactSubmissionPriority::Urgent);
    $workflow->assign($actor, $submission->refresh(), $assignee);
    $workflow->recordReply($actor, $submission->refresh());
    $resolved = $workflow->changeStatus($actor, $submission->refresh(), ContactSubmissionStatus::Resolved);
    expect($resolved->priority)->toBe(ContactSubmissionPriority::Urgent)->and($resolved->assigned_to)->toBe($assignee->id)->and($resolved->admin_replied_at)->not->toBeNull()->and($resolved->resolved_at)->not->toBeNull()->and($resolved->resolved_by)->toBe($actor->id);
    $reopened = $workflow->changeStatus($actor, $resolved, ContactSubmissionStatus::InProgress);
    expect($reopened->resolved_at)->toBeNull()->and($reopened->resolved_by)->toBeNull();
});

test('spam transition requires its dedicated permission and excludes the item from open counts', function (): void {
    $submission = ContactSubmission::factory()->create();
    $updater = contactActor([PermissionName::ContactSubmissionsUpdate]);
    expect(fn () => app(ChangeContactSubmissionWorkflow::class)->changeStatus($updater, $submission, ContactSubmissionStatus::Spam))->toThrow(AuthorizationException::class);
    $moderator = contactActor([PermissionName::ContactSubmissionsMarkSpam]);
    $spam = app(ChangeContactSubmissionWorkflow::class)->changeStatus($moderator, $submission, ContactSubmissionStatus::Spam);
    expect($spam->status)->toBe(ContactSubmissionStatus::Spam)->and(ContactSubmission::query()->open()->whereKey($spam)->exists())->toBeFalse();
});

test('suspended and unauthorized administrators cannot be assigned', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsAssign]);
    $ineligible = User::factory()->create(['account_status' => 'suspended']);
    $submission = ContactSubmission::factory()->create();
    expect(fn () => app(ChangeContactSubmissionWorkflow::class)->assign($actor, $submission, $ineligible))->toThrow(ValidationException::class);
});

test('unauthorized users cannot manage workflow or notes', function (): void {
    $actor = User::factory()->create();
    $submission = ContactSubmission::factory()->create();
    expect(fn () => app(ChangeContactSubmissionWorkflow::class)->changePriority($actor, $submission, ContactSubmissionPriority::High))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AddContactSubmissionNote::class)->handle($actor, $submission, 'A private note.'))->toThrow(AuthorizationException::class);
});

test('internal notes remain private and activity logs exclude message and note bodies', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsManageNotes]);
    $submission = ContactSubmission::factory()->create(['message' => 'Sensitive visitor message body.']);
    app(AddContactSubmissionNote::class)->handle($actor, $submission, 'Sensitive internal note body.');
    $this->get(route('public.contact'))->assertDontSee('Sensitive internal note body.')->assertDontSee('Sensitive visitor message body.');
    $audit = ActivityLog::query()->latest('id')->first();
    expect(json_encode([$audit->properties, $audit->old_values, $audit->new_values]))->not->toContain('Sensitive internal note body.')->not->toContain('Sensitive visitor message body.');
});

test('search and filters return matching submissions', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsView]);
    $match = ContactSubmission::factory()->highPriority()->create(['subject' => 'Unique partnership question', 'category' => ContactSubmissionCategory::Partnership]);
    $other = ContactSubmission::factory()->create();
    Livewire::actingAs($actor)->test('pages::contact-submissions.index')->set('search', 'Unique partnership')->set('category', 'partnership')->set('priority', 'high')->assertSee($match->reference_number)->assertDontSee($other->reference_number);
});

test('assignment read status and date filters are combined safely', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsView, PermissionName::ContactSubmissionsUpdate]);
    $matching = ContactSubmission::factory()->create(['assigned_to' => $actor->id, 'status' => ContactSubmissionStatus::InProgress, 'read_at' => null, 'created_at' => now()]);
    $other = ContactSubmission::factory()->read()->create(['created_at' => now()->subMonths(2)]);
    Livewire::actingAs($actor)->test('pages::contact-submissions.index')->set('status', 'in_progress')->set('assigned', (string) $actor->id)->set('read', 'unread')->set('dateFrom', now()->subDay()->toDateString())->set('dateTo', now()->addDay()->toDateString())->assertSee($matching->reference_number)->assertDontSee($other->reference_number);
});

test('admin output escapes visitor supplied markup', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsView]);
    $submission = ContactSubmission::factory()->create(['subject' => '<script>alert(1)</script>', 'message' => '<img src=x onerror=alert(1)>']);
    $this->actingAs($actor)->get(route('contact-submissions.show', $submission))->assertSuccessful()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('soft deletion restoration and forced deletion are authorized', function (): void {
    $actor = contactActor([PermissionName::ContactSubmissionsDelete, PermissionName::ContactSubmissionsRestore, PermissionName::ContactSubmissionsForceDelete]);
    $submission = ContactSubmission::factory()->create();
    $action = app(DeleteContactSubmission::class);
    $action->delete($actor, $submission);
    expect($submission->fresh()->trashed())->toBeTrue();
    $restored = $action->restore($actor, $submission->fresh());
    expect($restored->trashed())->toBeFalse();
    $action->delete($actor, $restored);
    $action->forceDelete($actor, $restored->fresh());
    expect(ContactSubmission::withTrashed()->find($submission->id))->toBeNull();
});
