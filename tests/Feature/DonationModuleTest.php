<?php

use App\Actions\Donations\ChangeDonationStatus;
use App\Actions\Donations\RefundDonation;
use App\Actions\Donations\SaveDonation;
use App\Actions\Donations\SubmitPublicDonation;
use App\DonationPaymentMethod;
use App\DonationPaymentStatus;
use App\DonationSource;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationCategory;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingSeeder::class]);
});

function donationActor(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (PermissionName $permission): string => $permission->value)->all());

    return $user;
}

function validDonationData(DonationCategory $category, array $overrides = []): array
{
    return array_merge(['donation_category_id' => $category->id, 'amount' => '125.50', 'currency' => 'USD', 'payment_method' => DonationPaymentMethod::Cash->value, 'payment_status' => DonationPaymentStatus::Pending->value, 'donated_at' => now(), 'is_anonymous' => true, 'is_recurring' => false, 'source' => DonationSource::Admin->value], $overrides);
}

test('admin donation routes require authentication and granular permission', function (): void {
    $this->get(route('donations.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('donations.index'))->assertForbidden();
    $this->actingAs(donationActor([PermissionName::DonationsView]))->get(route('donations.index'))->assertSuccessful();
});

test('all donation administration and public giving pages render', function (): void {
    $actor = donationActor([
        PermissionName::DonationsView, PermissionName::DonationsCreate, PermissionName::DonationsUpdate,
        PermissionName::DonorsView, PermissionName::DonorsCreate, PermissionName::DonorsUpdate,
        PermissionName::DonationCategoriesManage, PermissionName::DonationCampaignsManage,
        PermissionName::PaymentTransactionsView,
    ]);
    $donation = Donation::factory()->create();
    $donor = $donation->donor;

    foreach ([
        route('donations.index'), route('donations.create'), route('donations.show', $donation),
        route('donations.edit', $donation), route('donors.index'), route('donors.show', $donor),
        route('donation-categories.index'), route('donation-campaigns.index'), route('payment-transactions.index'),
    ] as $url) {
        $this->actingAs($actor)->get($url)->assertSuccessful();
    }

    $this->get(route('public.give'))->assertSuccessful()->assertSee('Verification required');
});

test('authorized user creates a donation with a deterministic unique reference', function (): void {
    $actor = donationActor([PermissionName::DonationsCreate]);
    $category = DonationCategory::factory()->create();
    $donation = app(SaveDonation::class)->handle($actor, validDonationData($category));
    $second = app(SaveDonation::class)->handle($actor, validDonationData($category));
    expect($donation->reference)->toMatch('/^DON-\d{4}-\d{6}$/')->not->toBe($second->reference)
        ->and($donation->recorded_by)->toBe($actor->id);
});

test('campaign must belong to the selected category', function (): void {
    $actor = donationActor([PermissionName::DonationsCreate]);
    $category = DonationCategory::factory()->create();
    $campaign = DonationCampaign::factory()->create();
    expect(fn () => app(SaveDonation::class)->handle($actor, validDonationData($category, ['donation_campaign_id' => $campaign->id])))->toThrow(ValidationException::class);
});

test('only approvers can complete donations and completion issues one receipt', function (): void {
    $category = DonationCategory::factory()->create();
    $donation = Donation::factory()->create(['donation_category_id' => $category->id]);
    expect(fn () => app(ChangeDonationStatus::class)->handle(User::factory()->create(), $donation, DonationPaymentStatus::Completed))->toThrow(AuthorizationException::class);
    $approver = donationActor([PermissionName::DonationsApprove]);
    app(ChangeDonationStatus::class)->handle($approver, $donation, DonationPaymentStatus::Completed);
    $receipt = $donation->refresh()->receipt_number;
    app(ChangeDonationStatus::class)->handle($approver, $donation, DonationPaymentStatus::Completed);
    expect($donation->refresh()->approved_by)->toBe($approver->id)->and($donation->receipt_number)->toBe($receipt)->and($receipt)->toMatch('/-\d{4}-\d{6}$/');
});

test('create permission alone cannot record a donation as completed', function (): void {
    $category = DonationCategory::factory()->create();
    $creator = donationActor([PermissionName::DonationsCreate]);
    expect(fn () => app(SaveDonation::class)->handle($creator, validDonationData($category, ['payment_status' => DonationPaymentStatus::Completed->value])))->toThrow(AuthorizationException::class);

    $approver = donationActor([PermissionName::DonationsCreate, PermissionName::DonationsApprove]);
    $donation = app(SaveDonation::class)->handle($approver, validDonationData($category, ['payment_status' => DonationPaymentStatus::Completed->value]));
    expect($donation->approved_by)->toBe($approver->id)->and($donation->approved_at)->not->toBeNull()->and($donation->receipt_number)->not->toBeNull();
});

test('invalid financial status transitions are blocked', function (): void {
    $actor = donationActor([PermissionName::DonationsApprove]);
    $donation = Donation::factory()->create(['payment_status' => DonationPaymentStatus::Cancelled]);
    expect(fn () => app(ChangeDonationStatus::class)->handle($actor, $donation, DonationPaymentStatus::Completed))->toThrow(ValidationException::class);
});

test('partial and full refunds preserve history and cannot exceed payment', function (): void {
    $actor = donationActor([PermissionName::DonationsRefund]);
    $donation = Donation::factory()->completed()->create(['amount' => '100.00']);
    app(RefundDonation::class)->handle($actor, $donation, '40.00', 'Partial correction');
    expect($donation->refresh()->payment_status)->toBe(DonationPaymentStatus::PartiallyRefunded)->and($donation->totalRefunded())->toBe('40.00');
    expect(fn () => app(RefundDonation::class)->handle($actor, $donation, '61.00', 'Too much'))->toThrow(ValidationException::class);
    app(RefundDonation::class)->handle($actor, $donation, '60.00', 'Remaining correction');
    expect($donation->refresh()->payment_status)->toBe(DonationPaymentStatus::Refunded)->and($donation->refunds)->toHaveCount(2);
});

test('completed totals exclude pending failed and refunded records', function (): void {
    Donation::factory()->completed()->create(['amount' => 100]);
    Donation::factory()->create(['amount' => 200, 'payment_status' => DonationPaymentStatus::Pending]);
    Donation::factory()->create(['amount' => 300, 'payment_status' => DonationPaymentStatus::Failed]);
    expect((float) Donation::completed()->sum('amount'))->toBe(100.0);
});

test('public submissions are pending idempotent and do not accept internal fields', function (): void {
    $category = DonationCategory::factory()->create(['is_active' => true, 'display_on_website' => true]);
    $key = (string) str()->uuid();
    $data = ['first_name' => 'Test', 'last_name' => 'Donor', 'email' => 'donor@example.test', 'phone' => null, 'is_anonymous' => false, 'donation_category_id' => $category->id, 'amount' => 50, 'currency' => 'USD', 'payment_method' => DonationPaymentMethod::BankTransfer->value, 'notes' => 'Transfer sent', 'internal_notes' => 'must not persist', 'submission_key' => $key];
    $first = app(SubmitPublicDonation::class)->handle($data);
    $retry = app(SubmitPublicDonation::class)->handle($data);
    expect($retry->is($first))->toBeTrue()->and($first->payment_status)->toBe(DonationPaymentStatus::Pending)->and($first->source)->toBe(DonationSource::Website)->and($first->internal_notes)->toBeNull()->and(Donation::count())->toBe(1);
});

test('public giving form creates only a pending payment notification', function (): void {
    $category = DonationCategory::factory()->create(['is_active' => true, 'display_on_website' => true]);
    Livewire::test('pages::public.give')
        ->set('startedAt', now()->subSeconds(5)->timestamp)
        ->set('firstName', 'Public')
        ->set('lastName', 'Donor')
        ->set('email', 'public@example.test')
        ->set('categoryId', (string) $category->id)
        ->set('amount', '75.00')
        ->set('method', DonationPaymentMethod::BankTransfer->value)
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('successReference', fn ($value): bool => str_starts_with((string) $value, 'DON-'));

    expect(Donation::sole()->payment_status)->toBe(DonationPaymentStatus::Pending);
});

test('provider references create reusable transaction history', function (): void {
    $actor = donationActor([PermissionName::DonationsCreate]);
    $category = DonationCategory::factory()->create();
    $donation = app(SaveDonation::class)->handle($actor, validDonationData($category, [
        'payment_provider' => 'manual-bank',
        'provider_reference' => 'BANK-12345',
    ]));

    expect($donation->transactions)->toHaveCount(1)
        ->and($donation->transactions->first()->provider_reference)->toBe('BANK-12345')
        ->and($donation->transactions->first()->status)->toBe(DonationPaymentStatus::Pending);
});

test('receipt requires permission and hides internal notes', function (): void {
    $donation = Donation::factory()->completed()->create(['receipt_number' => 'TWM-RCP-2026-000001', 'internal_notes' => 'Private finance detail']);
    $this->actingAs(donationActor([PermissionName::DonationsView]))->get(route('donations.receipt', $donation))->assertForbidden();
    $this->actingAs(donationActor([PermissionName::DonationsPrintReceipt]))->get(route('donations.receipt', $donation))->assertSuccessful()->assertSee($donation->reference)->assertDontSee('Private finance detail');
});

test('csv export requires permission respects status and neutralizes spreadsheet formulae', function (): void {
    $category = DonationCategory::factory()->create(['name' => '=Dangerous']);
    Donation::factory()->completed()->create(['donation_category_id' => $category->id]);
    Donation::factory()->create(['payment_status' => DonationPaymentStatus::Pending]);
    $this->actingAs(donationActor([PermissionName::DonationsView]))->get(route('donations.export'))->assertForbidden();
    $response = $this->actingAs(donationActor([PermissionName::DonationsExport]))->get(route('donations.export', ['status' => 'completed']));
    $response->assertSuccessful();
    $content = $response->streamedContent();
    expect($content)->toContain("'=Dangerous")->not->toContain('Pending');
});
