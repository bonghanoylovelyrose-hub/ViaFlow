<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanMaterialRate;
use App\Models\User;
use App\Services\LoanIncomeOcrService;
use App\Support\LoanTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanTermsSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_agreement_has_the_exact_requested_text(): void
    {
        $expected = <<<'TEXT'
Loan Meter Application Terms and Conditions

I am applying for a loan meter and confirm that I am a registered VHOA member living in Via Vida Subdivision with no overdue water bills or unpaid loans. I understand that the Decision Support System (DSS) checks my eligibility and that the VVWD Administrator makes the final decision on my application.

If approved, I agree to pay for the water meter in interest-free monthly installments over my selected repayment term of 3, 6, or 12 months. I agree to pay each installment on or before its scheduled due date.

I understand that if I fail to pay my loan meter balance in full by the end of my selected repayment term of 3, 6, or 12 months, I will be ineligible to apply for another loan meter.

I understand that if I fail to fully pay my outstanding water meter loan balance by the end of my selected repayment term of 3, 6, or 12 months, I will receive a Disconnection Notice. I will be given three (3) days from the issuance of the notice to settle my outstanding balance. Failure to pay within this period may result in the disconnection of my water service, subject to confirmation by the VVWD Administrator.

The system issues this notice automatically. If the outstanding balance remains unpaid after the three-day grace period, the account will be marked Ready for Disconnection, subject to confirmation by the VVWD Administrator.

I may pay in cash at the VVWD office or pay online and upload clear proof of payment. VVWD will credit my payment and issue a digital receipt after verification.

I understand that the water meter remains the property of VVWD until I pay for it in full, and I agree to keep it at its assigned location and refrain from tampering with it.

I understand that VVWD will use my personal information to process my application and manage my account in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). VVWD will send application and payment updates to my registered email address.
TEXT;
        $this->assertSame($expected, LoanTerms::text(3));
        $this->assertSame($expected, LoanTerms::text(6));
        $this->assertSame($expected, LoanTerms::text(12));

        $user = User::factory()->create(['role' => 'consumer']);
        Consumer::create(['user_id' => $user->id, 'full_name' => 'Terms Reader', 'account_number' => 'TERMS-2', 'meter_number' => 'MT-TERMS-2', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        LoanMaterialRate::create(['water_meter_cost' => 250, 'hose_cost' => 150, 'other_materials_cost' => 100, 'is_active' => true]);
        $page = $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee('Loan Meter Application Terms and Conditions')
            ->assertSee('I have read and agree to the Loan Meter Application Terms and Conditions.')
            ->assertDontSee('data-loan-term-clause', false);
        foreach (LoanTerms::paragraphs() as $paragraph) {
            $page->assertSee($paragraph);
        }
        $page->assertSeeInOrder([
            '<strong>'.e(LoanTerms::paragraphs()[2]).'</strong>',
            '<strong>'.e(LoanTerms::paragraphs()[3]).'</strong>',
            'Ready for Disconnection',
        ], false);
    }

    public function test_consent_and_one_time_confirmation_are_required_and_saved(): void
    {
        Storage::fake('local');
        $this->app->instance(LoanIncomeOcrService::class, new class extends LoanIncomeOcrService {
            public function extract(UploadedFile $file): array { return []; }
        });
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Agreement Test', 'account_number' => 'AGREE-1', 'meter_number' => 'MT-AGREE', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        LoanMaterialRate::create(['water_meter_cost' => 250, 'hose_cost' => 150, 'other_materials_cost' => 100, 'is_active' => true]);
        $this->actingAs($user)->get(route('loans.index'))->assertOk()->assertSee('Loan Meter Application Terms and Conditions')->assertSee('loan_terms_accepted', false)->assertSee('Are you sure you want to submit your loan application?');
        $this->postJson(route('loans.confirm-submission'), ['term_months' => 3, 'confirmed' => 1])->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');
        $this->postJson(route('loans.confirm-submission'), ['term_months' => 3, 'terms_accepted' => 1])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->post(route('loans.store'), ['term_months' => 3, 'purpose' => 'Connection', 'proof_of_income' => UploadedFile::fake()->image('proof.png')])->assertSessionHasErrors(['terms_accepted', 'confirmation_token']);
        $this->assertDatabaseCount('loans', 0);

        $mismatch = $this->postJson(route('loans.confirm-submission'), ['term_months' => 3, 'terms_accepted' => 1, 'confirmed' => 1])->assertOk()->json('confirmation_token');
        $this->post(route('loans.store'), ['term_months' => 6, 'purpose' => 'Connection', 'proof_of_income' => UploadedFile::fake()->image('proof.png'), 'terms_accepted' => 1, 'confirmation_token' => $mismatch])->assertSessionHasErrors('confirmation_token');
        $this->assertDatabaseCount('loans', 0);
        $token = $this->postJson(route('loans.confirm-submission'), ['term_months' => 3, 'terms_accepted' => 1, 'confirmed' => 1])->assertOk()->json('confirmation_token');
        $this->post(route('loans.store'), ['term_months' => 3, 'purpose' => 'Connection', 'proof_of_income' => UploadedFile::fake()->image('proof.png'), 'confirmation_token' => $token])->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseCount('loans', 0);
        $data = ['term_months' => 3, 'purpose' => 'Connection', 'proof_of_income' => UploadedFile::fake()->image('proof.png'), 'terms_accepted' => 1, 'confirmation_token' => $token];
        $this->post(route('loans.store'), $data)->assertSessionHasNoErrors();
        $loan = Loan::where('consumer_id', $consumer->id)->firstOrFail();
        $this->assertNotNull($loan->terms_agreed_at);
        $this->assertNotNull($loan->submission_confirmed_at);
        $this->assertSame(LoanTerms::VERSION, $loan->terms_version);
        $this->assertSame(LoanTerms::text(3), $loan->terms_text);
        $this->assertSame(3, (int) $loan->term_months);
        $this->assertSame('loan-terms-v3', $loan->terms_version);
        $this->assertSame('Needs Review', $loan->status);
        $this->post(route('loans.store'), array_merge($data, ['proof_of_income' => UploadedFile::fake()->image('again.png')]))->assertSessionHasErrors('confirmation_token');
        $secondToken = $this->postJson(route('loans.confirm-submission'), ['term_months' => 3, 'terms_accepted' => 1, 'confirmed' => 1])->assertOk()->json('confirmation_token');
        $this->post(route('loans.store'), array_merge($data, ['confirmation_token' => $secondToken, 'proof_of_income' => UploadedFile::fake()->image('again.png')]))->assertSessionHasErrors('loan');
        $this->assertDatabaseCount('loans', 1);
    }
}

