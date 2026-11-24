<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsumerBillingScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_pages_share_the_scenario_records_and_keep_the_recorded_payment_view(): void
    {
        $this->app->instance('env', 'local');
        $this->travelTo(Carbon::parse('2026-11-24 12:30:00', 'Asia/Manila'));
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->consumer('MT-3333');
        $reading = new MeterReading([
            'consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-11-25', 'previous_reading' => 14, 'current_reading' => 21,
            'consumption' => 7, 'status' => MeterReading::STATUS_ENCODED,
        ]);
        $reading->forceFill(['is_billing_fixture' => true])->save();
        $october = $this->bill($consumer, 'OCT-SCENARIO', '2026-10-01', '2026-10-25', 330, true);
        $november = $this->bill($consumer, 'NOV-SCENARIO', '2026-11-01', '2026-11-25', 506, true, [
            'meter_reading_id' => $reading->id, 'arrears_amount' => 230, 'water_charge' => 276,
        ]);
        $payment = new Payment([
            'consumer_id' => $consumer->id, 'bill_id' => $october->id, 'status' => 'Verified',
            'payment_type' => 'Water', 'reference_number' => 'OCT-SCENARIO-PAYMENT',
            'amount' => 100, 'payment_date' => '2026-11-01', 'payment_method' => 'Cash',
        ]);
        $payment->forceFill(['is_billing_fixture' => true])->save();
        $october->carryBalanceInto($november);
        $original = $this->bill($consumer, 'NOV-RECORDED', '2026-11-01', '2026-11-23', 180, false);
        $original->forceFill(['superseded_by_bill_id' => $november->id])->save();

        $this->actingAs($consumer->user)->get(route('dashboard'))->assertOk()
            ->assertSee('Meter Reading History')->assertSee('21.00')->assertSee('Partially Paid');
        $billing = $this->get(route('billing.index'))->assertOk()->assertSee('OCT-SCENARIO')
            ->assertDontSee('NOV-SCENARIO')->assertDontSee('NOV-RECORDED')->assertDontSee('projected after issue');
        $this->assertSame(230.0, $billing->viewData('consumerTotalBalanceDue'));
        $this->get(route('billing.show', ['bill' => $november, 'ledger' => 'demo']))->assertNotFound();
        $this->get(route('payments.index'))->assertOk()->assertSee('OCT-SCENARIO-PAYMENT')
            ->assertSee('100.00')->assertSee('230.00')->assertDontSee('id="consumerPaymentForm"', false);
        $this->assertSame([$october->id], $this->get(route('billing.index', ['ledger' => 'recorded']))
            ->assertOk()->viewData('filteredBills')->getCollection()->modelKeys());
        $this->get(route('payments.index', ['ledger' => 'recorded']))->assertOk()
            ->assertSee('id="consumerPaymentForm"', false)->assertDontSee('OCT-SCENARIO-PAYMENT');

        $this->travelTo(Carbon::parse('2026-11-26 12:00:00', 'Asia/Manila'));
        $this->assertSame(230.0, $this->get(route('billing.index'))->assertDontSee('NOV-SCENARIO')->viewData('consumerTotalBalanceDue'));
    }

    public function test_november_history_waits_for_official_generation_and_carries_the_balance_and_credit_once(): void
    {
        $this->app->instance('env', 'local');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->travelTo(Carbon::parse('2026-11-24 12:30:00', 'Asia/Manila'));
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $consumer = $this->consumer('MT-7777');
        $octoberReading = MeterReading::create([
            'consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-10-25', 'previous_reading' => 6, 'current_reading' => 21,
            'consumption' => 15, 'status' => MeterReading::STATUS_ENCODED,
        ]);
        $octoberReading->forceFill(['is_billing_fixture' => true])->save();
        $october = $this->bill($consumer, 'OCT-WATER', '2026-10-01', '2026-10-25', 420, true, ['meter_reading_id' => $octoberReading->id]);
        $oldReading = MeterReading::create([
            'consumer_id' => $consumer->id, 'reader_id' => $reader->id, 'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-11-17', 'previous_reading' => 0, 'current_reading' => 15,
            'consumption' => 15, 'status' => MeterReading::STATUS_ENCODED,
        ]);
        $original = $this->bill($consumer, 'NOV-ISSUED', '2026-11-01', '2026-11-17', 270, false, ['meter_reading_id' => $oldReading->id]);
        $november = $this->bill($consumer, 'NOV-REPLACEMENT', '2026-11-01', '2026-11-25', 714, true, [
            'arrears_amount' => 420, 'water_charge' => 294,
        ]);
        $original->forceFill(['superseded_by_bill_id' => $november->id])->save();
        $october->carryBalanceInto($november);
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $original->id, 'status' => 'Verified',
            'payment_type' => 'Water', 'reference_number' => 'ORIGINAL-PAYMENT', 'amount' => 270,
            'payment_date' => '2026-11-20', 'payment_method' => 'Cash',
        ]);

        $generate = [
            'billing_period' => '2026-11-01', 'due_date' => '2026-12-10',
            'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20,
        ];
        $capture = ['meter_number' => 'MT-7777', 'reading_date' => '2026-11-26', 'current_reading' => 29];
        $this->actingAs($reader)->post(route('meter-readings.store'), $capture)->assertSessionHasErrors('reading_date');
        foreach (['before scheduled date', 'after scheduled date', 'after reading'] as $stage) {
            $adminBilling = $this->actingAs($admin)->get(route('billing.index', ['search' => 'MT-7777']))->assertOk();
            $this->assertSame(420.0, $adminBilling->viewData('billingAccounts')->getCollection()->sole()['outstanding']);
            $adminHistory = $this->get(route('billing.monthly-history', $consumer))->assertOk()->assertDontSee('November 2026');
            $this->get(route('billing.statement.pdf', $november))->assertNotFound();
            $consumerBilling = $this->actingAs($consumer->user)->get(route('billing.index'))->assertOk()->assertDontSee('NOV-REPLACEMENT')->assertDontSee('projected after issue');
            $this->assertSame(420.0, $consumerBilling->viewData('consumerTotalBalanceDue'));
            $this->assertSame([$october->id], $adminHistory->viewData('records')->pluck('record_id')->all());
            $this->assertSame([$october->id], $consumerBilling->viewData('monthlyRecords')->pluck('record_id')->all());
            $this->assertSame(0.0, $november->fresh()->balance_due);
            $this->assertSame(420.0, $october->fresh()->balance_due);
            if ($stage === 'before scheduled date') {
                $this->travelTo(Carbon::parse('2026-11-26 12:00:00', 'Asia/Manila'));
                $this->actingAs($admin)->post(route('billing.generate'), $generate)->assertSessionHasErrors('billing_period');
            } elseif ($stage === 'after scheduled date') {
                $this->actingAs($reader)->postJson(route('meter-readings.lookup-by-meter'), $capture)
                    ->assertOk()->assertJsonPath('already_read', false)->assertJsonPath('consumer.previous_reading', '21.00');
                $this->post(route('meter-readings.store'), $capture)->assertRedirect()->assertSessionHasNoErrors();
                $this->post(route('meter-readings.store'), $capture)->assertSessionHasErrors('duplicate_reading');
            }
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($admin)->post(route('billing.generate'), $generate)->assertRedirect()->assertSessionHasNoErrors();
        }
        $issued = $consumer->bills()->whereNull('superseded_by_bill_id')->whereDate('billing_period', '2026-11-01')->sole();
        $this->assertEquals(21, $issued->meterReading->previous_reading);
        $this->assertEquals(8, $issued->units_consumed);
        $this->assertEquals(420, $issued->arrears_amount);
        $this->assertSame(270.0, $issued->earlier_issue_credit);
        $this->assertSame(0.0, $october->fresh()->balance_due);
        $this->assertSame($issued->id, $october->fresh()->carried_forward_to_bill_id);
        $this->assertSame($issued->id, $november->fresh()->superseded_by_bill_id);
        $this->assertSame($oldReading->id, $original->fresh()->meter_reading_id);
        $outstanding = (float) $issued->amount - 270;
        $this->assertSame($outstanding, $issued->balance_due);
        $adminBilling = $this->get(route('billing.index', ['search' => 'MT-7777', 'status' => 'Paid with Balance']))->assertOk();
        $this->assertSame($outstanding, $adminBilling->viewData('billingAccounts')->getCollection()->sole()['outstanding']);
        $this->assertSame($outstanding, $adminBilling->viewData('billingSummary')['balance_due']);
        $adminHistory = $this->get(route('billing.monthly-history', $consumer))->assertOk()->assertSee($issued->prn)->assertDontSee('NOV-REPLACEMENT');
        $consumerBilling = $this->actingAs($consumer->user)->get(route('billing.index'))->assertOk()->assertSee($issued->prn)->assertDontSee('NOV-REPLACEMENT');
        $this->assertSame($outstanding, $consumerBilling->viewData('consumerTotalBalanceDue'));
        $this->assertSame([$issued->id, $october->id], $adminHistory->viewData('records')->pluck('record_id')->all());
        $this->assertSame([$issued->id, $october->id], $consumerBilling->viewData('monthlyRecords')->pluck('record_id')->all());
        $adminHistory->assertSee('Includes &#8369;270.00 credit', false);
        $consumerBilling->assertSee('Includes &#8369;270.00 credit', false);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'bill_id' => $original->id, 'amount' => 270, 'status' => 'Verified']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_notice_status_matches_admin_and_scenario_pages_are_limited_to_the_designated_accounts(): void
    {
        $this->app->instance('env', 'local');
        $this->travelTo(Carbon::parse('2026-11-24 12:30:00', 'Asia/Manila'));
        $consumer = $this->consumer('MT-9191');
        $bill = $this->bill($consumer, 'NOTICE-SCENARIO', '2026-10-01', '2026-10-25', 696, true);
        $notice = new DisconnectionNotice([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'source' => DisconnectionNotice::SOURCE_WATER,
            'status' => DisconnectionNotice::STATUS_ACTIVE, 'outstanding_amount' => 696, 'months_unpaid' => 2,
            'issued_at' => now()->subDays(4), 'deadline_at' => now()->subDay(),
        ]);
        $notice->forceFill(['is_billing_fixture' => true])->save();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk()
            ->assertSee('data-record-status="Ready for Disconnection"', false)->assertSee('696.00');
        foreach (['dashboard', 'billing.index', 'payments.index', 'connection.status'] as $route) {
            $this->actingAs($consumer->user)->get(route($route))->assertOk()
                ->assertSee('data-notice-status="Ready for Disconnection"', false)->assertSee('696.00');
        }

        $other = $this->consumer('MT-2121');
        $unavailable = $this->bill($other, 'UNAVAILABLE-SCENARIO', '2026-10-01', '2026-10-25', 330, true);
        $this->actingAs($other->user)->get(route('billing.index'))->assertOk()
            ->assertDontSee('UNAVAILABLE-SCENARIO')->assertDontSee('Scenario billing');
        $this->get(route('billing.show', ['bill' => $unavailable, 'ledger' => 'demo']))->assertNotFound();
        $this->get(route('billing.show', ['bill' => $bill, 'ledger' => 'demo']))->assertForbidden();
    }

    private function consumer(string $meter): Consumer
    {
        $user = User::factory()->create(['role' => 'consumer']);

        return Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => $user->name, 'account_number' => $meter.'-ACCOUNT',
            'meter_number' => $meter, 'membership_type' => 'Member', 'status' => 'Active',
        ]);
    }

    private function bill(Consumer $consumer, string $reference, string $period, string $issued, float $amount, bool $fixture, array $attributes = []): Bill
    {
        $bill = new Bill($attributes + [
            'consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => $period,
            'prn' => $reference, 'due_date' => Carbon::parse($issued)->addDays(15), 'amount' => $amount,
            'water_charge' => $amount, 'status' => 'Unpaid', 'months_unpaid' => 0,
        ]);
        $bill->forceFill(['created_at' => $issued, 'is_billing_fixture' => $fixture, 'is_demo' => $fixture])->save();

        return $bill;
    }
}
