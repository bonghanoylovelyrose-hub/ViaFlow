<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HistoricalBillingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshots_carry_through_year_end_and_current_payments_do_not_rewrite_history(): void
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        try {
            $admin = User::factory()->create(['role' => 'admin']);
            $reader = User::factory()->create(['role' => 'meter_reader']);
            $user = User::factory()->create(['role' => 'consumer']);
            $consumer = Consumer::create(['user_id' => $user->id, 'full_name' => 'Historical Test', 'account_number' => 'HISTORY-1', 'meter_number' => 'HISTORY-M1', 'status' => 'Active', 'membership_type' => 'Non-Member']);
            $reading = MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => '2026-10-08', 'previous_reading' => 0, 'current_reading' => 10, 'consumption' => 10, 'status' => 'Encoded']);
            $october = Bill::create(['consumer_id' => $consumer->id, 'meter_reading_id' => $reading->id, 'bill_type' => 'Water', 'billing_period' => '2026-10-01', 'due_date' => '2026-11-02', 'prn' => 'HISTORY-OCT', 'amount' => 180, 'water_charge' => 180, 'status' => 'Paid with Balance']);
            Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $october->id, 'payment_type' => 'Water', 'reference_number' => 'HISTORY-PAID-OCT', 'amount' => 170, 'payment_date' => '2026-10-15', 'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Verified', 'verified_at' => now()]);

            $generate = function (string $period, int $current) use ($consumer, $reader, $admin): Bill {
                MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => Carbon::parse($period)->day(8)->toDateString(), 'previous_reading' => 0, 'current_reading' => $current, 'consumption' => 0, 'status' => 'Encoded']);
                $this->actingAs($admin)->post(route('billing.generate'), ['billing_period' => $period, 'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(), 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20])->assertSessionHasNoErrors();
                return Bill::where('consumer_id', $consumer->id)->where('bill_type', 'Water')->whereDate('billing_period', $period)->firstOrFail();
            };
            $pay = function (Bill $bill, int $amount, string $reference) use ($admin, $consumer): void {
                $payment = Payment::create(['consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'payment_type' => 'Water', 'reference_number' => $reference, 'amount' => $amount, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Pending']);
                $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertSessionHasNoErrors();
            };

            $november = $generate('2026-11-01', 11);
            $this->assertEquals(10, $november->arrears_amount);
            $this->assertEquals(180, $november->amount);
            $this->assertEquals(10, $october->fresh()->statement_balance);
            $this->assertEquals(170, $october->fresh()->statement_paid_amount);
            $pay($november, 60, 'HISTORY-PAY-NOV');
            $december = $generate('2026-12-01', 12);
            $this->assertEquals(120, $december->arrears_amount);
            $this->assertEquals(290, $december->amount);
            $this->assertEquals(120, $november->fresh()->statement_balance);
            $this->assertEquals(60, $november->fresh()->statement_paid_amount);
            $pay($december, 50, 'HISTORY-PAY-DEC');
            $january = $generate('2027-01-01', 13);
            $this->assertEquals(240, $january->arrears_amount);
            $this->assertEquals(410, $january->amount);
            $this->assertEquals(240, $december->fresh()->statement_balance);
            $this->assertEquals(50, $december->fresh()->statement_paid_amount);
            $this->assertEquals(410, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
            $this->assertEquals(0, $october->fresh()->balance_due);
            $this->assertEquals(10, $october->fresh()->statement_balance);
            $this->assertEquals(120, $november->fresh()->statement_balance);

            $this->actingAs($admin)->post(route('billing.generate'), ['billing_period' => '2026-11-01', 'due_date' => '2026-11-30', 'base_charge' => 999, 'member_unit_rate' => 18, 'non_member_unit_rate' => 50])->assertSessionHasNoErrors();
            $this->assertEquals(180, $november->fresh()->amount);
            $this->assertEquals(120, $november->fresh()->statement_balance);
            $this->assertSame(4, Bill::where('consumer_id', $consumer->id)->where('bill_type', 'Water')->count());
            $this->assertEquals(410, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));

            MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number, 'reading_date' => '2026-09-08', 'previous_reading' => 0, 'current_reading' => 1, 'consumption' => 1, 'status' => 'Encoded']);
            $this->actingAs($admin)->post(route('billing.generate'), ['billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20])->assertSessionHasErrors('billing_period');
            $this->assertFalse(Bill::where('consumer_id', $consumer->id)->whereDate('billing_period', '2026-09-01')->exists());
            $this->assertEquals(410, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));

            foreach ([$admin, $user] as $actor) {
                $response = $this->actingAs($actor)->get(route('billing.show', $october))->assertOk()->assertSee('Carried forward:')->assertSee('P 10.00')->assertSee('P 170.00');
                $this->assertEquals(10, $response->viewData('bill')->statement_balance);
                $response = $this->actingAs($actor)->get(route('billing.show', $november))->assertOk()->assertSee('P 120.00')->assertSee('P 60.00');
                $this->assertEquals(120, $response->viewData('bill')->statement_balance);
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}

