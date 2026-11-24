<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\InstallationRescheduleRequest;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InstallationRescheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createConsumerAccount(array $consumerOverrides = []): array
    {
        $user = User::factory()->create([
            'role' => 'consumer',
            'username' => 'MT-99999',
            'email' => 'consumer@example.com',
        ]);

        $consumer = Consumer::create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Juan Dela Cruz',
            'email' => 'consumer@example.com',
            'account_number' => 'VV-1001',
            'meter_number' => 'MT-99999',
            'status' => 'Active',
            'membership_type' => 'Member',
            'requested_membership_type' => 'Member',
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_VERIFIED,
            'installation_date' => '2026-11-10',
            'service_address' => 'Purok 1, Block 2, Lot 3, Panabo City',
        ], $consumerOverrides));

        return [$user, $consumer];
    }

    private function createLoan(Consumer $consumer, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'consumer_id' => $consumer->id,
            'amount' => 1200.00,
            'term_months' => 6,
            'purpose' => 'Water Service Installation',
            'material_meter_cost' => 600.00,
            'material_hose_cost' => 300.00,
            'material_other_cost' => 300.00,
            'installation_cost_total' => 1200.00,
            'status' => 'Approved',
            'review_status' => 'Reviewed',
            'installation_status' => 'Scheduled',
            'installation_date' => '2026-11-15',
            'installation_time' => '10:00:00',
            'installation_notes' => 'Near front gate',
            'monthly_installment' => 200.00,
            'applied_at' => now(),
        ], $overrides));
    }

    public function test_connection_activation_email_includes_reschedule_button(): void
    {
        $admin = $this->createAdmin();

        $applicant = Consumer::create([
            'full_name' => 'Maria Clara',
            'email' => 'maria@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
            'requested_membership_type' => 'Non-Member',
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
            'service_address' => 'Purok 3, Panabo City',
        ]);

        $payload = [
            'meter_number' => 'MT-12345',
            'membership_type' => 'Non-Member',
            'installation_date' => '2026-11-20',
        ];

        $captured = [];
        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.account-activated';
            });

        $response = $this->actingAs($admin)
            ->patch(route('consumers.activate', $applicant), $payload);

        $response->assertRedirect(route('consumers.index'));

        $this->assertNotEmpty($captured);
        $this->assertSame('November 20, 2026', $captured['data']['installationDate']);
        $this->assertNotEmpty($captured['data']['rescheduleUrl']);

        // Verify the email view renders the Request for Reschedule button
        $rendered = view($captured['view'], $captured['data'])->render();

        $this->assertStringContainsString('Request for Reschedule', $rendered);
        $this->assertStringContainsString('installation/reschedule/connection/'.$applicant->id, $rendered);
    }

    public function test_loan_installation_schedule_sends_email_with_reschedule_button(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->startOfDay());

        $admin = $this->createAdmin();
        [, $consumer] = $this->createConsumerAccount();
        $loan = $this->createLoan($consumer, [
            'installation_status' => 'Not Scheduled',
            'installation_date' => null,
            'installation_time' => null,
            'status' => 'Ready for Scheduling',
            'review_status' => 'Reviewed',
            'income_verification_status' => 'Verified',
            'approved_at' => now(),
        ]);
        LoanDssEvaluation::create([
            'loan_id' => $loan->id,
            'recommendation' => 'Approved',
            'evaluation_type' => 'initial',
            'criteria_version' => 'test',
            'evidence_hash' => hash('sha256', 'installation-email'),
            'evaluated_values' => [],
            'failed_criteria' => [],
            'missing_information' => [],
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('loans.assign-and-schedule', $loan), [
                'meter_number' => 'MT-99998',
                'installation_date' => '2026-11-25',
                'installation_time' => '14:30',
                'installation_notes' => 'Call upon arrival.',
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('MT-99998', $consumer->fresh()->meter_number);

        $loan->refresh();
        $this->assertSame('Scheduled', $loan->installation_status);
        $this->assertSame('2026-11-25', $loan->installation_date->toDateString());
        $this->assertSame('14:30', substr((string) $loan->installation_time, 0, 5));

        $message = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame($consumer->email, $message->getTo()[0]->getAddress());
        $this->assertSame([], $message->getAttachments());
        foreach (['MT-99998', 'November 25, 2026', '2:30 PM', 'Call upon arrival.', 'Request for Reschedule', 'installation/reschedule/loan/'.$loan->id] as $value) {
            $this->assertStringContainsString($value, $message->getHtmlBody());
        }

        $loanFilters = ['application_status' => 'approved', 'application_search' => $consumer->account_number];
        $loanPage = route('loans.index', $loanFilters + ['installation_status' => 'pending']);
        $completedPage = route('loans.index', $loanFilters + ['installation_status' => 'completed']);
        $activePage = route('consumers.index', ['account_category' => 'active', 'search' => $consumer->account_number, 'installation_status' => 'pending']);
        $completionRoute = route('loans.installation-status.update', $loan);
        $this->createLoan($consumer, ['installation_status' => 'Not Scheduled', 'installation_date' => null, 'installation_time' => null]);
        $consumerBeforeCompletion = $consumer->fresh()->getRawOriginal();

        $this->get($loanPage)->assertOk()
            ->assertSee('class="badge bg-primary">Pending Installation', false)
            ->assertSee('Scheduled Date: Nov 25, 2026')
            ->assertSee('Mark Complete')
            ->assertSee($completionRoute, false);
        $this->get($activePage)->assertOk()
            ->assertSee('Scheduled Date: Nov 10, 2026')
            ->assertDontSee($completionRoute, false);

        $this->from($loanPage)->patch($completionRoute, ['installation_status' => 'Completed'])
            ->assertSessionHasErrors('physical_installation_confirmed');
        $this->assertNull($loan->fresh()->installation_completed_at);

        $this->travelTo(now()->setDate(2026, 11, 26)->setTime(9, 45));
        $confirmation = ['installation_status' => 'Completed', 'physical_installation_confirmed' => '1'];
        $this->from($loanPage)->patch($completionRoute, $confirmation)
            ->assertRedirect($loanPage)->assertSessionHasNoErrors();

        $completed = $loan->fresh();
        $this->assertSame('Completed', $completed->installation_status);
        $this->assertSame('2026-11-26 09:45:00', $completed->installation_completed_at->toDateTimeString());
        $this->assertSame($admin->id, $completed->installation_completed_by);
        $this->assertSame('2026-11-25', $completed->installation_date->toDateString());
        $this->assertTrue($completed->installation_completed_at->eq($completed->released_at));
        $this->assertSame($consumerBeforeCompletion, $consumer->fresh()->getRawOriginal());

        $this->travel(1)->days();
        $this->from($loanPage)->patch($completionRoute, $confirmation)
            ->assertRedirect($loanPage)->assertSessionHasNoErrors();
        $this->assertTrue($loan->fresh()->installation_completed_at->eq($completed->installation_completed_at));

        $this->get($loanPage)->assertOk()->assertDontSee($consumer->full_name);
        $this->get($completedPage)->assertOk()
            ->assertSee($consumer->full_name)
            ->assertSee('class="badge bg-success">Completed', false)
            ->assertSee('Completed Date: Nov 26, 2026')
            ->assertDontSee('Mark Complete');
        $this->get($activePage)->assertOk()
            ->assertSee($consumer->full_name)
            ->assertSee('Scheduled Date: Nov 10, 2026')
            ->assertSee('Mark Complete')
            ->assertDontSee('Completed Date: Nov 26, 2026');
    }

    public function test_consumer_can_submit_connection_reschedule_request_with_reason_only(): void
    {
        $admin = $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount();

        // Consumer cannot select a new date: any attempted new_installation_date is ignored
        $response = $this->actingAs($user)
            ->post(route('installation.reschedule.connection.store', $consumer), [
                'reason' => 'I have an emergency business trip on the scheduled date.',
                'new_installation_date' => '2026-12-01', // Attempted bypass
            ]);

        $expectedSuccess = 'Your reschedule request has been submitted successfully! The administrator has been notified and will review your request. You will receive an email once a decision has been made.';
        $response->assertRedirect(URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]));
        $response->assertSessionHas('success', $expectedSuccess);
        $response->assertSessionHas('reschedule_confirmation', function (array $confirmation): bool {
            return $confirmation['origin'] === 'portal'
                && $confirmation['destination'] === route('connection.status');
        });

        $followResponse = $this->get($response->headers->get('Location'));
        $followResponse->assertOk();
        $followResponse->assertSee($expectedSuccess);
        $followResponse->assertSee('id="rescheduleConfirmationModal"', false);
        $followResponse->assertSee('>OK</a>', false);
        $followResponse->assertDontSee('Back to Portal');

        $reschedule = InstallationRescheduleRequest::where('consumer_id', $consumer->id)->firstOrFail();
        $this->assertSame('connection', $reschedule->reschedule_type);
        $this->assertSame('pending', $reschedule->status);
        $this->assertSame('I have an emergency business trip on the scheduled date.', $reschedule->reason);
        $this->assertSame('2026-11-10', $reschedule->original_installation_date->toDateString());
        $this->assertNull($reschedule->new_installation_date); // Strictly null; consumer cannot set date

        // Verify admin notification contains consumer name, reference, current date, and reason
        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Installation Reschedule Requested', $notification->data['title']);
        $this->assertStringContainsString('Juan Dela Cruz', $notification->data['message']);
        $this->assertSame('Juan Dela Cruz', $notification->data['consumer_name']);
        $this->assertNotEmpty($notification->data['reference']);
        $this->assertStringContainsString($notification->data['reference'], $notification->data['message']);
        $this->assertSame('November 10, 2026', $notification->data['current_installation_date']);
        $this->assertStringContainsString('November 10, 2026', $notification->data['message']);
        $this->assertSame('I have an emergency business trip on the scheduled date.', $notification->data['reason']);
        $this->assertStringContainsString('I have an emergency business trip on the scheduled date.', $notification->data['message']);
    }

    public function test_consumer_cannot_submit_duplicate_pending_reschedule_requests(): void
    {
        [$user, $consumer] = $this->createConsumerAccount();

        InstallationRescheduleRequest::create([
            'consumer_id' => $consumer->id,
            'reschedule_type' => 'connection',
            'status' => 'pending',
            'reason' => 'First reschedule request pending',
            'original_installation_date' => $consumer->installation_date,
        ]);

        $response = $this->actingAs($user)
            ->post(route('installation.reschedule.connection.store', $consumer), [
                'reason' => 'Second attempt should be blocked',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(1, InstallationRescheduleRequest::where('consumer_id', $consumer->id)->count());
    }

    public function test_consumer_cannot_reschedule_another_consumers_installation(): void
    {
        [$user1, $consumer1] = $this->createConsumerAccount();
        [$user2, $consumer2] = $this->createConsumerAccount([
            'account_number' => 'VV-1002',
            'meter_number' => 'MT-88888',
            'email' => 'consumer2@example.com',
        ]);

        $response = $this->actingAs($user1)
            ->post(route('installation.reschedule.connection.store', $consumer2), [
                'reason' => 'Tampered attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_guest_can_submit_reschedule_via_valid_signed_url(): void
    {
        $this->createAdmin();
        [, $consumer] = $this->createConsumerAccount();

        $signedUrl = URL::signedRoute('installation.reschedule.connection.store', ['consumer' => $consumer->id]);

        $response = $this->post($signedUrl, [
            'reason' => 'Submitted from email signed link without logging in.',
        ]);

        $expectedSuccess = 'Your reschedule request has been submitted successfully! The administrator has been notified and will review your request. You will receive an email once a decision has been made.';
        $response->assertRedirect(URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]));
        $response->assertSessionHas('success', $expectedSuccess);
        $response->assertSessionHas('reschedule_confirmation', function (array $confirmation): bool {
            return $confirmation['origin'] === 'email';
        });
        $this->assertDatabaseHas('installation_reschedule_requests', [
            'consumer_id' => $consumer->id,
            'status' => 'pending',
            'reason' => 'Submitted from email signed link without logging in.',
        ]);
    }

    public function test_reschedule_page_does_not_show_back_to_portal_button(): void
    {
        [$user, $consumer] = $this->createConsumerAccount();

        $this->actingAs($user)
            ->get(route('installation.reschedule.connection', $consumer))
            ->assertOk()
            ->assertDontSee('Back to Portal')
            ->assertDontSee('id="rescheduleConfirmationModal"', false);

        $signedUrl = URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertDontSee('Back to Portal')
            ->assertDontSee('id="rescheduleConfirmationModal"', false);
    }

    public function test_email_submission_modal_offers_clear_return_to_email_instruction(): void
    {
        $this->createAdmin();
        [, $consumer] = $this->createConsumerAccount();

        $response = $this->post(URL::signedRoute('installation.reschedule.connection.store', ['consumer' => $consumer->id]), [
            'reason' => 'Opened from the email link.',
        ]);

        $response->assertRedirect(URL::signedRoute('installation.reschedule.connection', ['consumer' => $consumer->id]));

        $confirmation = $response->getSession()->get('reschedule_confirmation');

        $this->assertSame('email', $confirmation['origin']);
        $this->assertStringContainsString('return_to_email=1', $confirmation['destination']);

        $followResponse = $this->get($response->headers->get('Location'));
        $followResponse->assertOk();
        $followResponse->assertSee('Your reschedule request has been submitted successfully! The administrator has been notified and will review your request. You will receive an email once a decision has been made.');
        $followResponse->assertSee('After clicking OK, we will show you how to return to your email.');
        $followResponse->assertSee('id="rescheduleConfirmationModal"', false);
        $followResponse->assertSee('>OK</a>', false);

        $returnToEmailResponse = $this->get($confirmation['destination']);
        $returnToEmailResponse->assertOk();
        $returnToEmailResponse->assertSee('Please return to your email inbox.');
        $returnToEmailResponse->assertSee('This page cannot reopen your email application or tab');
        $returnToEmailResponse->assertDontSee('id="rescheduleConfirmationModal"', false);
    }

    public function test_submission_failure_shows_error_and_rolls_back_request(): void
    {
        $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount();

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Notification service unavailable.'));

        $response = $this->actingAs($user)
            ->post(route('installation.reschedule.connection.store', $consumer), [
                'reason' => 'A valid reason that must not be saved without an admin notification.',
            ]);

        $response->assertSessionHasErrors('reason');
        $response->assertSessionMissing('reschedule_confirmation');

        $this->assertDatabaseCount('installation_reschedule_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_submission_fails_when_no_admin_can_be_notified(): void
    {
        [$user, $consumer] = $this->createConsumerAccount();

        $response = $this->actingAs($user)
            ->post(route('installation.reschedule.connection.store', $consumer), [
                'reason' => 'A valid reason that requires an administrator notification.',
            ]);

        $response->assertSessionHasErrors('reason');
        $response->assertSessionMissing('reschedule_confirmation');

        $this->assertDatabaseCount('installation_reschedule_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_pending_reschedule_requests_appear_under_admin_pending_accounts(): void
    {
        $admin = $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount();

        InstallationRescheduleRequest::create([
            'consumer_id' => $consumer->id,
            'reschedule_type' => 'connection',
            'status' => 'pending',
            'reason' => 'Need schedule change.',
            'original_installation_date' => $consumer->installation_date,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'pending']));

        $response->assertOk();
        $response->assertSee('Reschedule Request: Connection');
        $response->assertSee('Assign New Schedule');
        $response->assertSee('Need schedule change.');

        // Test sub-filtering by reschedules only
        $responseSub = $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']));

        $responseSub->assertOk();
        $responseSub->assertSee($consumer->full_name);
    }

    public function test_admin_can_approve_reschedule_and_assign_new_installation_date(): void
    {
        $admin = $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount(['installation_date' => '2026-11-10']);

        $reschedule = InstallationRescheduleRequest::create([
            'consumer_id' => $consumer->id,
            'reschedule_type' => 'connection',
            'status' => 'pending',
            'reason' => 'Work conflict.',
            'original_installation_date' => '2026-11-10',
        ]);

        $captured = [];
        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.installation-rescheduled';
            });

        $response = $this->actingAs($admin)
            ->patch(route('installation-reschedules.approve', $reschedule), [
                'new_installation_date' => '2026-11-28',
                'new_installation_time' => '09:30',
                'admin_notes' => 'Rescheduled to Saturday as requested.',
            ]);

        $response->assertSessionHas('success');

        $reschedule->refresh();
        $consumer->refresh();

        $this->assertSame('assigned', $reschedule->status);
        $this->assertSame('2026-11-28', $reschedule->new_installation_date->toDateString());
        $this->assertSame('Rescheduled to Saturday as requested.', $reschedule->admin_notes);
        $this->assertSame($admin->id, $reschedule->reviewed_by_user_id);
        $this->assertNotNull($reschedule->reviewed_at);

        // Underlying consumer installation date is updated to the new date
        $this->assertSame('2026-11-28', $consumer->installation_date->toDateString());

        // Updated schedule email sent to consumer
        $this->assertNotEmpty($captured);
        $this->assertSame('November 28, 2026 at 9:30 AM', $captured['data']['newSchedule']);
        $rendered = view($captured['view'], $captured['data'])->render();
        $this->assertStringContainsString('Request for Reschedule', $rendered);

        // Verify consumer notification
        $notification = DatabaseNotification::query()->where('notifiable_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('New Installation Schedule Assigned', $notification->data['title']);

        // Consumer is now free to request another reschedule if necessary
        $newResponse = $this->actingAs($user)
            ->post(route('installation.reschedule.connection.store', $consumer), [
                'reason' => 'Another scheduling conflict arose.',
            ]);
        $newResponse->assertSessionHas('success');
        $this->assertSame(2, InstallationRescheduleRequest::where('consumer_id', $consumer->id)->count());
    }

    public function test_rejection_is_retired_and_does_not_change_existing_schedule_or_request(): void
    {
        $admin = $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount(['installation_date' => '2026-11-10']);
        $request = InstallationRescheduleRequest::create([
            'consumer_id' => $consumer->id, 'reschedule_type' => 'connection', 'status' => 'pending',
            'reason' => 'Moving date.', 'original_installation_date' => '2026-11-10',
        ]);
        Mail::shouldReceive('send')->never();
        $this->actingAs($admin)->patch(route('installation-reschedules.reject', $request), ['admin_notes' => 'Old action.'])
            ->assertStatus(410);
        $this->assertSame('pending', $request->refresh()->status);
        $this->assertNull($request->assigned_at);
        $this->assertSame('2026-11-10', $consumer->refresh()->installation_date->toDateString());
    }

    public function test_loan_reschedule_flow_works_end_to_end(): void
    {
        $admin = $this->createAdmin();
        [$user, $consumer] = $this->createConsumerAccount();
        $loan = $this->createLoan($consumer, [
            'installation_status' => 'Scheduled',
            'installation_date' => '2026-11-15',
            'installation_time' => '09:00:00',
        ]);

        // 1. Consumer submits loan reschedule request
        $response = $this->actingAs($user)
            ->post(route('installation.reschedule.loan.store', $loan), [
                'reason' => 'Household head is out of the country on Nov 15.',
            ]);

        $response->assertRedirect(route('installation.reschedule.loan', $loan));
        $response->assertSessionHas('success');
        $response->assertSessionHas('reschedule_confirmation', function (array $confirmation): bool {
            return $confirmation['origin'] === 'portal'
                && $confirmation['destination'] === route('loans.index');
        });

        $followResponse = $this->get($response->headers->get('Location'));
        $followResponse->assertOk();
        $followResponse->assertSee('id="rescheduleConfirmationModal"', false);
        $followResponse->assertSee('>OK</a>', false);

        $reschedule = InstallationRescheduleRequest::where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame('loan', $reschedule->reschedule_type);
        $this->assertSame('pending', $reschedule->status);
        $this->assertSame('2026-11-15', $reschedule->original_installation_date->toDateString());
        $this->assertSame('09:00:00', $reschedule->original_installation_time);

        // 2. Admin approves loan reschedule with new date and time
        $captured = [];
        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured): bool {
                $captured = ['view' => $view, 'data' => $data];

                return $view === 'emails.consumer.installation-rescheduled';
            })->andReturn(\Mockery::mock(\Illuminate\Mail\SentMessage::class));

        $approveResponse = $this->actingAs($admin)
            ->patch(route('installation-reschedules.approve', $reschedule), [
                'new_installation_date' => '2026-11-30',
                'new_installation_time' => '13:00',
                'admin_notes' => 'Assigned to afternoon slot.',
            ]);

        $approveResponse->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame('2026-11-30', $loan->installation_date->toDateString());
        $this->assertSame('13:00', substr((string) $loan->installation_time, 0, 5));
        $this->assertSame('Scheduled', $loan->installation_status);

        $this->assertNotEmpty($captured);
        $this->assertSame('November 30, 2026 at 1:00 PM', $captured['data']['newSchedule']);
        $rendered = view($captured['view'], $captured['data'])->render();
        $this->assertStringContainsString('Request for Reschedule', $rendered);
    }

    public function test_complete_flow_from_email_button_click_to_admin_approval(): void
    {
        $admin = $this->createAdmin();

        $applicant = Consumer::create([
            'full_name' => 'Maria Clara',
            'email' => 'maria.clara@example.com',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
            'requested_membership_type' => 'Non-Member',
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED,
            'service_address' => 'Purok 3, Panabo City',
        ]);

        $capturedMail = [];
        Mail::shouldReceive('send')
            ->atLeast()->once()
            ->withArgs(function (string $view, array $data) use (&$capturedMail): bool {
                $capturedMail[$view] = $data;

                return true;
            });

        // Step 1: Admin activates account and schedules installation
        $activateResponse = $this->actingAs($admin)
            ->patch(route('consumers.activate', $applicant), [
                'meter_number' => 'MT-77701',
                'membership_type' => 'Non-Member',
                'installation_date' => '2026-11-12',
            ]);

        $activateResponse->assertRedirect(route('consumers.index'));
        $applicant->refresh();

        $this->assertArrayHasKey('emails.consumer.account-activated', $capturedMail);
        $emailData = $capturedMail['emails.consumer.account-activated'];
        $rescheduleUrl = $emailData['rescheduleUrl'];
        $this->assertNotEmpty($rescheduleUrl);

        // Step 2: Consumer clicks the email button (opens signed URL)
        $emailClickResponse = $this->get($rescheduleUrl);
        $emailClickResponse->assertOk();
        $emailClickResponse->assertSee('Request for Reschedule');
        $emailClickResponse->assertSee('Maria Clara');
        $emailClickResponse->assertSee('November 12, 2026');

        // Step 3: Consumer submits reschedule request with reason only (no date choice)
        $postUrl = URL::signedRoute('installation.reschedule.connection.store', ['consumer' => $applicant->id]);
        $submitResponse = $this->post($postUrl, [
            'reason' => 'Family emergency scheduled on that date.',
            'new_installation_date' => '2026-12-05', // Attempted consumer date override must be ignored
        ]);

        $expectedSuccess = 'Your reschedule request has been submitted successfully! The administrator has been notified and will review your request. You will receive an email once a decision has been made.';
        $submitResponse->assertSessionHas('success', $expectedSuccess);

        // Follow the redirect back to the signed view and verify the confirmation modal appears
        $followResponse = $this->get($submitResponse->headers->get('Location'));
        $followResponse->assertOk();
        $followResponse->assertSee($expectedSuccess);
        $followResponse->assertSee('id="rescheduleConfirmationModal"', false);
        $followResponse->assertSee('>OK</a>', false);
        $followResponse->assertDontSee('Back to Portal');

        // Step 4: Admin receives notification containing consumer's name, reference, current date, and reason
        $notification = DatabaseNotification::query()->where('notifiable_id', $admin->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Installation Reschedule Requested', $notification->data['title']);
        $this->assertSame('Maria Clara', $notification->data['consumer_name']);
        $this->assertNotEmpty($notification->data['reference']);
        $this->assertSame('November 12, 2026', $notification->data['current_installation_date']);
        $this->assertSame('Family emergency scheduled on that date.', $notification->data['reason']);
        $this->assertStringContainsString('Maria Clara', $notification->data['message']);
        $this->assertStringContainsString($notification->data['reference'], $notification->data['message']);
        $this->assertStringContainsString('November 12, 2026', $notification->data['message']);
        $this->assertStringContainsString('Family emergency scheduled on that date.', $notification->data['message']);

        // Step 5: Request appears under Consumers -> Pending Accounts -> Reschedule Requests
        $pendingAccountsResponse = $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'pending', 'pending_type' => 'reschedules']));

        $pendingAccountsResponse->assertOk();
        $pendingAccountsResponse->assertSee('Reschedule Requests (1)');
        $pendingAccountsResponse->assertSee('Maria Clara');
        $pendingAccountsResponse->assertSee('Assign New Schedule');
        $pendingAccountsResponse->assertSee('Family emergency scheduled on that date.');

        // Step 6: Admin opens modal details and approves the request with a new installation date
        $reschedule = InstallationRescheduleRequest::where('consumer_id', $applicant->id)->firstOrFail();
        $this->assertNull($reschedule->new_installation_date); // Consumer was NOT allowed to set date

        $adminApproveResponse = $this->actingAs($admin)
            ->patch(route('installation-reschedules.approve', $reschedule), [
                'new_installation_date' => '2026-11-20',
                'new_installation_time' => '10:00',
                'admin_notes' => 'Moved to next week Friday per request.',
            ]);

        $adminApproveResponse->assertSessionHas('success');

        // Verify updated state and email to consumer
        $reschedule->refresh();
        $applicant->refresh();

        $this->assertSame('assigned', $reschedule->status);
        $this->assertSame('2026-11-20', $reschedule->new_installation_date->toDateString());
        $this->assertSame('2026-11-20', $applicant->installation_date->toDateString());

        $this->assertArrayHasKey('emails.consumer.installation-rescheduled', $capturedMail);
        $updatedEmailData = $capturedMail['emails.consumer.installation-rescheduled'];
        $this->assertSame('November 20, 2026 at 10:00 AM', $updatedEmailData['newSchedule']);

        // After approval, pending count returns to 0
        $this->assertSame(0, InstallationRescheduleRequest::pending()->count());
    }
}
