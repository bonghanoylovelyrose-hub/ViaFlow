<?php

namespace Tests\Feature;

use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\User;
use App\Services\LoanMaterialRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPaymentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Breakdown Applicant', 'account_number' => 'B-'.$user->id, 'meter_number' => 'BM-'.$user->id, 'status' => 'Active', 'membership_type' => 'Member']);
        return [$user, $consumer];
    }

    public function test_saved_approval_date_drives_months_for_all_terms_and_preserves_recorded_due_dates(): void
    {
        [$user, $consumer] = $this->applicant();
        $calculator = app(LoanMaterialRateService::class);
        foreach ([3 => 'February 2027', 6 => 'May 2027', 12 => 'November 2027'] as $term => $lastMonth) {
            $schedule = $calculator->schedule(439, $term);
            $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 439, 'installation_cost_total' => 439, 'material_meter_cost' => 200, 'material_hose_cost' => 139, 'material_other_cost' => 100, 'term_months' => $term, 'monthly_installment' => $schedule[0]['amount'], 'installment_schedule_snapshot' => $schedule, 'purpose' => 'Breakdown test', 'status' => 'Approved', 'applied_at' => '2026-10-11', 'approved_at' => '2026-11-16']);
            LoanInstallment::create(['loan_id' => $loan->id, 'installment_number' => 1, 'billing_period' => '2026-12-01', 'due_date' => '2026-12-15', 'required_amount' => $schedule[0]['amount'], 'amount_paid' => 20, 'remaining_balance' => $schedule[0]['amount'] - 20, 'status' => 'Partially Paid']);
            $response = $this->actingAs($user)->get(route('loans.payment-breakdown', $loan))->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $response->assertSee('data-breakdown-loan-id="'.$loan->id.'"', false)->assertSee('December 2026')->assertSee($lastMonth)->assertSee('Dec 15, 2026')->assertSee('To Be Scheduled')->assertSee('₱439.00')
                ->assertDontSee('data-label="Amount Paid"', false)->assertDontSee('data-label="Remaining Amount"', false)->assertDontSee('Payment Status');
            $response->assertViewHas('breakdown', function ($data) use ($term, $lastMonth) {
                return count($data['rows']) === $term && $data['schedule_state'] === 'available' && $data['has_plan'] && ! $data['period_mismatch']
                    && $data['rows'][0]['month'] === 'December 2026' && $data['rows'][$term - 1]['month'] === $lastMonth
                    && $data['total_due'] === 439.0 && $data['rows'][0]['due_date']->format('Y-m-d') === '2026-12-15';
            });
            if ($term === 6) $response->assertSee('₱73.17')->assertSee('₱73.15');
        }
    }

    public function test_pending_rejected_and_missing_approval_never_get_invented_months(): void
    {
        [$user, $consumer] = $this->applicant();
        $other = User::factory()->create(['role' => 'consumer']);
        $plan = app(LoanMaterialRateService::class)->schedule(439, 3);
        $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 439, 'term_months' => 3, 'monthly_installment' => $plan[0]['amount'], 'installment_schedule_snapshot' => $plan, 'purpose' => 'Review test', 'status' => 'Needs Review', 'applied_at' => '2026-11-11']);
        $this->actingAs($other)->get(route('loans.payment-breakdown', $loan))->assertForbidden();
        $this->actingAs($user)->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('Installment schedule available after approval.')->assertDontSee('December 2026')
            ->assertViewHas('breakdown', fn ($data) => $data['schedule_state'] === 'pending' && $data['rows'] === []);
        $loan->update(['status' => 'Rejected', 'approved_at' => '2026-11-16']);
        $this->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('No installment schedule is available for a rejected application.')->assertDontSee('December 2026');
        $loan->update(['status' => 'Approved', 'approved_at' => null]);
        $this->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('The approval date is not recorded')->assertDontSee('December 2026');
        $loan->update(['approved_at' => '2026-12-31']);
        $this->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('January 2027')->assertSee('March 2027')->assertDontSee('December 2026');
        $loan->update(['approved_at' => '2028-01-31']);
        $this->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('February 2028')->assertSee('April 2028');
    }

    public function test_missing_or_conflicting_saved_amounts_are_not_invented(): void
    {
        [$owner, $consumer] = $this->applicant();
        $loan = Loan::create(['consumer_id' => $consumer->id, 'amount' => 1000, 'term_months' => 3, 'monthly_installment' => 333.33, 'installment_schedule_snapshot' => [['month' => 1, 'amount' => 20]], 'purpose' => 'Legacy loan', 'status' => 'Approved', 'applied_at' => '2026-10-11', 'approved_at' => '2026-11-16']);
        $this->actingAs($owner)->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('A complete saved schedule is not available')->assertSee('Not recorded')
            ->assertViewHas('breakdown', fn ($data) => ! $data['has_plan'] && count($data['rows']) === 3 && $data['rows'][0]['amount_due'] === null && $data['total_due'] === null);
        $schedule = app(LoanMaterialRateService::class)->schedule(1000, 3);
        $loan->update(['installment_schedule_snapshot' => $schedule]);
        LoanInstallment::create(['loan_id' => $loan->id, 'installment_number' => 1, 'billing_period' => '2027-01-01', 'due_date' => '2027-01-15', 'required_amount' => 335, 'amount_paid' => 20, 'remaining_balance' => 315, 'status' => 'Partially Paid']);
        $this->get(route('loans.payment-breakdown', $loan))->assertOk()->assertSee('recorded installment amounts differ')->assertSee('recorded billing periods differ')->assertSee('Jan 15, 2027')
            ->assertViewHas('breakdown', fn ($data) => $data['has_plan'] && $data['record_mismatch'] && $data['period_mismatch'] && (float) $data['rows'][0]['amount_due'] === 335.0 && $data['total_due'] === 1001.67);
        $this->get(route('loans.index'))->assertOk()
            ->assertSee('View Details')
            ->assertSee('data-breakdown-url="'.route('loans.payment-breakdown', $loan).'"', false);
    }
}
