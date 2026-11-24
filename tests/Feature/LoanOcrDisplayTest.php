<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanOcrDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_details_hide_ocr_data_and_keep_proof_and_other_review_information(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('example.png')->store('loan-income-proofs', 'local');

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Proof Owner', 'account_number' => 'OCR-TEST-1', 'meter_number' => 'MT-OCR-TEST', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified']);
        $evidence = ['ocr_status' => 'Incomplete', 'ocr_confidence' => 42, 'extracted_name' => 'OCR PRIVATE NAME', 'missing_fields' => ['OCR-only missing field']];
        $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 300, 'term_months' => 3, 'monthly_installment' => 100, 'purpose' => 'Materials', 'status' => 'Needs Review', 'applied_at' => now(), 'proof_of_income_path' => $path, 'income_verification_status' => 'Pending Verification', 'income_ocr_data' => $evidence]);
        $evaluation = LoanDssEvaluation::create(['loan_id' => $loan->id, 'recommendation' => 'Needs Review', 'evaluation_type' => 'initial', 'criteria_version' => 'test', 'evaluated_values' => [], 'income_evidence' => $evidence, 'failed_criteria' => [], 'missing_information' => ['Trusted income-source verification', 'Proof of income: OCR-only missing field', 'OCR applicant name does not match the consumer record'], 'evidence_hash' => str_repeat('a', 64), 'evaluated_at' => now()]);
        $this->actingAs($admin)->get(route('loans.index', ['tab' => 'pending']))->assertOk()
            ->assertDontSee('OCR-extracted information')->assertDontSee('OCR PRIVATE NAME')->assertDontSee('OCR status:')->assertDontSee('OCR items needing review:')
            ->assertSee(route('loans.income-proof', $loan), false)->assertSee('Uploaded Proof of Income for Proof Owner')
            ->assertSee('Income verification:')->assertSee('Pending Verification')
            ->assertSee('name="decision" value="confirm"', false)
            ->assertSee('Confirm Verification')->assertSee('Reject Verification')
            ->assertSee('action="'.route('loans.verify-income', $loan).'"', false)
            ->assertSee('data-reject-income-url="'.route('loans.verify-income', $loan).'"', false);
        $this->get(route('loans.income-proof', $loan))->assertOk()->assertHeader('Content-Type', 'image/png');

        $loan->load('consumer');
        foreach (['admin.loans._loan-detail-template', 'admin.dss._loan-detail-template'] as $template) {
            $html = view($template, ['application' => $loan, 'evaluation' => $evaluation, 'decision' => 'Needs Review', 'manageInstallation' => false])->render();
            $this->assertStringContainsString('Trusted income-source verification', $html);
            $this->assertStringNotContainsString('OCR applicant name', $html);
            $this->assertStringNotContainsString('OCR-only missing field', $html);
            $this->assertStringContainsString('Proof of Income', $html);
            $this->assertStringContainsString('Verification Status:', $html);
            $this->assertStringContainsString(route('loans.income-proof', $loan), $html);
        }

        $this->actingAs($user)->get(route('loans.index'))->assertOk()
            ->assertSee('Pending Verification')
            ->assertDontSee('Trusted income-source verification')
            ->assertDontSee('OCR applicant name')->assertDontSee('OCR-only missing field');

        $loan->update(['income_verification_status' => 'Verified']);
        $this->get(route('loans.index'))->assertOk()
            ->assertSee('Trusted income-source verification')
            ->assertDontSee('OCR applicant name')->assertDontSee('OCR-only missing field');
        $this->assertSame($evidence, $loan->fresh()->income_ocr_data);
        $this->assertSame($evidence, $evaluation->fresh()->income_evidence);
    }
}

