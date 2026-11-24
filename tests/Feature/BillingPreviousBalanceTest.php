<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPreviousBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_bill_carries_previous_unpaid_balance_into_total_due(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);

        $consumer = Consumer::query()->create([
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $previousReading = MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => now()->startOfMonth()->subMonth()->day(8)->toDateString(),
            'previous_reading' => 100,
            'current_reading' => 115,
            'consumption' => 15,
            'status' => 'Encoded',
        ]);

        $previousBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'meter_reading_id' => $previousReading->id,
            'billing_period' => now()->startOfMonth()->subMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'TEST-PREV-2233',
            'amount' => 450.00,
            'base_charge' => 150.00,
            'water_charge' => 450.00,
            'arrears_amount' => 0.00,
            'penalty_amount' => 0.00,
            'units_consumed' => 15,
            'unit_rate' => 20.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $previousBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'TEST-PARTIAL-2233',
            'amount' => 200.00,
            'payment_date' => now()->startOfMonth()->subMonth()->day(15)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $previousBill->refresh()->syncPaymentStatus();

        $currentReading = MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => now()->startOfMonth()->day(8)->toDateString(),
            'previous_reading' => 115,
            'current_reading' => 127,
            'consumption' => 12,
            'status' => 'Encoded',
        ]);

        $this->actingAs($admin)
            ->post(route('billing.generate'), [
                'billing_period' => now()->startOfMonth()->toDateString(),
                'due_date' => now()->endOfMonth()->toDateString(),
                'base_charge' => 150.00,
                'member_unit_rate' => 18.00,
                'non_member_unit_rate' => 20.00,
            ])
            ->assertRedirect(route('billing.index', ['period' => now()->format('Y-m')]));

        $bill = Bill::query()->where('meter_reading_id', $currentReading->id)->firstOrFail();

        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'TEST-CURRENT-PAYMENT-2233',
            'amount' => 100.00,
            'payment_date' => now()->startOfMonth()->day(20)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $bill->refresh();

        $this->assertEquals(250.00, (float) $bill->arrears_amount);
        // Penalties and minimum charges are no longer applied to new bills.
        $this->assertEquals(0.00, (float) $bill->penalty_amount);
        $this->assertEquals(390.00, (float) $bill->water_charge);
        // Previous outstanding balance + current month charges.
        $this->assertEquals(640.00, (float) $bill->amount);
        $this->assertEquals(150.00, $bill->billing_breakdown['base_charge']);
        $this->assertEquals(240.00, $bill->billing_breakdown['usage_charge']);
        $this->assertEquals(
            $bill->billing_breakdown['base_charge'] + $bill->billing_breakdown['usage_charge'],
            $bill->billing_breakdown['current_bill_amount'],
        );
        $this->assertEquals(250.00, $bill->billing_breakdown['previous_unpaid_balance']);
        $this->assertEquals(640.00, $bill->billing_breakdown['total_amount_due']);
        $this->assertEquals(100.00, $bill->billing_breakdown['verified_payments']);
        $this->assertEquals(540.00, $bill->billing_breakdown['remaining_balance']);
        $this->assertEquals(450.00, $bill->previous_balance_audit['source_bill_amount']);
        $this->assertEquals(200.00, $bill->previous_balance_audit['payments_applied_before_carryover']);
        $this->assertEquals(200.00, $bill->previous_balance_audit['verified_payments']);
        $this->assertEquals(250.00, $bill->previous_balance_audit['remaining_balance']);
        $this->assertEquals(250.00, $bill->previous_balance_audit['carried_balance']);
    }
}
