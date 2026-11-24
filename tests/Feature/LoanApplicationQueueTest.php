<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\LoanMaterialRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApplicationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_categories_filter_internal_statuses_without_changing_records_or_crossing_accounts(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Queue Applicant', 'account_number' => 'Q-100', 'meter_number' => 'QM-100', 'status' => 'Active', 'membership_type' => 'Member', 'membership_verification_status' => 'Verified', 'membership_started_at' => now()->subYear()]);
        $other = User::factory()->create(['role' => 'consumer']);
        $otherConsumer = Consumer::create(['user_id' => $other->id, 'full_name' => 'Other Applicant', 'account_number' => 'Q-200', 'meter_number' => 'QM-200', 'status' => 'Active', 'membership_type' => 'Member']);
        LoanMaterialRate::create(['water_meter_cost' => 250, 'hose_cost' => 150, 'other_materials_cost' => 100, 'is_active' => true]);
        $statuses = ['Pending Review', 'Needs Review', 'Ready for Scheduling', 'Approved', 'Completed', 'Active', 'Ongoing', 'Rejected'];
        foreach ($statuses as $index => $status) {
            Loan::create(['consumer_id' => $consumer->id, 'amount' => 500, 'installation_cost_total' => 500, 'term_months' => 6, 'monthly_installment' => 83.33, 'purpose' => 'Queue '.$status, 'status' => $status, 'risk_level' => 'Medium Risk', 'applied_at' => now()->subMinutes($index)]);
        }
        Loan::create(['consumer_id' => $otherConsumer->id, 'amount' => 500, 'term_months' => 6, 'monthly_installment' => 83.33, 'purpose' => 'Other account', 'status' => 'Approved', 'applied_at' => now()]);
        $this->actingAs($user);
        $all = $this->get(route('loans.index'))->assertOk()->assertViewHas('loans', fn ($loans) => $loans->total() === 8);
        $all->assertDontSee('Select Status')->assertSee('value="Pending Review"', false)->assertSee('value="Approved"', false)->assertSee('value="Rejected"', false)
            ->assertDontSee('value="Needs Review"', false)->assertDontSee('value="Ready for Scheduling"', false)->assertDontSee('value="Completed"', false)->assertDontSee('Other Applicant');
        foreach ([
            'Pending Review' => ['Pending Review', 'Needs Review'],
            'Approved' => ['Ready for Scheduling', 'Approved', 'Completed', 'Active', 'Ongoing'],
            'Rejected' => ['Rejected'],
        ] as $category => $expected) {
            $this->get(route('loans.index', ['status' => $category]))->assertOk()
                ->assertViewHas('loans', function ($loans) use ($expected, $category) {
                    $actual = $loans->getCollection()->pluck('status')->sort()->values()->all();
                    sort($expected);
                    return $actual === $expected && $loans->getCollection()->every(fn ($loan) => $loan->applicationStatusCategory() === $category);
                });
        }
        $this->assertDatabaseHas('loans', ['consumer_id' => $consumer->id, 'status' => 'Ready for Scheduling']);
        $this->assertDatabaseHas('loans', ['consumer_id' => $consumer->id, 'status' => 'Completed']);
    }

    public function test_proof_correction_does_not_display_an_old_dss_rejection_as_the_current_decision(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Applicant', 'account_number' => 'C-100', 'status' => 'Active', 'membership_type' => 'Member']);
        $loan = Loan::create([
            'consumer_id' => $consumer->id, 'amount' => 500, 'term_months' => 6, 'monthly_installment' => 83.33,
            'purpose' => 'Connection', 'status' => 'Needs Review', 'applied_at' => now(),
            'income_verification_status' => 'Rejected', 'income_rejection_reason' => 'The document is unreadable.',
        ]);
        LoanDssEvaluation::create([
            'loan_id' => $loan->id, 'recommendation' => 'Rejected', 'evaluation_type' => 'initial',
            'criteria_version' => 'old', 'evaluated_values' => [], 'income_evidence' => [],
            'failed_criteria' => [['id' => 'old', 'label' => 'Old decision', 'explanation' => 'Old DSS rejection reason']],
            'missing_information' => [], 'evidence_hash' => hash('sha256', 'old'), 'evaluated_at' => now(),
        ]);
        $response = $this->actingAs($user)->get(route('loans.index'))->assertOk();
        $html = $response->getContent();
        $article = substr($html, strpos($html, '<article class="loan-application-card"'));
        $article = substr($article, 0, strpos($article, '</article>'));
        $this->assertStringContainsString('Proof of Income Needs Correction', $article);
        $this->assertStringNotContainsString('Old DSS rejection reason', $article);
        $this->assertStringNotContainsString('Your loan application was rejected.', $article);
        $this->assertStringNotContainsString('Request review of incorrect information', $article);
        $loan->update(['income_verification_status' => 'Pending Verification']);
        $this->get(route('loans.index'))->assertDontSee('Old DSS rejection reason')->assertSee('Income verification: Pending Verification');
        $this->assertDatabaseHas('loan_dss_evaluations', ['loan_id' => $loan->id, 'recommendation' => 'Rejected']);
    }

    public function test_actual_loan_rejection_is_not_described_as_a_proof_correction(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Applicant', 'account_number' => 'C-200', 'status' => 'Active', 'membership_type' => 'Member']);
        Loan::create(['consumer_id' => $consumer->id, 'amount' => 500, 'term_months' => 6, 'monthly_installment' => 83.33, 'purpose' => 'Connection', 'status' => 'Rejected', 'income_verification_status' => 'Verified', 'applied_at' => now()]);
        $this->actingAs($user)->get(route('loans.index'))->assertSee('Your loan application was rejected.')->assertDontSee('Proof of Income Needs Correction');
    }
}
