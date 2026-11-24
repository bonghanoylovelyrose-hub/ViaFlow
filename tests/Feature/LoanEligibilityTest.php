<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\LoanMaterialRate;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use App\Services\DssEligibilityService;
use App\Services\LoanIncomeOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->mock(LoanIncomeOcrService::class)->shouldReceive('extract')->andReturn([]);
    }

    public function test_admin_loan_module_tab_order_default_and_content_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('loans.index'))->assertOk();
        $html = $response->getContent();
        $navStart = strpos($html, '<nav class="loan-tab-nav" aria-label="Loan application categories">');
        $this->assertNotFalse($navStart);
        $navEnd = strpos($html, '</nav>', $navStart);
        $this->assertNotFalse($navEnd);
        $tabs = substr($html, $navStart, $navEnd - $navStart);

        $allPosition = strpos($tabs, '>ALL</a>');
        $pendingPosition = strpos($tabs, '>PENDING</a>');
        $costPosition = strpos($tabs, '>SET COST</a>');
        $this->assertNotFalse($allPosition);
        $this->assertNotFalse($pendingPosition);
        $this->assertNotFalse($costPosition);
        $this->assertLessThan($pendingPosition, $allPosition);
        $this->assertLessThan($costPosition, $pendingPosition);
        $this->assertStringNotContainsString('SET COSTS', $tabs);
        $this->assertSame(1, preg_match('/<a\b(?=[^>]*class="active")(?=[^>]*aria-current="page")[^>]*>ALL<\/a>/', $tabs));
        $response->assertSee('Loan Application Management');

        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending']))
            ->assertOk()->assertSee('Pending Income Verification')->assertSee('Income Verification');
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'costs']))
            ->assertOk()->assertSee('Installation Cost Breakdown');

        $refreshed = $this->actingAs($admin)->get(route('loans.index'))->assertOk()->getContent();
        $refreshedTabs = substr($refreshed, strpos($refreshed, '<nav class="loan-tab-nav" aria-label="Loan application categories">'));
        $refreshedTabs = substr($refreshedTabs, 0, strpos($refreshedTabs, '</nav>'));
        $this->assertSame(1, preg_match('/<a\b(?=[^>]*class="active")(?=[^>]*aria-current="page")[^>]*>ALL<\/a>/', $refreshedTabs));
        $this->assertLessThan(strpos($refreshedTabs, '>PENDING</a>'), strpos($refreshedTabs, '>ALL</a>'));
        $this->assertLessThan(strpos($refreshedTabs, '>SET COST</a>'), strpos($refreshedTabs, '>PENDING</a>'));
    }
    public function test_non_member_cannot_see_or_access_loan_management(): void
    {
        [$user] = $this->consumerAccount('Non-Member', now()->subYear());

        $response = $this->actingAs($user)->get('/loans');

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('loan_access_denied', 'Loan services are available to Members only.');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('My Loans')
            ->assertSee('Loan Services Unavailable')
            ->assertSee('Loan services are available to Members only.');

        $this->actingAs($user)->post('/loans', [
            'term_months' => 6,
            'purpose' => 'Non-member loan attempt',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('loan_access_denied', 'Loan services are available to Members only.');

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_verified_member_with_tenant_ownership_and_clean_records_can_apply(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now(), [
            'ownership_status' => 'Tenant',
        ]);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)
            ->get('/loans')
            ->assertOk()
            ->assertSee('Apply for Loan')
            ->assertSee('data-bs-target="#loanModal"', false)
            ->assertDontSee('Apply Loan Unavailable')
            ->assertDontSee('Renters are not eligible');

        $this->submitApplication($user, 6, 'Tenant member application')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'status' => 'Needs Review',
            'income_verification_status' => 'Pending Verification',
            'purpose' => 'Tenant member application',
        ]);
        $this->assertSame('Approved', $this->verifyApplication($consumer->loans()->sole())->recommendation);
    }


    public function test_ineligible_member_sees_the_specific_reason_after_dss_review(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->addUnpaidBill($consumer);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->submitApplication($user, 6, 'Water connection materials')->assertSessionHasNoErrors();
        $loan = $consumer->loans()->sole();
        $this->assertSame('Rejected', $this->verifyApplication($loan)->recommendation);

        $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee('View DSS rejection reasons')
            ->assertSee(DssEligibilityService::OUTSTANDING_BALANCE_MESSAGE)
            ->assertDontSee('Your account does not currently meet the requirements for a new loan application.')
            ->assertDontSee('Renters are not eligible')
            ->assertDontSee('Low Risk')
            ->assertDontSee('Medium Risk')
            ->assertDontSee('High Risk');
    }


    public function test_member_with_bad_record_cannot_bypass_verification_or_dss(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->addUnpaidBill($consumer);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->submitApplication($user, 6, 'Emergency repair', ['status' => 'Approved', 'income_verification_status' => 'Verified'])
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $loan = $consumer->loans()->sole();
        $this->assertSame('Needs Review', $loan->status);
        $this->assertSame('Pending Verification', $loan->income_verification_status);
        $this->assertDatabaseCount('loan_dss_evaluations', 0);
        $this->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])->assertForbidden();

        $evaluation = $this->verifyApplication($loan);
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame('Rejected', $loan->fresh()->status);
        $this->assertSame(500.0, (float) $evaluation->evaluated_values['overdue_water_balance']);
    }

    public function test_eligible_member_can_submit_a_loan_application(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->setLoanMaterialRate(250, 150, 100);

        $this->submitApplication($user, 6, 'Water connection repair')
            ->assertRedirect(route('loans.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('loans.index'))->assertOk()
            ->assertSee('Your loan application was received and is marked Needs Review while staff verifies the proof of income.')
            ->assertSee('Pending Verification')
            ->assertSee('Apply Loan Unavailable')
            ->assertSee('A loan application is already pending review.')
            ->assertDontSee('data-bs-target="#loanModal"', false);

        $this->submitApplication($user, 6, 'Duplicate pending loan')->assertSessionHasErrors('loan');
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('loan_dss_evaluations', 0);

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'status' => 'Needs Review',
            'income_verification_status' => 'Pending Verification',
            'amount' => 500,
            'term_months' => 6,
            'monthly_installment' => 83.33,
            'material_meter_cost' => 250,
            'material_hose_cost' => 150,
            'material_other_cost' => 100,
            'installation_cost_total' => 500,
        ]);

        $loan = Loan::query()->where('consumer_id', $consumer->id)->firstOrFail();
        $evaluation = $this->verifyApplication($loan);
        $this->assertSame('Ready for Scheduling', $loan->fresh()->status);
        $this->assertSame('Approved', $evaluation->recommendation);
        $this->assertSame('initial', $evaluation->evaluation_type);
        $this->assertSame([], $evaluation->failed_criteria);
        $this->assertSame(
            [83.33, 83.33, 83.33, 83.33, 83.33, 83.35],
            collect($loan->installment_schedule_snapshot)->pluck('amount')->all()
        );
    }

    public function test_new_member_without_payment_or_loan_history_is_eligible_immediately(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now());
        $this->setLoanMaterialRate(300, 180, 120);

        $this->submitApplication($user, 3, 'Emergency plumbing materials')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'status' => 'Needs Review',
            'income_verification_status' => 'Pending Verification',
            'amount' => 600,
            'term_months' => 3,
            'monthly_installment' => 200,
        ]);
        $this->assertSame('Approved', $this->verifyApplication($consumer->loans()->sole())->recommendation);
    }

    public function test_existing_member_with_short_tenure_and_clean_records_can_apply(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(2));
        $this->addPaidBill($consumer);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)
            ->get('/loans')
            ->assertOk()
            ->assertSee('Apply for Loan')
            ->assertSee('data-bs-target="#loanModal"', false)
            ->assertDontSee('Apply Loan Unavailable');

        $this->submitApplication($user, 6, 'Short-tenure member application')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'status' => 'Needs Review',
            'income_verification_status' => 'Pending Verification',
            'purpose' => 'Short-tenure member application',
        ]);
        $this->assertSame('Approved', $this->verifyApplication($consumer->loans()->sole())->recommendation);
    }


    public function test_member_with_overdue_water_balance_is_rejected_after_verification(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->addUnpaidBill($consumer);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('Apply for Loan');
        $this->submitApplication($user, 6, 'Water connection materials')->assertSessionHasNoErrors();

        $loan = $consumer->loans()->sole();
        $evaluation = $this->verifyApplication($loan);
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame(['outstanding_balance'], array_column($evaluation->failed_criteria, 'id'));
        $this->assertSame(500.0, (float) $evaluation->evaluated_values['overdue_water_balance']);
        $this->assertNull($loan->fresh()->approved_at);
    }


    public function test_member_with_late_payment_is_rejected_after_verification(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->addLatePaidBill($consumer);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('Apply for Loan');
        $this->submitApplication($user, 3, 'Water connection materials')->assertSessionHasNoErrors();

        $evaluation = $this->verifyApplication($consumer->loans()->sole());
        $this->assertSame('Rejected', $evaluation->recommendation);
        $this->assertSame(['late_payment_history'], array_column($evaluation->failed_criteria, 'id'));
        $this->assertSame(1, $evaluation->evaluated_values['late_payments_last_six_months']);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee(DssEligibilityService::DELAYED_PAYMENT_MESSAGE)
            ->assertSee('Found 1 delayed or late payment record(s) within the last six months.');
    }

    public function test_disconnected_member_is_redirected_to_inactive_account_information(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'status' => 'Disconnected',
        ]);
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)
            ->get('/loans')
            ->assertRedirect(route('account.inactive'));

        $this->get(route('account.inactive'))->assertOk()
            ->assertSee('Account Disconnection')
            ->assertSee('visit the office');

        $this->actingAs($user)->post('/loans', [
            'term_months' => 6,
            'purpose' => 'Disconnected member attempt',
        ])->assertForbidden();

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_consumer_loan_application_has_no_amount_field_and_only_allowed_terms(): void
    {
        [$user] = $this->consumerAccount('Member', now());
        $this->setLoanMaterialRate(250, 150, 100);

        $response = $this->actingAs($user)->get('/loans');

        $response->assertOk();
        $response->assertDontSee('Requested Loan Amount');
        $response->assertDontSee('name="amount"', false);
        $response->assertSee('Installation Cost Breakdown');
        $response->assertSee('Water Meter');
        $response->assertSee("\u{20B1}250.00");
        $response->assertSee('Hose');
        $response->assertSee("\u{20B1}150.00");
        $response->assertSee('Other Materials');
        $response->assertSee("\u{20B1}100.00");
        $response->assertSee('Total Installation Cost');
        $response->assertSee("\u{20B1}500.00");
        $response->assertSee('Loading installation costs...');
        $response->assertSee('Selected Term');
        $response->assertSee('Estimated Monthly Installment');
        $response->assertSee('Monthly Payment Schedule:');
        $response->assertSee('value="3"', false);
        $response->assertSee('3 months');
        $response->assertSee('value="6"', false);
        $response->assertSee('6 months');
        $response->assertSee('value="12"', false);
        $response->assertSee('12 months');
        $response->assertDontSee('5 months');
    }


    public function test_consumer_cannot_override_amount_or_submit_an_invalid_repayment_term(): void
    {
        [$user] = $this->consumerAccount('Member', now());
        $this->setLoanMaterialRate(250, 150, 100);

        $this->submitApplication($user, 6, 'Water connection repair', ['term_months' => 5])
            ->assertSessionHasErrors('term_months');
        $this->assertDatabaseCount('loans', 0);

        $this->submitApplication($user, 6, 'Water connection repair', ['amount' => 1200])
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('loans', ['amount' => 500, 'term_months' => 6]);
    }

    public function test_admin_cannot_create_loan_application_from_ui_or_direct_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));

        $this->actingAs($admin)
            ->get('/loans')
            ->assertOk()
            ->assertDontSee('New Application')
            ->assertDontSee('Apply for Loan')
            ->assertDontSee('data-bs-target="#loanModal"', false)
            ->assertDontSee('id="loanModal"', false)
            ->assertDontSee('route(\'loans.store\')', false)
            ->assertSee('Loan Application Management');
        $this->get(route('loans.index', ['tab' => 'costs']))->assertOk()->assertSee('Installation Cost Breakdown');

        $this->actingAs($admin)->post('/loans', [
            'consumer_id' => $consumer->id,
            'amount' => 500,
            'term_months' => 6,
            'purpose' => 'Admin-created loan attempt',
        ])->assertSessionHasErrors([
            'loan' => 'Only consumers can submit loan applications.',
        ]);

        $this->assertDatabaseCount('loans', 0);
    }


    public function test_admin_loan_management_shows_installation_details_for_the_selected_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $scheduled = $this->createLoan($consumer, [
            'status' => 'Ready for Scheduling',
            'review_status' => 'Installation Scheduled',
            'income_verification_status' => 'Verified',
            'installation_status' => 'Scheduled',
            'installation_date' => '2026-08-20',
            'installation_time' => '13:30',
            'installation_notes' => 'Use front gate.',
            'approved_at' => '2026-08-01',
            'material_meter_cost' => 250,
            'material_hose_cost' => 150,
            'material_other_cost' => 100,
            'installation_cost_total' => 500,
        ]);
        $this->recordApprovedDecision($scheduled);
        [, $otherConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'account_number' => 'VV-REJECTED',
            'meter_number' => 'M-REJECTED',
        ]);
        $this->createLoan($otherConsumer, ['status' => 'Rejected']);

        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'all', 'loan' => $scheduled->id]))
            ->assertOk()
            ->assertSee('Loan Application Management')
            ->assertSee('DSS Review Status')
            ->assertSee('Installation Cost Breakdown')
            ->assertSee('Total Installation Cost')
            ->assertSeeInOrder(['Installation Information', 'Assigned Meter Number', $consumer->meter_number, 'Installation Date', 'Aug 20, 2026', 'Installation Time', '1:30 PM', 'Installation Status', 'Scheduled'])
            ->assertSee('Installation Notes')
            ->assertSee('Use front gate.')
            ->assertSee('Installment Schedule and Loan Agreement')
            ->assertSeeInOrder(['Pending Installation', 'Scheduled Date: Aug 20, 2026', 'Mark Complete'])
            ->assertSee(route('loans.installation-status.update', $scheduled), false)
            ->assertDontSee('Update Installation Status')
            ->assertDontSee('Update Status')
            ->assertDontSee('Send Installation Schedule')
            ->assertDontSee('Assign Meter &amp; Schedule Installation', false)
            ->assertDontSee('VV-REJECTED')
            ->assertDontSee('id="loan_review_status"', false);

        $pending = $scheduled->installationRescheduleRequests()->create([
            'consumer_id' => $consumer->id,
            'reschedule_type' => InstallationRescheduleRequest::TYPE_LOAN,
            'status' => InstallationRescheduleRequest::STATUS_PENDING,
            'reason' => 'Please move the appointment.',
            'original_installation_date' => $scheduled->installation_date,
            'original_installation_time' => $scheduled->installation_time,
        ]);
        $assign = route('installation-reschedules.assign', $pending);

        $this->get(route('loans.index', ['loan' => $scheduled->id]))->assertOk()
            ->assertSee('Reschedule Requested')->assertDontSee($assign, false)->assertDontSee('Mark Complete');
        $this->get(route('loans.index', ['tab' => 'pending', 'pending' => 'reschedule']))->assertOk()
            ->assertSee($assign, false)->assertSee('name="admin_notes"', false);
    }

    public function test_admin_installation_schedule_notifies_consumer_and_avoids_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$consumerUser, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        [$otherUser] = $this->consumerAccount('Member', now()->subMonths(7), [
            'account_number' => 'VV-OTHER',
            'meter_number' => 'M-OTHER',
        ]);
        $loan = $this->createLoan($consumer, [
            'amount' => 500,
            'term_months' => 6,
            'status' => 'Ready for Scheduling',
            'review_status' => 'Reviewed',
            'income_verification_status' => 'Verified',
            'installation_status' => 'Not Scheduled',
            'material_meter_cost' => 250,
            'material_hose_cost' => 150,
            'material_other_cost' => 100,
            'installation_cost_total' => 500,
        ]);
        $this->recordApprovedDecision($loan);

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_time' => '09:30',
        ])->assertSessionHasErrors('installation_date');

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-08-15',
        ])->assertSessionHasErrors('installation_time');

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-08-15',
            'installation_time' => '09:30',
            'installation_notes' => 'Bring sealant.',
        ])->assertRedirect(route('loans.index'));

        $loan->refresh();
        $this->assertSame('Installation Scheduled', $loan->review_status);
        $this->assertSame('Scheduled', $loan->installation_status);
        $this->assertSame('2026-08-15', $loan->installation_date->toDateString());
        $this->assertSame('09:30', substr((string) $loan->installation_time, 0, 5));
        $this->assertSame('Bring sealant.', $loan->installation_notes);
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('notifications', 1);

        $notification = DatabaseNotification::query()->firstOrFail();
        $this->assertSame(User::class, $notification->notifiable_type);
        $this->assertSame((string) $consumerUser->id, (string) $notification->notifiable_id);
        $this->assertNull($notification->read_at);
        $this->assertSame('Installation Schedule', $notification->data['title']);
        $this->assertSame($loan->id, $notification->data['loan_id']);
        $this->assertSame($consumer->id, $notification->data['consumer_id']);
        $this->assertSame($consumer->account_number, $notification->data['account_number']);
        $this->assertSame('2026-08-15', $notification->data['installation_date']);
        $this->assertSame('09:30', $notification->data['installation_time']);
        $this->assertSame('Bring sealant.', $notification->data['installation_notes']);
        $this->assertStringContainsString('Your installation has been scheduled for August 15, 2026 at 9:30 AM.', $notification->data['message']);
        $this->assertCount(0, $otherUser->notifications);

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-08-15',
            'installation_time' => '09:30',
            'installation_notes' => 'Bring sealant.',
        ])->assertRedirect(route('loans.index'));

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-08-16',
            'installation_time' => '10:00',
            'installation_notes' => 'Use side gate.',
        ])->assertRedirect(route('loans.index'));

        $this->assertDatabaseCount('notifications', 2);
        $updatedNotification = DatabaseNotification::query()
            ->get()
            ->first(fn (DatabaseNotification $notification) => ($notification->data['installation_date'] ?? null) === '2026-08-16');
        $this->assertNotNull($updatedNotification);
        $this->assertSame((string) $consumerUser->id, (string) $updatedNotification->notifiable_id);
        $this->assertSame('Installation Schedule', $updatedNotification->data['title']);
        $this->assertSame('2026-08-16', $updatedNotification->data['installation_date']);
        $this->assertSame('10:00', $updatedNotification->data['installation_time']);
        $this->assertSame('Use side gate.', $updatedNotification->data['installation_notes']);
        $this->assertSame('Your installation schedule has been updated to August 16, 2026 at 10:00 AM.', $updatedNotification->data['message']);

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $loan), [
            'installation_date' => '2026-08-16',
            'installation_time' => '10:00',
            'installation_notes' => 'Use front gate.',
        ])->assertRedirect(route('loans.index'));

        $this->assertDatabaseCount('notifications', 3);
        $notesNotification = DatabaseNotification::query()
            ->get()
            ->first(fn (DatabaseNotification $notification) => ($notification->data['installation_notes'] ?? null) === 'Use front gate.');
        $this->assertNotNull($notesNotification);
        $this->assertSame('Use front gate.', $notesNotification->data['installation_notes']);
        $this->assertSame('Your installation schedule has been updated to August 16, 2026 at 10:00 AM.', $notesNotification->data['message']);

        $this->actingAs($admin)
            ->get('/loans')
            ->assertOk()
            ->assertSee('Installation schedule saved successfully.', false)
            ->assertSeeInOrder(['Installation Date', 'Aug 16, 2026', 'Installation Time', '10:00 AM'])
            ->assertSee('Use front gate.');

        $this->actingAs($consumerUser)
            ->get('/loans')
            ->assertOk()
            ->assertSee('Scheduled')
            ->assertSee('Aug 16, 2026')
            ->assertSee('10:00 AM')
            ->assertSee('Use front gate.')
            ->assertDontSee('loan_installation_date');

        $this->actingAs($otherUser)
            ->get('/loans')
            ->assertOk()
            ->assertDontSee('Aug 16, 2026')
            ->assertDontSee('Use front gate.');
    }

    public function test_installation_schedule_rejects_invalid_application_and_missing_configured_costs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $pendingLoan = $this->createLoan($consumer, [
            'amount' => 500,
            'status' => 'Rejected',
            'review_status' => 'Pending Review',
            'material_meter_cost' => 250,
            'material_hose_cost' => 150,
            'material_other_cost' => 100,
            'installation_cost_total' => 500,
        ]);

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $pendingLoan), [
            'installation_date' => '2026-08-15',
            'installation_time' => '09:30',
        ])->assertSessionHasErrors('installation');

        $pendingLoan->update(['status' => 'Pending Review']);
        $pendingLoan->forceFill([
            'material_meter_cost' => null,
            'material_hose_cost' => null,
            'material_other_cost' => null,
            'installation_cost_total' => null,
        ])->save();

        $this->actingAs($admin)->patch(route('loans.installation-schedule.update', $pendingLoan), [
            'installation_date' => '2026-08-15',
            'installation_time' => '09:30',
        ])->assertSessionHasErrors('installation');
    }

    public function test_consumer_apply_for_loan_button_stays_available_when_eligible_and_costs_are_configured(): void
    {
        [$user] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->setLoanMaterialRate(250, 150, 100);

        $this->actingAs($user)
            ->get('/loans')
            ->assertOk()
            ->assertSee('Apply for Loan')
            ->assertSee('data-bs-target="#loanModal"', false)
            ->assertSee('id="loanNextButton"', false)
            ->assertSee('Loan Meter Application Terms and Conditions')
            ->assertDontSee('Apply Loan Unavailable');
    }


    public function test_consumer_with_an_active_unpaid_loan_is_rejected_after_verification(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->setLoanMaterialRate(250, 150, 100);
        $existing = $this->createLoan($consumer, [
            'amount' => 1500,
            'status' => 'Approved',
            'purpose' => 'Existing active loan',
        ]);

        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('Apply for Loan');
        $this->submitApplication($user, 6, 'Second loan attempt')->assertSessionHasNoErrors();
        $application = $consumer->loans()->where('purpose', 'Second loan attempt')->sole();

        $evaluation = $this->verifyApplication($application);
        $this->assertSame('Rejected', $application->fresh()->status);
        $this->assertContains('blocking_or_overdue_loan', array_column($evaluation->failed_criteria, 'id'));
        $this->assertSame(1500.0, (float) $evaluation->evaluated_values['previous_loan_outstanding_balance']);
        $this->assertSame('Approved', $existing->fresh()->status);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee(DssEligibilityService::ACTIVE_LOAN_MESSAGE);
    }

    public function test_completed_loan_history_remains_available_and_does_not_block_application(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->setLoanMaterialRate(250, 150, 100);
        $loan = $this->createLoan($consumer, [
            'amount' => 1000,
            'status' => 'Completed',
            'purpose' => 'Completed historical loan',
        ]);

        LoanRepayment::query()->create([
            'loan_id' => $loan->id,
            'amount' => 1000,
            'payment_date' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/loans');

        $response
            ->assertOk()
            ->assertSee('Fully Paid')
            ->assertSee('Apply for Loan');
        $this->assertTrue($response->viewData('loans')->getCollection()->contains(fn (Loan $historyLoan) => $historyLoan->is($loan)));

        $this->submitApplication($user, 12, 'New loan after completion')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'purpose' => 'Completed historical loan',
            'status' => 'Completed',
        ]);
        $this->assertDatabaseHas('loans', [
            'consumer_id' => $consumer->id,
            'purpose' => 'New loan after completion',
            'status' => 'Needs Review',
            'income_verification_status' => 'Pending Verification',
            'term_months' => 12,
            'amount' => 500,
        ]);
    }

    public function test_admin_updates_material_rates_and_consumer_application_snapshots_latest_costs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$firstUser, $firstConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'account_number' => 'VV-2001',
            'meter_number' => 'M-2001',
        ]);
        [$secondUser, $secondConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'account_number' => 'VV-2002',
            'meter_number' => 'M-2002',
        ]);

        $this->actingAs($admin)->patch(route('loan-material-rates.update'), [
            'water_meter_cost' => 250,
            'hose_cost' => 150,
            'other_materials_cost' => 100,
        ])->assertRedirect(route('loans.index', ['tab' => 'costs']));

        $this->actingAs($firstUser)
            ->get('/loans')
            ->assertOk()
            ->assertSee("\u{20B1}500.00")
            ->assertSee('"month":6', false)
            ->assertSee('"amount":83.35', false)
            ->assertSee("termSelect.addEventListener('change'", false);

        $this->submitApplication($firstUser, 6, 'Water connection materials')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $firstLoan = Loan::query()->where('consumer_id', $firstConsumer->id)->firstOrFail();
        $this->assertEquals(500.00, (float) $firstLoan->amount);
        $this->assertEquals(250.00, (float) $firstLoan->material_meter_cost);
        $this->assertEquals(150.00, (float) $firstLoan->material_hose_cost);
        $this->assertEquals(100.00, (float) $firstLoan->material_other_cost);
        $this->assertEquals(500.00, (float) $firstLoan->installation_cost_total);
        $this->assertEquals(83.33, (float) $firstLoan->monthly_installment);
        $this->assertSame(
            [83.33, 83.33, 83.33, 83.33, 83.33, 83.35],
            collect($firstLoan->installment_schedule_snapshot)->pluck('amount')->all()
        );

        $this->actingAs($admin)->patch(route('loan-material-rates.update'), [
            'water_meter_cost' => 300,
            'hose_cost' => 180,
            'other_materials_cost' => 120,
        ])->assertRedirect(route('loans.index', ['tab' => 'costs']));

        $this->assertEquals(500.00, (float) $firstLoan->fresh()->installation_cost_total);

        $this->submitApplication($secondUser, 3, 'Water connection materials')
            ->assertRedirect(route('loans.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'consumer_id' => $secondConsumer->id,
            'amount' => 600,
            'term_months' => 3,
            'monthly_installment' => 200,
            'material_meter_cost' => 300,
            'material_hose_cost' => 180,
            'material_other_cost' => 120,
            'installation_cost_total' => 600,
        ]);
    }

    public function test_admin_can_update_installation_costs_without_a_hose_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->setLoanMaterialRate(250, 150, 100);
        $costsPage = route('loans.index', ['tab' => 'costs']);

        $this->actingAs($admin)->get($costsPage)->assertOk()
            ->assertSee('name="water_meter_cost"', false)
            ->assertSee('name="other_materials_cost"', false)
            ->assertDontSee('name="hose_cost"', false)
            ->assertDontSee('>Hose<', false);

        $this->from($costsPage)->patch(route('loan-material-rates.update'), [
            'water_meter_cost' => 300,
            'other_materials_cost' => 120,
        ])->assertRedirect($costsPage)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loan_material_rates', [
            'is_active' => true, 'water_meter_cost' => 300, 'hose_cost' => 150, 'other_materials_cost' => 120,
        ]);
        $this->get($costsPage)->assertOk()->assertSee("\u{20B1}570.00");
    }

    public function test_consumer_cannot_submit_when_admin_material_costs_are_unavailable(): void
    {
        [$user] = $this->consumerAccount('Member', now()->subMonths(7));

        $this->actingAs($user)
            ->get('/loans')
            ->assertOk()
            ->assertSee('The installation cost is not configured yet. Please contact the administrator.')
            ->assertSee('Apply Loan Unavailable')
            ->assertDontSee('id="loanModal"', false)
            ->assertDontSee('Submit Application');

        $this->submitApplication($user, 6, 'Water connection materials')->assertSessionHasErrors([
            'loan_material_rates' => 'The installation cost is currently unavailable. Please contact the administrator.',
        ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_installment_schedules_round_final_installment_for_each_allowed_term(): void
    {
        [$user] = $this->consumerAccount('Member', now()->subMonths(7));
        $this->setLoanMaterialRate(250, 150, 100);

        $response = $this->actingAs($user)->get('/loans');

        $schedules = $response->viewData('loanInstallmentSchedules');

        $this->assertSame([166.67, 166.67, 166.66], collect($schedules[3])->pluck('amount')->all());
        $this->assertSame([83.33, 83.33, 83.33, 83.33, 83.33, 83.35], collect($schedules[6])->pluck('amount')->all());
        $this->assertSame([41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.67, 41.63], collect($schedules[12])->pluck('amount')->all());
        $this->assertEquals(500, round(collect($schedules[3])->sum('amount'), 2));
        $this->assertEquals(500, round(collect($schedules[6])->sum('amount'), 2));
        $this->assertEquals(500, round(collect($schedules[12])->sum('amount'), 2));
    }

    public function test_admin_loan_queue_search_filters_by_consumer_name_or_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $firstConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'full_name' => 'Alice Applicant',
            'account_number' => 'VV-SEARCH-A',
            'meter_number' => 'M-SEARCH-A',
        ]);
        [, $secondConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'full_name' => 'Bob Applicant',
            'account_number' => 'VV-SEARCH-B',
            'meter_number' => 'M-SEARCH-B',
        ]);

        $this->createLoan($firstConsumer, [
            'status' => 'Pending Review',
            'review_status' => 'Pending Review',
            'purpose' => 'Pipe repair',
        ]);
        $this->createLoan($secondConsumer, [
            'status' => 'Pending Review',
            'review_status' => 'Pending Review',
            'purpose' => 'Meter upgrade',
        ]);

        foreach (['Alice Applicant', 'VV-SEARCH-A'] as $search) {
            $this->actingAs($admin)->get(route('loans.index', ['tab' => 'all', 'application_search' => $search]))
                ->assertOk()
                ->assertSee('Alice Applicant')
                ->assertDontSee('Bob Applicant')
                ->assertSee('name="application_search"', false);
        }
    }

    public function test_admin_loan_installation_filter_combines_with_loan_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [, $consumer] = $this->consumerAccount('Member', now()->subMonths(7), ['full_name' => 'Filter Applicant']);
        [, $otherConsumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'account_number' => 'VV-OTHER',
            'meter_number' => 'M-OTHER',
        ]);
        $schedule = ['installation_date' => today()->toDateString(), 'installation_time' => '10:00'];
        $pending = $this->createLoan($consumer, $schedule + ['installation_status' => 'Scheduled']);
        $completed = $this->createLoan($consumer, $schedule + ['installation_status' => 'Completed', 'installation_completed_at' => now()]);
        $this->createLoan($consumer, ['installation_status' => 'Not Scheduled']);
        $this->createLoan($consumer, $schedule + ['installation_status' => 'Scheduled', 'status' => 'Rejected']);
        $this->createLoan($otherConsumer, $schedule + ['installation_status' => 'Scheduled']);

        $filters = ['application_status' => 'approved', 'application_search' => $consumer->full_name];
        foreach (['pending' => $pending, 'completed' => $completed] as $installationStatus => $loan) {
            $response = $this->actingAs($admin)->get(route('loans.index', $filters + ['installation_status' => $installationStatus]))->assertOk();
            $this->assertSame([$loan->id], $response->viewData('adminApplications')->getCollection()->modelKeys());
            $response->assertSee('<option value="'.$installationStatus.'" selected>', false)
                ->assertSee('<option value="approved" selected>', false)
                ->assertSee('value="'.$consumer->full_name.'"', false);
        }

        $clearUrl = route('loans.index', ['tab' => 'all']);
        $this->assertSame(1, substr_count($response->getContent(), '>Clear</a>'));
        $response->assertSee('href="'.$clearUrl.'" class="btn btn-light border w-100"', false);
        $cleared = $this->get($clearUrl)->assertOk()
            ->assertSee('<option value="all" selected>All Installations</option>', false)
            ->assertSee('<option value="all" selected>All Status</option>', false);
        $this->assertSame('', $cleared->viewData('applicationSearch'));
        $this->assertSame(5, $cleared->viewData('adminApplications')->total());
    }

    public function test_consumer_loan_queue_filters_by_status_and_search(): void
    {
        [$user, $consumer] = $this->consumerAccount('Member', now()->subMonths(7), [
            'full_name' => 'Loan Filter Member',
            'account_number' => 'VV-LOAN-FILTER',
            'meter_number' => 'M-LOAN-FILTER',
        ]);

        $this->createLoan($consumer, [
            'status' => 'Approved',
            'purpose' => 'Garden hose',
        ]);
        $this->createLoan($consumer, [
            'status' => 'Pending Review',
            'review_status' => 'Pending Review',
            'purpose' => 'Meter upgrade',
        ]);

        $approved = $this->actingAs($user)->get(route('loans.index', ['status' => 'Approved']));
        $approved->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="loan_filter_status"', false);
        $this->assertSame(['Approved'], $approved->viewData('loans')->pluck('status')->all());

        $search = $this->actingAs($user)->get(route('loans.index', ['search' => 'Meter upgrade']));
        $search->assertOk();
        $this->assertSame(['Meter upgrade'], $search->viewData('loans')->pluck('purpose')->all());
    }


    private function submitApplication(User $user, int $term, string $purpose, array $overrides = []): TestResponse
    {
        $token = $this->actingAs($user)->postJson(route('loans.confirm-submission'), [
            'term_months' => $term,
            'terms_accepted' => 1,
            'confirmed' => 1,
        ])->assertOk()->json('confirmation_token');

        return $this->post(route('loans.store'), array_replace([
            'term_months' => $term,
            'purpose' => $purpose,
            'proof_of_income' => UploadedFile::fake()->image('proof.png'),
            'terms_accepted' => 1,
            'confirmation_token' => $token,
        ], $overrides));
    }

    private function verifyApplication(Loan $loan): LoanDssEvaluation
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('loans.verify-income', $loan), ['decision' => 'confirm'])
            ->assertSessionHasNoErrors();

        return $loan->fresh()->latestDssEvaluation()->sole();
    }


    private function recordApprovedDecision(Loan $loan): void
    {
        $loan->latestDssEvaluation()->create([
            'recommendation' => 'Approved',
            'evaluation_type' => 'initial',
            'criteria_version' => 'test',
            'evaluated_values' => [],
            'failed_criteria' => [],
            'missing_information' => [],
            'evidence_hash' => hash('sha256', 'approved-loan-'.$loan->id),
            'evaluated_at' => now(),
        ]);
    }

    private function consumerAccount(string $membershipType, $registeredAt, array $overrides = []): array
    {
        $user = User::factory()->create([
            'role' => 'consumer',
        ]);

        $consumer = Consumer::create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Loan Applicant',
            'account_number' => 'VV-1001',
            'meter_number' => 'M-1001',
            'status' => 'Active',
            'membership_type' => $membershipType,
            'membership_verification_status' => $membershipType === 'Member' ? Consumer::MEMBERSHIP_VERIFICATION_VERIFIED : null,
            'ownership_status' => 'Owner',
        ], $overrides));

        $consumer->forceFill([
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
            'membership_started_at' => $membershipType === 'Member' ? $registeredAt : null,
        ])->saveQuietly();

        return [$user, $consumer->fresh()];
    }

    private function createLoan(Consumer $consumer, array $overrides = []): Loan
    {
        return Loan::query()->create(array_merge([
            'consumer_id' => $consumer->id,
            'applied_by_user_id' => $consumer->user_id,
            'amount' => 1000,
            'term_months' => 6,
            'purpose' => 'Existing loan',
            'status' => 'Approved',
            'risk_level' => 'Low Risk',
            'monthly_installment' => 166.67,
            'applied_at' => now()->subMonth(),
            'approved_at' => now()->subMonth(),
            'next_due_date' => now()->addMonth(),
        ], $overrides));
    }

    private function addPaidBill(Consumer $consumer): void
    {
        $bill = Bill::create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonth()->startOfMonth(),
            'due_date' => now()->subMonth()->endOfMonth(),
            'prn' => 'PRN-'.$consumer->id,
            'amount' => 500,
            'status' => 'Paid',
        ]);

        Payment::create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-'.$consumer->id,
            'amount' => 500,
            'payment_date' => $bill->due_date,
            'payment_method' => 'Cash',
            'status' => 'Verified',
        ]);
    }

    private function addUnpaidBill(Consumer $consumer): void
    {
        Bill::create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonth()->startOfMonth(),
            'due_date' => now()->subDays(14),
            'prn' => 'PRN-UNPAID-'.$consumer->id,
            'amount' => 500,
            'status' => 'Unpaid',
        ]);
    }

    private function addLatePaidBill(Consumer $consumer): void
    {
        $bill = Bill::create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonths(2)->startOfMonth(),
            'due_date' => now()->subMonths(2)->startOfMonth()->addDays(9),
            'prn' => 'PRN-LATE-'.$consumer->id,
            'amount' => 500,
            'status' => 'Paid',
        ]);

        Payment::create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-LATE-'.$consumer->id,
            'amount' => 500,
            'payment_date' => $bill->due_date->copy()->addDays(5),
            'payment_method' => 'Cash',
            'status' => 'Verified',
        ]);
    }

    private function setLoanMaterialRate(float $meter, float $hose, float $other): LoanMaterialRate
    {
        LoanMaterialRate::query()->where('is_active', true)->update(['is_active' => false]);

        return LoanMaterialRate::query()->create([
            'water_meter_cost' => $meter,
            'hose_cost' => $hose,
            'other_materials_cost' => $other,
            'is_active' => true,
        ]);
    }
}
