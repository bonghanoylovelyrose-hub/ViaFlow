<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingStatementLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_uses_the_shared_layout_and_shows_zero_previous_balance_for_first_billing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader', 'name' => 'Via Vida Meter Reader']);
        $consumer = $this->createConsumer([
            'full_name' => 'First Time Consumer',
            'account_number' => 'STMT-0001',
            'meter_number' => 'MTR-STMT-0001',
            'service_address' => 'Block 1 Lot 1, Via Vida, Panabo City',
            'address_street' => 'Example Street',
        ]);

        $reading = MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'meter_number' => $consumer->meter_number,
            'reader_id' => $reader->id,
            'reading_date' => '2026-08-08',
            'previous_reading' => 100,
            'current_reading' => 110,
            'consumption' => 10,
            'status' => 'Encoded',
            'remarks' => 'Tested meter reading',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'meter_reading_id' => $reading->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-18',
            'prn' => 'STMT-FIRST-2026-08',
            'amount' => 350.00,
            'base_charge' => 150.00,
            'water_charge' => 350.00,
            'arrears_amount' => 0.00,
            'penalty_amount' => 0.00,
            'loan_installment_amount' => 0.00,
            'units_consumed' => 10.00,
            'unit_rate' => 20.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $bill->forceFill(['created_at' => '2026-08-09 08:00:00'])->save();

        $response = $this->actingAs($admin)->get(route('billing.show', $bill));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Via Vida Townhomes HOA Water System',
            'Purok Via Vida, Brgy. Datu Abdul Dadia',
            'Panabo City, Davao del Norte',
            'Water Bill',
            'Name: <strong>First Time Consumer</strong>',
            'Date: <strong>August 09, 2026</strong>',
            'Blk: <strong>1</strong>',
            'Lot: <strong>1</strong>',
            'Street: <strong>Example Street</strong>',
            'Due Date: <strong>August 18, 2026</strong>',
            'Status: <strong>Non-Member</strong>',
            'Period Covered:',
            'August 2026',
            'Billing Computation',
            'Present Reading',
            '>110</td>',
            'Previous Reading',
            '>-100</td>',
            'Current Water Consumption',
            '>10</td>',
            'Total Current Bill',
            'P 350.00',
            'ADD: Unpaid Balance Before Reading',
            'P 0.00',
            'Monthly Dues',
            'Total Obligation',
            'P 350.00',
            'Remarks: Tested meter reading',
            'With Meter Loan:',
            'Please disregard if payment has been made.',
        ], false);
        $response->assertDontSee('Print Bill');
        $response->assertDontSee('window.print()', false);
        $response->assertSee('Consumer Information');
        $response->assertSee('Detailed Billing Breakdown');
        $response->assertSee('Discounts &amp; Adjustments', false);
        $response->assertSee('Payment Summary');
        $response->assertSee('Meter Reading Details');
        $response->assertSee('Payment Instructions');
        $response->assertSee('Previous Balance');
        $response->assertSee('P 0.00');
        $response->assertSee('P 350.00');
        $response->assertSee('Other Charges / Adjustments');
        $response->assertSee('August 08, 2026');
        $response->assertSee('Via Vida Meter Reader');
        $response->assertSee('STMT-FIRST-2026-08');
        $response->assertSee('Download Bill');
        $response->assertSee('@media print', false);
        $response->assertDontSee('Arrears Carryover');
        $response->assertSee('href="'.route('billing.statement.pdf', ['bill' => $bill]).'" class="btn btn-primary"', false);
    }

    public function test_statement_distinguishes_verified_and_pending_membership(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $verifiedConsumer = $this->createConsumer([
            'full_name' => 'Verified Member',
            'account_number' => 'STMT-MEMBER-001',
            'meter_number' => 'MTR-MEMBER-001',
            'membership_type' => 'Member',
            'requested_membership_type' => 'Member',
            'membership_verification_status' => 'Verified',
        ]);
        $verifiedBill = $this->createBill($verifiedConsumer, 'STMT-MEMBER-2026-08', '2026-08-01', 18.00);

        $verified = $this->actingAs($admin)->get(route('billing.show', $verifiedBill));
        $verified->assertOk();
        $verified->assertSee('Member · Verified', false);
        $verified->assertSee('Member billing rate is applied', false);
        $verified->assertSee('P 18.00');

        $pendingConsumer = $this->createConsumer([
            'full_name' => 'Pending Member Applicant',
            'account_number' => 'STMT-PENDING-001',
            'meter_number' => 'MTR-PENDING-001',
            'membership_type' => 'Non-Member',
            'requested_membership_type' => 'Member',
            'membership_verification_status' => 'Pending',
        ]);
        $pendingBill = $this->createBill($pendingConsumer, 'STMT-PENDING-2026-08', '2026-08-01');

        $pending = $this->actingAs($admin)->get(route('billing.show', $pendingBill));
        $pending->assertOk();
        $pending->assertSee('Member application · Pending verification', false);
        $pending->assertSee('Membership verification is pending', false);
        $pending->assertDontSee('Member billing rate is applied', false);
        $pending->assertSee('P 20.00');
    }

    public function test_paid_statement_keeps_every_section_and_zero_remaining_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer([
            'full_name' => 'Paid Consumer',
            'account_number' => 'STMT-PAID-001',
            'meter_number' => 'MTR-PAID-001',
        ]);
        $bill = $this->createBill($consumer, 'STMT-PAID-2026-08', '2026-08-01');

        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'STMT-PAID-REF-001',
            'amount' => 350.00,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('billing.show', $bill));

        $response->assertOk();
        $response->assertSee('Consumer Information');
        $response->assertSee('Payment Summary');
        $response->assertSee('Meter Reading Details');
        $response->assertSee('Payment Instructions');
        $response->assertSee('Remaining Balance');
        $response->assertSee('P 0.00');
    }

    public function test_carried_forward_legacy_statement_does_not_invent_missing_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer([
            'full_name' => 'Carried Forward Consumer',
            'account_number' => 'STMT-CARRIED-001',
            'meter_number' => 'MTR-CARRIED-001',
        ]);
        $carriedBill = $this->createBill($consumer, 'STMT-CARRIED-2026-08', '2026-08-01');
        $laterBill = $this->createBill($consumer, 'STMT-CARRIED-2026-09', '2026-09-01');

        $carriedBill->forceFill([
            'carried_forward_to_bill_id' => $laterBill->id,
            'status' => 'Paid',
        ])->save();

        $response = $this->actingAs($admin)->get(route('billing.show', $carriedBill));

        $response->assertOk();
        $response->assertSee('Carried forward:', false);
        $response->assertSee('not charged twice', false);
        $response->assertSee('Remaining Balance (This Bill)');
        $response->assertSee('Not available');
    }

    public function test_consumer_can_download_statement_pdf_but_not_another_consumers_statement(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer([
            'user_id' => $consumerUser->id,
            'account_number' => 'STMT-PDF-001',
            'meter_number' => 'MTR-PDF-001',
        ]);
        $bill = $this->createBill($consumer, 'STMT-PDF-2026-08', '2026-08-01');

        $this->actingAs($consumerUser)
            ->get(route('billing.show', $bill))
            ->assertOk()
            ->assertSee('Download Bill')
            ->assertDontSee('Print Bill');

        $this->actingAs($consumerUser)
            ->get(route('billing.statement.pdf', ['bill' => $bill]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('billing-statement-stmt-pdf-2026-08.pdf');

        $otherUser = User::factory()->create(['role' => 'consumer']);
        $this->createConsumer([
            'user_id' => $otherUser->id,
            'account_number' => 'STMT-PDF-002',
            'meter_number' => 'MTR-PDF-002',
        ]);

        $this->actingAs($otherUser)
            ->get(route('billing.statement.pdf', ['bill' => $bill]))
            ->assertForbidden();
    }

    public function test_admin_and_consumer_billing_lists_link_to_the_pdf_and_view_statement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'STMT-BILLING-LIST', '2026-08-01');

        $period = ['billing_period' => '2026-08'];
        $adminPeriod = ['year' => 2026, 'month' => 8];

        $this->actingAs($admin)
            ->get(route('billing.index', $period))
            ->assertOk()
            ->assertSee('View Monthly Billings')
            ->assertSee(route('billing.monthly-history', ['consumer' => $consumer] + $adminPeriod));

        $responses = [
            [$this->get(route('billing.monthly-history', ['consumer' => $consumer] + $adminPeriod)), $adminPeriod],
            [$this->actingAs($consumerUser)->get(route('billing.index', $period)), $period],
        ];

        foreach ($responses as [$response, $statementFilters]) {
            $response
                ->assertOk()
                ->assertSee('href="'.e(route('billing.show', ['bill' => $bill] + $statementFilters)).'"', false)
                ->assertSee('href="'.route('billing.statement.pdf', ['bill' => $bill]).'"', false)
                ->assertSee('Download Bill')
                ->assertDontSee('Print Bill')
                ->assertDontSee('bi-printer');
        }

        $this->actingAs($admin)
            ->get(route('billing.statement.pdf', ['bill' => $bill]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('billing-statement-stmt-billing-list.pdf');
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Statement Consumer',
            'account_number' => 'STMT-'.fake()->unique()->numerify('####'),
            'meter_number' => 'MTR-STMT-'.fake()->unique()->numerify('####'),
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'service_address' => 'Via Vida, Panabo City',
        ], $attributes));
    }

    private function createBill(Consumer $consumer, string $prn, string $billingPeriod, float $unitRate = 20.00): Bill
    {
        $currentCharges = 150.00 + (10.00 * $unitRate);

        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $billingPeriod,
            'due_date' => date('Y-m-d', strtotime($billingPeriod.' +17 days')),
            'prn' => $prn,
            'amount' => $currentCharges,
            'base_charge' => 150.00,
            'water_charge' => $currentCharges,
            'arrears_amount' => 0.00,
            'penalty_amount' => 0.00,
            'loan_installment_amount' => 0.00,
            'units_consumed' => 10.00,
            'unit_rate' => $unitRate,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
    }
}
