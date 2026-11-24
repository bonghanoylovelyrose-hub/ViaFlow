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
 * Balance carry-forward must move every unpaid peso forward exactly once, and
 * payments must settle the oldest period first.
 */
class BillingBalanceCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_unpaid_months_carry_forward_without_double_counting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        // May unpaid (400). June absorbs it: 400 carried + 390 current = 790.
        // July absorbs June: 790 carried + 390 current = 1180.
        $may = $this->createBill($consumer, 'CF-MAY', 400.00, '2026-05-01', 0.00);
        $june = $this->createBill($consumer, 'CF-JUNE', 790.00, '2026-06-01', 400.00, $may);
        $july = $this->createBill($consumer, 'CF-JULY', 1180.00, '2026-07-01', 790.00, $june);

        // Carrying a balance moves it, so the source periods are closed exactly
        // as the real billing cycle closes them.
        $may->forceFill(['carried_forward_to_bill_id' => $june->id, 'status' => 'Paid'])->save();
        $june->forceFill(['carried_forward_to_bill_id' => $july->id, 'status' => 'Paid'])->save();

        $balances = app(ConsumerBalanceService::class);

        // May and June live inside July's total, so only July is still owed.
        $this->assertEquals(1180.00, $balances->totalOutstandingFor($consumer->id));

        // Generate August: the whole remaining 1180 carries forward once.
        $this->createReading($consumer, $reader, '2026-08-08', 100);
        $this->actingAs($admin)->post(route('billing.generate'), $this->generatePayload('2026-08-01'));

        $august = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-08-01')
            ->firstOrFail();

        $this->assertEquals(1180.00, (float) $august->arrears_amount);
        // base 150 + 20*100 usage = 2150 current charges, plus 1180 carried.
        $this->assertEquals(2150.00, (float) $august->water_charge);
        $this->assertEquals(3330.00, (float) $august->amount);

        // Generating again for the same month must not stack another carry.
        $this->actingAs($admin)->post(route('billing.generate'), $this->generatePayload('2026-08-01'));

        $this->assertEquals(1, Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-08-01')
            ->count());

        // The whole liability rolls into August exactly once: 1180 + 2150.
        $this->assertEquals(3330.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
    }

    public function test_new_bill_breaks_down_previous_balance_and_current_charges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        $previous = $this->createBill($consumer, 'BD-PREV', 500.00, '2026-07-01');
        Payment::query()->create([
            'bill_id' => $previous->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-BD-PREV',
            'amount' => 300.00,
            'payment_date' => '2026-07-20',
            'payment_method' => 'Cash',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);
        $previous->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $previous->syncPaymentStatus();

        $this->createReading($consumer, $reader, '2026-08-08', 110);
        $this->actingAs($admin)->post(route('billing.generate'), $this->generatePayload('2026-08-01'));

        $bill = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-08-01')
            ->firstOrFail();

        $breakdown = $bill->billing_breakdown;

        // Clear breakdown of each component.
        $this->assertEquals(200.00, $breakdown['previous_unpaid_balance'], 'Previous outstanding balance component');
        $this->assertEquals(150.00, $breakdown['base_charge'], 'Base charge component');
        $this->assertEquals(2200.00, $breakdown['usage_charge'], 'Usage charge component');
        $this->assertEquals(2350.00, $breakdown['current_bill_amount'], 'Current month water charges component');
        $this->assertEquals(2550.00, $breakdown['total_amount_due'], 'Total amount due');
        $this->assertEquals(
            $breakdown['previous_unpaid_balance'] + $breakdown['current_bill_amount'],
            $breakdown['total_amount_due'],
            'Total must equal previous balance plus current charges.',
        );
        $this->assertEquals(0.00, $breakdown['penalties'], 'New bills carry no penalty');
    }

    public function test_partial_payment_allocates_to_the_oldest_balance_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'ALLOC-MAY', 500.00, '2026-05-01');
        $june = $this->createBill($consumer, 'ALLOC-JUNE', 400.00, '2026-06-01');

        // Partial payment of 700 clears May (500) and leaves 200 on June.
        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($june, 700.00))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $may->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $june->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');

        $this->assertEquals(0.00, $may->balance_due, 'Oldest bill is fully settled first');
        $this->assertSame('Paid', $may->status);
        $this->assertEquals(200.00, $june->balance_due, 'Only the remainder stays on the newer bill');
        $this->assertSame('Paid with Balance', $june->status);

        // Payment history is preserved as separate records.
        $this->assertEquals([500.00, 200.00], Payment::query()
            ->orderBy('id')
            ->pluck('amount')
            ->map(fn ($amount) => (float) $amount)
            ->all());

        $this->assertEquals(200.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
    }

    public function test_remaining_partial_balance_is_the_only_amount_carried_forward(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'CARRY-MAY', 500.00, '2026-05-01');
        $june = $this->createBill($consumer, 'CARRY-JUNE', 400.00, '2026-06-01');

        $this->actingAs($admin)->post(route('payments.store'), $this->paymentPayload($june, 700.00));

        $may->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $june->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $may->syncPaymentStatus();
        $june->syncPaymentStatus();

        $this->createReading($consumer, $reader, '2026-07-08', 100);
        $this->actingAs($admin)->post(route('billing.generate'), $this->generatePayload('2026-07-01'));

        $july = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-07-01')
            ->firstOrFail();

        // Only June's remaining 200 is carried, never the settled 500.
        $this->assertEquals(200.00, (float) $july->arrears_amount);
        $this->assertEquals(2150.00, (float) $july->water_charge);
        $this->assertEquals(2350.00, (float) $july->amount);
    }

    public function test_historical_penalties_are_preserved_while_new_bills_have_none(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->createConsumer();

        $historic = $this->createBill($consumer, 'HIST-PEN', 500.00, '2026-06-01');
        $historic->forceFill(['penalty_amount' => 50.00])->save();

        $this->createReading($consumer, $reader, '2026-07-08', 100);
        $this->actingAs($admin)->post(route('billing.generate'), $this->generatePayload('2026-07-01'));

        $july = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereDate('billing_period', '2026-07-01')
            ->firstOrFail();

        $this->assertEquals(50.00, (float) $historic->refresh()->penalty_amount, 'Historic penalty is untouched');
        $this->assertEquals(0.00, (float) $july->penalty_amount, 'New bills carry no penalty');

        // The billing form no longer collects a penalty rate or minimum charge.
        $this->actingAs($admin)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertDontSee('name="penalty_rate"', false)
            ->assertDontSee('name="minimum_charge"', false)
            ->assertDontSee('Penalty Rate (%)');
    }

    public function test_generate_endpoint_rejects_removed_charge_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('billing.index'))
            ->post(route('billing.generate'), [
                'billing_period' => '2026-08-01',
                'due_date' => '2026-08-31',
                'base_charge' => 150,
                'member_unit_rate' => 18,
                'non_member_unit_rate' => 20,
                'penalty_rate' => 10,
            ])
            ->assertSessionHasErrors('billing_period');

        $this->actingAs($admin)
            ->from(route('billing.index'))
            ->post(route('billing.generate'), [
                'billing_period' => '2026-08-01',
                'due_date' => '2026-08-31',
                'base_charge' => 150,
                'member_unit_rate' => 18,
                'non_member_unit_rate' => 20,
                'minimum_charge' => 150,
            ])
            ->assertSessionHasErrors('billing_period');
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        return Consumer::query()->create(array_merge([
            'full_name' => 'Carry Forward Consumer',
            'account_number' => 'CF-1001',
            'meter_number' => 'CF-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }

    private function createBill(
        Consumer $consumer,
        string $prn,
        float $amount,
        string $period,
        float $arrears = 0.00,
        ?Bill $carriedFrom = null,
    ): Bill {
        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => \Illuminate\Support\Carbon::parse($period)->endOfMonth()->toDateString(),
            'prn' => $prn,
            'amount' => $amount,
            'arrears_amount' => $arrears,
            'carried_forward_from_bill_id' => $carriedFrom?->id,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
    }

    private function createReading(Consumer $consumer, User $reader, string $readingDate, float $current): MeterReading
    {
        return MeterReading::query()->create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => $readingDate,
            'previous_reading' => 0,
            'current_reading' => $current,
            'consumption' => $current,
            'status' => 'Encoded',
        ]);
    }

    private function generatePayload(string $billingPeriod): array
    {
        return [
            'billing_period' => $billingPeriod,
            'due_date' => \Illuminate\Support\Carbon::parse($billingPeriod)->endOfMonth()->toDateString(),
            'base_charge' => 150.00,
            'member_unit_rate' => 18.00,
            'non_member_unit_rate' => 20.00,
        ];
    }

    private function paymentPayload(Bill $bill, float $amount): array
    {
        return [
            'bill_id' => $bill->id,
            'amount' => $amount,
            'payment_date' => '2026-08-15',
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ];
    }
}