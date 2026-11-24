<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real billing cycle:
 *   August unpaid -> September generated carrying it -> consumer pays
 *   -> October generated carrying only what is still unpaid.
 *
 * Readings rise by 20 m3 each month, so every cycle adds
 * base charge 150 + (20 x 20) = 550 of new charges.
 */
class CarryForwardRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHLY_CHARGES = 550.00;

    private User $admin;
    private User $reader;
    private Consumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->reader = User::factory()->create(['role' => 'meter_reader']);

        $this->consumer = Consumer::query()->create([
            'full_name' => 'Carry Forward Consumer',
            'email' => 'carry@example.com',
            'account_number' => 'CF-1',
            'meter_number' => 'CF-M-1',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
    }

    /** @param float $current the running meter reading for that month */
    private function generateCycle(string $period, float $current): Bill
    {
        MeterReading::query()->create([
            'consumer_id' => $this->consumer->id,
            'reader_id' => $this->reader->id,
            'reading_date' => \Illuminate\Support\Carbon::parse($period)->day(8)->toDateString(),
            'previous_reading' => 0,
            'current_reading' => $current,
            'consumption' => $current,
            'status' => 'Encoded',
        ]);

        $this->actingAs($this->admin)->post(route('billing.generate'), [
            'billing_period' => $period,
            'due_date' => \Illuminate\Support\Carbon::parse($period)->endOfMonth()->toDateString(),
            'base_charge' => 150.00,
            'member_unit_rate' => 18.00,
            'non_member_unit_rate' => 20.00,
        ])->assertSessionHasNoErrors();

        return $this->billFor($period);
    }

    private function billFor(string $period): Bill
    {
        return Bill::query()
            ->where('consumer_id', $this->consumer->id)
            ->whereDate('billing_period', $period)
            ->firstOrFail();
    }

    private function pay(Bill $target, float $amount): void
    {
        $payment = Payment::query()->create([
            'bill_id' => $target->id,
            'consumer_id' => $this->consumer->id,
            'reference_number' => 'PAY-'.uniqid(),
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        app(\App\Services\PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $this->admin);
    }

    private function outstanding(): float
    {
        return app(ConsumerBalanceService::class)->totalOutstandingFor($this->consumer->id);
    }

    public function test_september_includes_the_august_balance_plus_its_own_charges(): void
    {
        $august = $this->generateCycle('2026-08-01', 20);
        $this->assertEquals(self::MONTHLY_CHARGES, (float) $august->amount);
        $this->assertEquals(0.00, (float) $august->arrears_amount);

        $september = $this->generateCycle('2026-09-01', 40);

        $this->assertEquals(self::MONTHLY_CHARGES, (float) $september->arrears_amount, 'September carries the August balance.');
        $this->assertEquals(self::MONTHLY_CHARGES * 2, (float) $september->amount, 'August balance + September charges.');
        $this->assertEquals(self::MONTHLY_CHARGES * 2, $this->outstanding(), 'The debt is counted once, not twice.');
    }

    public function test_paying_the_september_amount_closes_the_august_balance_too(): void
    {
        $august = $this->generateCycle('2026-08-01', 20);
        $september = $this->generateCycle('2026-09-01', 40);

        // The consumer pays exactly the September billing amount.
        $this->pay($september, self::MONTHLY_CHARGES * 2);

        $september->refresh();
        $this->assertEquals(0.00, $september->balance_due);
        $this->assertSame('Paid', $september->status);

        $august->refresh();
        $this->assertEquals(0.00, $august->balance_due, 'August must not be left showing an unpaid balance.');
        $this->assertNotSame('Unpaid', $august->status);
        $this->assertNotNull($august->carried_forward_to_bill_id, 'August is recorded as carried into September.');

        $this->assertEquals(0.00, $this->outstanding());
    }

    public function test_october_does_not_re_carry_a_balance_that_was_already_paid(): void
    {
        $this->generateCycle('2026-08-01', 20);
        $september = $this->generateCycle('2026-09-01', 40);

        $this->pay($september, self::MONTHLY_CHARGES * 2);

        $october = $this->generateCycle('2026-10-01', 60);

        $this->assertEquals(0.00, (float) $october->arrears_amount, 'October must not re-carry a settled balance.');
        $this->assertEquals(self::MONTHLY_CHARGES, (float) $october->amount);
    }

    public function test_partial_payment_reduces_the_total_and_carries_only_the_remainder(): void
    {
        $this->generateCycle('2026-08-01', 20);
        $september = $this->generateCycle('2026-09-01', 40);

        $this->pay($september, 500.00);

        $this->assertEquals(600.00, $this->outstanding(), 'Total drops by exactly what was paid.');

        $october = $this->generateCycle('2026-10-01', 60);

        $this->assertEquals(600.00, (float) $october->arrears_amount, 'Only the unpaid remainder carries forward.');
        $this->assertEquals(1150.00, (float) $october->amount);
    }

    public function test_payment_is_applied_to_the_oldest_period_still_owed(): void
    {
        $august = $this->generateCycle('2026-08-01', 20);
        $september = $this->generateCycle('2026-09-01', 40);

        // August's debt now lives on September, so September is the oldest
        // period still owed and must absorb the payment first.
        $this->pay($september, self::MONTHLY_CHARGES * 2);

        $septemberPaid = Payment::query()
            ->where('bill_id', $september->id)
            ->where('status', 'Verified')
            ->sum('amount');

        $this->assertEquals(self::MONTHLY_CHARGES * 2, (float) $septemberPaid);

        // Both covered periods end up fully settled, none left dangling.
        $this->assertSame('Paid', $september->refresh()->status);
        $this->assertSame('Paid', $august->refresh()->status);
        $this->assertEquals(0.00, $this->outstanding());
    }

    public function test_partial_payment_applies_oldest_first_across_two_open_periods(): void
    {
        // Two genuinely open periods (no carry-forward between them).
        $june = Bill::query()->create([
            'consumer_id' => $this->consumer->id,
            'billing_period' => '2026-06-01',
            'due_date' => '2026-06-30',
            'prn' => 'JUN',
            'amount' => 400.00,
            'status' => 'Unpaid',
        ]);

        $july = Bill::query()->create([
            'consumer_id' => $this->consumer->id,
            'billing_period' => '2026-07-01',
            'due_date' => '2026-07-31',
            'prn' => 'JUL',
            'amount' => 600.00,
            'status' => 'Unpaid',
        ]);

        // 700 covers all of June and part of July.
        $this->pay($july, 700.00);

        $this->assertEquals(400.00, (float) Payment::query()->where('bill_id', $june->id)->where('status', 'Verified')->sum('amount'));
        $this->assertEquals(300.00, (float) Payment::query()->where('bill_id', $july->id)->where('status', 'Verified')->sum('amount'));

        $this->assertSame('Paid', $june->refresh()->status);
        $this->assertSame('Paid with Balance', $july->refresh()->status);
        $this->assertEquals(300.00, $this->outstanding());
    }

    public function test_no_month_is_skipped_when_three_months_go_unpaid(): void
    {
        $june = $this->generateCycle('2026-06-01', 20);   // 550
        $july = $this->generateCycle('2026-07-01', 40);   // 550 carried + 550 = 1100
        $august = $this->generateCycle('2026-08-01', 60);  // 1100 carried + 550 = 1650

        $this->assertEquals(self::MONTHLY_CHARGES, (float) $june->amount);
        $this->assertEquals(self::MONTHLY_CHARGES, (float) $july->arrears_amount);
        $this->assertEquals(self::MONTHLY_CHARGES * 2, (float) $july->amount);
        $this->assertEquals(self::MONTHLY_CHARGES * 2, (float) $august->arrears_amount, 'Both earlier months are carried.');
        $this->assertEquals(self::MONTHLY_CHARGES * 3, (float) $august->amount);
        $this->assertEquals(self::MONTHLY_CHARGES * 3, $this->outstanding(), 'Counted once across all three months.');

        $this->pay($august, self::MONTHLY_CHARGES * 3);

        $this->assertEquals(0.00, $this->outstanding());
    }
}