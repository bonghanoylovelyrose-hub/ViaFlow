<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\BillingComputationService;
use App\Services\ConnectionRequestService;
use App\Services\DssEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registrationPayload(string $email): array
    {
        return [
            'first_name' => 'Membership',
            'last_name' => 'Applicant',
            'email' => $email,
            'service_country' => 'Philippines',
            'service_city' => 'Panabo City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 1-C',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png', 1, 1),
            'acknowledgment_accepted' => '1',
        ];
    }

    private function memberApplicant(array $overrides = []): Consumer
    {
        $consumer = Consumer::create(array_merge([
            'full_name' => 'Pending Member Applicant',
            'email' => 'pending.member@example.com',
            'status' => 'Pending Verification',
            'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'requested_membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_PENDING,
            'association_member' => true,
            'association_registered' => true,
            'ownership_status' => 'Owner',
        ], $overrides));

        app(ConnectionRequestService::class)->createForApplication($consumer);

        return $consumer;
    }

    public function test_member_registration_keeps_the_request_pending_and_preserves_actual_association_answers(): void
    {
        $this->withSession([
            'register_connection_flow' => [
                'is_viohoa_member' => 'yes',
                'declared_viohoa_member' => true,
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            ],
            'register_member_details' => [
                'association_member' => false,
                'association_registered' => false,
                'agreement_accepted' => true,
            ],
        ])->post(route('register.store'), $this->registrationPayload('declared.member@example.com'))
            ->assertRedirect(route('login'));

        $consumer = Consumer::query()
            ->where('email', 'declared.member@example.com')
            ->firstOrFail();

        // The applicant is classified as a Member from their answer, while the
        // separate verification status keeps benefits locked until review.
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->requested_membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
        $this->assertNull($consumer->membership_reviewed_at);
        $this->assertNull($consumer->membership_reviewed_by);
        $this->assertNull($consumer->membership_started_at);

        $connectionRequest = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->firstOrFail();

        $this->assertSame(ServiceRequest::STATUS_PENDING, $connectionRequest->status);
        $this->assertStringContainsString('Declared VVTHOA membership: Member', $connectionRequest->notes);

        // The applicant's actual answers must not be overwritten by the
        // top-level "I am a member" declaration.
        $this->assertFalse($consumer->association_member);
        $this->assertFalse($consumer->association_registered);
        $this->assertFalse($consumer->isVerifiedViohoaMember());
    }

    public function test_member_workflow_is_pending_verification_even_without_a_separate_declaration_flag(): void
    {
        $this->withSession([
            'register_connection_flow' => [
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            ],
            'register_member_details' => [
                'association_member' => true,
                'association_registered' => true,
                'agreement_accepted' => true,
            ],
        ])->post(route('register.store'), $this->registrationPayload('member.workflow@example.com'))
            ->assertRedirect(route('login'));

        $consumer = Consumer::query()
            ->where('email', 'member.workflow@example.com')
            ->firstOrFail();

        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->requested_membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
        $this->assertTrue($consumer->association_member);
        $this->assertTrue($consumer->association_registered);
        $this->assertSame(
            ServiceRequest::STATUS_PENDING,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_non_member_registration_does_not_require_membership_verification(): void
    {
        $this->withSession([
            'register_connection_flow' => [
                'is_viohoa_member' => 'no',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_NON_MEMBER,
            ],
        ])->post(route('register.store'), $this->registrationPayload('non.member@example.com'))
            ->assertRedirect(route('login'));

        $consumer = Consumer::query()
            ->where('email', 'non.member@example.com')
            ->firstOrFail();

        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_NON_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_NON_MEMBER, $consumer->requested_membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_NOT_REQUIRED, $consumer->membership_verification_status);
        $this->assertSame(
            ServiceRequest::STATUS_PENDING,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_admin_consumer_list_surfaces_pending_member_claims(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->memberApplicant(['full_name' => 'Listed Member Applicant']);

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertSee('Listed Member Applicant')
            ->assertDontSee('Member Applications &amp; Membership Reviews', false)
            ->assertDontSee('Review pending verifications')
            ->assertDontSee('member-applications', false)
            ->assertSee('Verification pending')
            ->assertSee('Existing VVTHOA member')
            ->assertSee('Connection Request: Pending')
            ->assertDontSee('Member applicant')
            ->assertDontSee('Requested: Member')
            ->assertDontSee('membership=pending_member_verification', false)
            ->assertSee('activation_membership_verification_confirmed', false)
            ->assertSee('edit_membership_verification_notes', false)
            ->assertSee('edit_membership_verification_confirmed', false)
            ->assertSee('edit_membership_pending_panel', false);
    }

    public function test_activation_as_member_requires_explicit_verification_confirmation(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant();

        $this->actingAs($admin)
            ->patch(route('consumers.activate', $consumer), [
                'meter_number' => 'MT-MV-001',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
                'installation_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasErrors('membership_verification_confirmed');

        $consumer->refresh();
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
        $this->assertNull($consumer->user_id);
        $this->assertSame(
            ServiceRequest::STATUS_PENDING,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
        Mail::assertNothingSent();
    }

    public function test_activation_as_member_records_a_verified_decision(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant();

        $this->actingAs($admin)
            ->patch(route('consumers.activate', $consumer), [
                'meter_number' => 'MT-MV-002',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
                'membership_verification_confirmed' => '1',
                'membership_verification_notes' => 'Association registration and uploaded proof were reviewed.',
                'installation_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame('Active', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_VERIFIED, $consumer->membership_verification_status);
        $this->assertSame($admin->id, $consumer->membership_reviewed_by);
        $this->assertNotNull($consumer->membership_reviewed_at);
        $this->assertSame(
            'Association registration and uploaded proof were reviewed.',
            $consumer->membership_verification_notes,
        );
        $this->assertNotNull($consumer->membership_started_at);

        $connectionRequest = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->firstOrFail();

        $this->assertSame(ServiceRequest::STATUS_APPROVED, $connectionRequest->status);
        $this->assertSame($admin->id, $connectionRequest->reviewed_by_user_id);
        $this->assertNotNull($connectionRequest->reviewed_at);
    }

    public function test_activation_as_non_member_records_the_claim_as_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant();

        $this->actingAs($admin)
            ->patch(route('consumers.activate', $consumer), [
                'meter_number' => 'MT-MV-003',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_NON_MEMBER,
                'installation_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame('Active', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_NON_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_REJECTED, $consumer->membership_verification_status);
        $this->assertSame($admin->id, $consumer->membership_reviewed_by);
        $this->assertSame('Membership claim was not verified.', $consumer->membership_verification_notes);
        $this->assertSame(
            ServiceRequest::STATUS_APPROVED,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_admin_edit_records_a_membership_verification_decision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Regular Consumer',
            'email' => 'regular.consumer@example.com',
            'account_number' => 'MV-100',
            'meter_number' => 'MT-MV-100',
            'status' => 'Active',
            'membership_type' => Consumer::MEMBERSHIP_REQUEST_NON_MEMBER,
        ]);

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => 'Verified Member Consumer',
                'email' => 'regular.consumer@example.com',
                'status' => 'Active',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
                'membership_verification_notes' => 'Verified in the association registry.',
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_VERIFIED, $consumer->membership_verification_status);
        $this->assertSame($admin->id, $consumer->membership_reviewed_by);
        $this->assertNotNull($consumer->membership_reviewed_at);
        $this->assertSame('Verified in the association registry.', $consumer->membership_verification_notes);
    }

    public function test_rejecting_an_application_records_the_membership_claim_as_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant();

        $this->actingAs($admin)
            ->patch(route('consumers.reject', $consumer), [
                'rejection_reason' => 'The submitted registration document could not be verified.',
                'correction_fields' => ['membership_document_photos'],
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame('Rejected', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_NON_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_REJECTED, $consumer->membership_verification_status);
        $this->assertSame($admin->id, $consumer->membership_reviewed_by);
        $this->assertSame(
            'The submitted registration document could not be verified.',
            $consumer->membership_verification_notes,
        );

        $connectionRequest = $consumer->serviceRequests()
            ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
            ->firstOrFail();

        $this->assertSame(ServiceRequest::STATUS_REJECTED, $connectionRequest->status);
        $this->assertSame(
            'The submitted registration document could not be verified.',
            $connectionRequest->admin_notes,
        );
    }

    public function test_rejecting_an_application_preserves_an_already_verified_membership(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant([
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_VERIFIED,
            'membership_reviewed_at' => now()->subDay(),
            'membership_reviewed_by' => $admin->id,
            'membership_verification_notes' => 'Verified before the connection review.',
        ]);

        $this->actingAs($admin)
            ->patch(route('consumers.reject', $consumer), [
                'rejection_reason' => 'The requested connection point is not serviceable.',
                'correction_fields' => ['address_street'],
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame('Rejected', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_VERIFIED, $consumer->membership_verification_status);
        $this->assertSame('Verified before the connection review.', $consumer->membership_verification_notes);
        $this->assertSame(
            ServiceRequest::STATUS_REJECTED,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_admin_edit_cannot_activate_a_pending_application_without_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant();

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => $consumer->full_name,
                'email' => $consumer->email,
                'status' => 'Active',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasErrors('status');

        $consumer->refresh();

        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
        $this->assertNull($consumer->user_id);
        $this->assertNull($consumer->account_number);
        $this->assertSame(
            ServiceRequest::STATUS_PENDING,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_new_connections_are_reviewed_in_consumers_and_direct_status_edits_remain_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->memberApplicant([
            'application_reference' => 'APP-BILLING-001',
            'full_name' => 'Billing Member Applicant',
        ]);
        $connectionRequest = app(ConnectionRequestService::class)
            ->createForApplication($consumer);

        $this->actingAs($admin)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertDontSee('Connection Requests')
            ->assertDontSee('APP-BILLING-001');

        $this->actingAs($admin)
            ->get(route('consumers.index', ['search' => 'APP-BILLING-001']))
            ->assertOk()
            ->assertSee('Billing Member Applicant')
            ->assertSee('APP-BILLING-001');

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $connectionRequest), [
                'status' => ServiceRequest::STATUS_APPROVED,
                'admin_notes' => 'Trying to bypass Consumers.',
            ])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasErrors('status');

        $connectionRequest->refresh();
        $consumer->refresh();

        $this->assertSame(ServiceRequest::STATUS_PENDING, $connectionRequest->status);
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
    }

    public function test_admin_dashboard_counts_pending_member_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->memberApplicant(['full_name' => 'Dashboard Member Applicant']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 pending verification')
            ->assertSee('Review 1 pending membership verification');
    }

    public function test_pending_member_application_cannot_enter_meter_readings_early(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $consumer = $this->memberApplicant([
            'account_number' => 'MV-PENDING-001',
            'meter_number' => 'MT-PENDING-001',
        ]);

        $this->actingAs($staff)
            ->postJson(route('meter-readings.lookup-by-meter'), [
                'meter_number' => 'MT-PENDING-001',
            ])
            ->assertStatus(422)
            ->assertJson([
                'found' => false,
                'verified' => false,
            ]);

        $this->assertFalse($consumer->fresh()->isVerified());
    }

    public function test_pending_member_application_is_not_loan_eligible_early(): void
    {
        $consumer = $this->memberApplicant();

        $eligibility = app(DssEligibilityService::class)->loanEligibility($consumer);

        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('Not Eligible', $eligibility['status']);
        $this->assertFalse($eligibility['metrics']['is_verified']);
    }

    public function test_pending_member_claim_does_not_affect_dss_eligibility(): void
    {
        $consumer = Consumer::create([
            'full_name' => 'Active Pending Claimant',
            'email' => 'active.pending.claimant@example.com',
            'account_number' => 'MV-ACTIVE-PENDING',
            'meter_number' => 'MT-ACTIVE-PENDING',
            'status' => 'Active',
            'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'requested_membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_PENDING,
            'ownership_status' => 'Owner',
        ]);

        $this->assertTrue($consumer->isVerified());
        $this->assertFalse($consumer->hasVerifiedMembership());
        $this->assertFalse($consumer->isVerifiedViohoaMember());

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertSee('Active Pending Claimant')
            ->assertSee('Verification pending')
            ->assertSee('Review Membership');

        $this->actingAs($admin)
            ->postJson(route('meter-readings.lookup-by-meter'), [
                'meter_number' => 'MT-ACTIVE-PENDING',
            ])
            ->assertOk()
            ->assertJsonPath(
                'consumer.unit_rate',
                number_format(BillingComputationService::NON_MEMBER_UNIT_RATE, 2, '.', ''),
            );

        $eligibility = app(DssEligibilityService::class)->loanEligibility($consumer);

        $this->assertTrue($eligibility['eligible']);
        $this->assertSame('Eligible', $eligibility['status']);
    }

    public function test_admin_edit_can_verify_an_active_member_claim_with_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::create([
            'full_name' => 'Active Membership Claimant',
            'email' => 'active.verify@example.com',
            'account_number' => 'MV-ACTIVE-VERIFY',
            'meter_number' => 'MT-ACTIVE-VERIFY',
            'status' => 'Active',
            'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'requested_membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            'membership_verification_status' => Consumer::MEMBERSHIP_VERIFICATION_PENDING,
            'ownership_status' => 'Owner',
        ]);

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => $consumer->full_name,
                'email' => $consumer->email,
                'status' => 'Active',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
            ])
            ->assertSessionHasErrors('membership_verification_confirmed');

        $consumer->refresh();
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);

        $this->actingAs($admin)
            ->patch(route('consumers.update', $consumer), [
                'full_name' => $consumer->full_name,
                'email' => $consumer->email,
                'account_number' => $consumer->account_number,
                'meter_number' => $consumer->meter_number,
                'status' => 'Active',
                'membership_type' => Consumer::MEMBERSHIP_REQUEST_MEMBER,
                'membership_verification_confirmed' => '1',
                'membership_verification_notes' => 'Existing membership confirmed in the association registry.',
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame(Consumer::MEMBERSHIP_REQUEST_MEMBER, $consumer->membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_VERIFIED, $consumer->membership_verification_status);
        $this->assertSame($admin->id, $consumer->membership_reviewed_by);
        $this->assertSame(
            'Existing membership confirmed in the association registry.',
            $consumer->membership_verification_notes,
        );
        $this->assertTrue($consumer->hasVerifiedMembership());
        $this->assertNotNull($consumer->membership_started_at);
    }
}
