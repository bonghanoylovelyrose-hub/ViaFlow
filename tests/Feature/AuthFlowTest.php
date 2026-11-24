<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_the_guest_entry_point_and_has_no_role_selection(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('Password')
            ->assertSee('Remember me')
            ->assertSee('Forgot Password?')
            ->assertSee('Apply Now!')
            ->assertSee('Request a secure password reset link')
            ->assertDontSee('Select User')
            ->assertDontSee('Change role');
        $this->get('/auth/role')->assertNotFound();
    }

    public function test_admin_role_is_identified_automatically_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => 'password123',
        ]);

        $loginResponse = $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('dashboard'));
    }

    public function test_meter_reader_role_is_identified_automatically_after_login(): void
    {
        $meterReader = User::factory()->create([
            'email' => 'reader@example.com',
            'role' => 'meter_reader',
            'password' => 'password123',
        ]);

        $loginResponse = $this->post('/login', [
            'login' => $meterReader->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('dashboard'));
    }

    public function test_consumer_role_is_identified_automatically_after_login(): void
    {
        $consumer = User::factory()->create([
            'email' => 'consumer@example.com',
            'username' => 'MT-1001',
            'role' => 'consumer',
            'password' => 'password123',
        ]);

        \App\Models\Consumer::create([
            'user_id' => $consumer->id,
            'full_name' => 'Consumer User',
            'account_number' => '1001',
            'meter_number' => 'MT-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $loginResponse = $this->post('/login', [
            'login' => 'MT-1001',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(route('dashboard'));
    }

    public function test_consumer_uses_meter_number_as_login_identifier(): void
    {
        $consumer = User::factory()->create([
            'email' => 'notification-only@example.com',
            'username' => 'MT-1002',
            'role' => 'consumer',
            'password' => 'password123',
        ]);

        \App\Models\Consumer::create([
            'user_id' => $consumer->id,
            'full_name' => 'Username Login Consumer',
            'email' => $consumer->email,
            'account_number' => '1002',
            'meter_number' => 'MT-1002',
            'status' => 'Active',
            'membership_type' => 'Member',
            'ownership_status' => 'Owner',
        ]);

        $this->post('/login', [
            'login' => 'MT-1002',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($consumer);
    }

    public function test_consumer_cannot_log_in_with_their_email_address(): void
    {
        $consumer = User::factory()->create([
            'email' => 'email-login-blocked@example.com',
            'username' => 'MT-1003',
            'role' => 'consumer',
            'password' => 'password123',
        ]);

        \App\Models\Consumer::create([
            'user_id' => $consumer->id,
            'full_name' => 'Email Login Blocked',
            'email' => $consumer->email,
            'account_number' => '1003',
            'meter_number' => 'MT-1003',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $this->post('/login', [
            'login' => $consumer->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_pending_consumer_account_cannot_log_in_until_activated(): void
    {
        $consumer = User::factory()->create([
            'email' => 'pending@example.com',
            'username' => 'MT-PENDING-1',
            'role' => 'consumer',
            'password' => 'password123',
        ]);

        \App\Models\Consumer::create([
            'user_id' => $consumer->id,
            'full_name' => 'Pending Consumer',
            'account_number' => null,
            'meter_number' => 'MT-PENDING-1',
            'status' => 'Pending Verification',
            'membership_type' => 'Non-Member',
        ]);

        $response = $this->post('/login', [
            'login' => 'MT-PENDING-1',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('login');
        $response->assertSessionHasErrors([
            'login' => 'Your application is still pending admin verification. You can log in after your account is approved, activated, and assigned a unique meter number.',
        ]);
        $this->assertGuest();
    }

    public function test_rejected_consumer_account_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'rejected@example.com',
            'username' => 'MT-REJECTED-1',
            'role' => 'consumer',
            'password' => 'password123',
        ]);

        \App\Models\Consumer::create([
            'user_id' => $user->id,
            'full_name' => 'Rejected Consumer',
            'account_number' => 'RJ-1',
            'meter_number' => 'MT-REJECTED-1',
            'status' => 'Rejected',
            'membership_type' => 'Non-Member',
        ]);

        $this->post('/login', [
            'login' => 'MT-REJECTED-1',
            'password' => 'password123',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_invalid_credentials_show_a_clear_error(): void
    {
        $this->post('/login', [
            'login' => 'missing-user',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'login' => 'Invalid meter number/email/username or password.',
        ]);
    }

    public function test_apply_now_registration_does_not_require_a_selected_role(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Are you a VHOA member?')
            ->assertSee('name="is_viohoa_member"', false)
            ->assertDontSee('Choose Membership')
            ->assertSee('Back')
            ->assertSee("window.location.href = '".route('login')."'", false)
            ->assertDontSee('window.history.back()', false);
    }

    public function test_membership_question_requires_a_yes_or_no_answer(): void
    {
        $this->post(route('register.type.store'), [])
            ->assertSessionHasErrors('is_viohoa_member');

        $this->post(route('register.type.store'), ['is_viohoa_member' => 'maybe'])
            ->assertSessionHasErrors('is_viohoa_member');
    }

    public function test_answering_no_goes_straight_to_the_non_member_application(): void
    {
        $this->post(route('register.type.store'), ['is_viohoa_member' => 'no'])
            ->assertRedirect(route('register.new-connection'));

        $this->assertSame('Non-Member', session('register_connection_flow.membership_type'));
    }

    public function test_answering_yes_routes_through_member_details(): void
    {
        $this->post(route('register.type.store'), ['is_viohoa_member' => 'yes'])
            ->assertRedirect(route('register.member-details'));

        $this->assertSame('Member', session('register_connection_flow.membership_type'));
        $this->assertTrue(session('register_connection_flow.declared_viohoa_member'));
    }

    public function test_answering_yes_classifies_the_applicant_as_a_member_pending_verification(): void
    {
        $this->withSession([
            'register_connection_flow' => [
                'is_viohoa_member' => 'yes',
                'declared_viohoa_member' => true,
                'membership_type' => 'Member',
            ],
            'register_member_details' => [
                'agreement_accepted' => true,
            ],
        ])->post(route('register.store'), [
            'first_name' => 'Declared',
            'last_name' => 'Member',
            'email' => 'declared.member@gmail.com',
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
        ])->assertRedirect(route('login'));

        $consumer = Consumer::query()->where('email', 'declared.member@gmail.com')->firstOrFail();

        // The answer classifies the applicant as a Member, but the separate
        // verification status withholds benefits until an administrator acts.
        $this->assertSame('Member', $consumer->membership_type);
        $this->assertSame('Member', $consumer->requested_membership_type);
        $this->assertSame(Consumer::MEMBERSHIP_VERIFICATION_PENDING, $consumer->membership_verification_status);
        $this->assertSame('Pending Verification', $consumer->status);
        $this->assertNull($consumer->membership_started_at);
        $this->assertTrue($consumer->association_member);
        $this->assertFalse($consumer->association_registered);
        $this->assertFalse($consumer->isVerified());
        $this->assertFalse($consumer->isVerifiedViohoaMember());

        $this->assertSame(
            ServiceRequest::STATUS_PENDING,
            $consumer->serviceRequests()->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)->value('status'),
        );
    }

    public function test_connection_application_uses_fixed_via_vida_address_fields_and_purok_dropdown(): void
    {
        $this->withSession([
            'register_connection_flow' => ['membership_type' => 'Non-Member'],
        ])->get('/register/new-connection')
            ->assertOk()
            ->assertSee('Country')
            ->assertSee('Philippines')
            ->assertSee('City')
            ->assertSee('Panabo City')
            ->assertSee('Barangay/Subdivision')
            ->assertSee('Via Vida')
            ->assertSee('Purok')
            ->assertSee('Select your Purok')
            ->assertSee('Purok 1-A')
            ->assertSee('Purok 2-D')
            ->assertSee('name="service_country"', false)
            ->assertSee('readonly', false)
            ->assertSee('name="service_purok"', false)
            ->assertSee('name="address_block"', false)
            ->assertSee('name="address_street"', false)
            ->assertSee('name="contact_number"', false)
            ->assertSee('pattern="[0-9]{10,15}"', false)
            ->assertDontSee('name="service_address"', false)
            ->assertDontSee('Application Not Eligible')
            ->assertDontSee('validateServiceAddress', false)
            ->assertDontSee('<option value="Other"', false);
    }

    public function test_member_details_documents_require_one_radio_selection_and_photo_uploads(): void
    {
        $response = $this->withSession([
            'register_connection_flow' => ['is_viohoa_member' => 'yes', 'declared_viohoa_member' => true, 'membership_type' => 'Member'],
        ])->get(route('register.member-details'));

        $response
            ->assertOk()
            ->assertSee('4. Documents Submitted')
            ->assertSee('Required')
            ->assertSee('name="membership_document_type"', false)
            ->assertSee('type="radio"', false)
            ->assertSee('Housing Loan Contract (Pag-IBIG)')
            ->assertSee('Proof of Residency')
            ->assertSee('name="membership_document_photos[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('Please select one document type.', false)
            ->assertSee('Please attach at least one photo of the selected document.', false)
            ->assertSee('Please upload a valid JPG, JPEG, or PNG image.', false)
            ->assertSee('Replace')
            ->assertSee('Remove')
            ->assertDontSee('id="valid_id_submitted"', false)
            ->assertDontSee('name="valid_id_submitted"', false)
            ->assertDontSee('>Valid ID</label>', false);
    }

    public function test_member_details_backend_requires_document_type_and_photos(): void
    {
        $response = $this->withSession([
            'register_connection_flow' => ['is_viohoa_member' => 'yes', 'declared_viohoa_member' => true, 'membership_type' => 'Member'],
        ])->post(route('register.member-details.store'), [
            'housing_rights_documented' => 'yes',
            'resident_of_community' => 'yes',
            'association_member' => 'yes',
            'association_registered' => 'yes',
            'agreement_accepted' => '1',
        ]);

        $response->assertSessionHasErrors([
            'membership_document_type' => 'Please select one document type.',
            'membership_document_photos' => 'Please attach at least one photo of the selected document.',
        ]);
    }

    public function test_member_application_saves_selected_document_type_and_all_photos_for_admin_review(): void
    {
        Storage::fake('local');

        $this->withSession([
            'register_connection_flow' => ['is_viohoa_member' => 'yes', 'declared_viohoa_member' => true, 'membership_type' => 'Member'],
        ])->post(route('register.member-details.store'), [
            'housing_rights_documented' => 'yes',
            'resident_of_community' => 'yes',
            'association_member' => 'yes',
            'association_registered' => 'yes',
            'membership_document_type' => 'Proof of Residency',
            'membership_document_photos' => [
                UploadedFile::fake()->createWithContent('residency-page-1.jpg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ap//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQMBAT8QH//EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQIBAT8QH//EFBABAQAAAAAAAAAAAAAAAAAAARD/2gAIAQEAAT8QH//Z')),
                UploadedFile::fake()->createWithContent('residency-page-2.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
            ],
            'other_document_details' => 'Optional barangay note',
            'agreement_accepted' => '1',
        ])->assertRedirect(route('register.new-connection'));

        $this->post(route('register.store'), [
            'first_name' => 'Member',
            'last_name' => 'Applicant',
            'email' => 'member.document.applicant@gmail.com',
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
        ])->assertRedirect(route('login'));

        $consumer = Consumer::query()
            ->where('email', 'member.document.applicant@gmail.com')
            ->firstOrFail();

        $this->assertSame('Proof of Residency', $consumer->membership_document_type);
        $this->assertFalse($consumer->valid_id_submitted);
        $this->assertFalse($consumer->housing_loan_contract_submitted);
        $this->assertTrue($consumer->proof_of_residency_submitted);
        $this->assertCount(2, $consumer->membership_document_paths);

        foreach ($consumer->membership_document_paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertSee('membership_document_type', false)
            ->assertSee('membership_document_urls', false)
            ->assertSee('Membership Document Photos')
            ->assertSee('Selected document type:');
    }

    public function test_unsupported_purok_is_rejected_before_application_is_created(): void
    {
        $response = $this->withSession([
            'register_connection_flow' => ['membership_type' => 'Non-Member'],
        ])->post('/register', [
            'first_name' => 'Outside',
            'last_name' => 'Applicant',
            'email' => 'outside.applicant@gmail.com',
            'service_country' => 'Philippines',
            'service_city' => 'Panabo City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 9-Z',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png', 1, 1),
            'acknowledgment_accepted' => '1',
        ]);

        $response->assertSessionHasErrors([
            'service_purok',
        ]);
        $this->assertDatabaseCount('consumers', 0);
    }

    public function test_tampered_fixed_service_area_fields_are_rejected(): void
    {
        $response = $this->withSession([
            'register_connection_flow' => ['membership_type' => 'Non-Member'],
        ])->post('/register', [
            'first_name' => 'Tampered',
            'last_name' => 'Applicant',
            'email' => 'tampered.applicant@gmail.com',
            'service_country' => 'Philippines',
            'service_city' => 'Quezon City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 1-A',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png', 1, 1),
            'acknowledgment_accepted' => '1',
        ]);

        $response->assertSessionHasErrors(['service_city']);
        $this->assertDatabaseCount('consumers', 0);
    }

    public function test_same_email_can_submit_multiple_distinct_applications(): void
    {
        $application = [
            'first_name' => 'Repeat',
            'last_name' => 'Applicant',
            'email' => 'repeat.applicant@gmail.com',
            'service_country' => 'Philippines',
            'service_city' => 'Panabo City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 1-A',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png', 1, 1),
            'acknowledgment_accepted' => '1',
        ];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->withSession([
                'register_connection_flow' => ['is_viohoa_member' => 'no', 'membership_type' => 'Non-Member'],
            ])->post('/register', $application)
                ->assertRedirect(route('login'))
                ->assertSessionHas('application_reference');
        }

        $applications = \App\Models\Consumer::query()
            ->where('email', 'repeat.applicant@gmail.com')
            ->get();

        $this->assertCount(2, $applications);
        $this->assertSame(2, $applications->pluck('application_reference')->unique()->count());
        $this->assertTrue($applications->every(fn ($application) => filled($application->application_reference)));
        $this->assertTrue($applications->every(
            fn ($application) => $application->service_address === 'Block 5, Vista Street, Purok 1-A, Via Vida, Panabo City, Philippines'
        ));
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_away_from_auth_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/register')->assertRedirect(route('dashboard'));
    }

public function test_contact_number_accepts_only_numeric_phone_numbers(): void
    {
        foreach (['0917123456a', '0917 123 456', '+63-917-123-4567', 'abcdefghij'] as $index => $invalid) {
            $response = $this->withSession([
                'register_connection_flow' => ['is_viohoa_member' => 'no', 'membership_type' => 'Non-Member'],
            ])->post('/register', [
                'first_name' => 'Phone',
                'last_name' => 'Check',
                'email' => 'phone.check.'.$index.'@gmail.com',
                'service_country' => 'Philippines',
                'service_city' => 'Panabo City',
                'service_barangay_subdivision' => 'Via Vida',
                'service_purok' => 'Purok 1-A',
                'address_block' => 'Block 5',
                'address_street' => 'Vista Street',
                'contact_number' => $invalid,
                'property_type' => 'Residential',
                'ownership_status' => 'Owner',
                'valid_id_presented' => 'National ID',
                'acknowledgment_accepted' => '1',
            ]);

            $response->assertSessionHasErrors('contact_number');
        }

        $this->assertDatabaseCount('consumers', 0);

        $this->withSession([
            'register_connection_flow' => ['is_viohoa_member' => 'no', 'membership_type' => 'Non-Member'],
        ])->post('/register', [
            'first_name' => 'Phone',
            'last_name' => 'Valid',
            'email' => 'phone.valid@gmail.com',
            'service_country' => 'Philippines',
            'service_city' => 'Panabo City',
            'service_barangay_subdivision' => 'Via Vida',
            'service_purok' => 'Purok 1-A',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
            'contact_number' => '09171234567',
            'property_type' => 'Residential',
            'ownership_status' => 'Owner',
            'valid_id_presented' => 'National ID',
            'valid_id_file' => UploadedFile::fake()->image('valid-id.png', 1, 1),
            'acknowledgment_accepted' => '1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('consumers', [
            'email' => 'phone.valid@gmail.com',
            'contact_number' => '09171234567',
            'address_block' => 'Block 5',
            'address_street' => 'Vista Street',
        ]);
    }
}
