<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\User;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NovemberWaterBillingDemonstrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_november_meter_capture_billing_payment_and_disconnection_workflows(): void
    {
        // Synthetic readings and test payments are confined to the test database.
        $this->on('2026-09-25 09:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $meters = collect(['MT-0909', 'MT-9191', 'MT-3333', 'MT-7777'])
            ->mapWithKeys(fn (string $meter) => [$meter => $this->demoAccount($meter)]);

        $this->capture($reader, $meters['MT-0909'], '2026-09-25', 8);
        $this->capture($reader, $meters['MT-9191'], '2026-09-25', 12);
        $this->generate($admin, '2026-09-01', '2026-10-10');

        $this->on('2026-10-25 09:00');
        foreach (['MT-0909' => 17, 'MT-9191' => 22, 'MT-3333' => 10, 'MT-7777' => 15] as $meter => $reading) {
            $this->capture($reader, $meters[$meter], '2026-10-25', $reading);
        }
        $this->generate($admin, '2026-10-01', '2026-11-09');

        $this->on('2026-11-01 09:00');
        $partial = $this->bill($meters['MT-3333'], '2026-10-01');
        $unpaid = $this->bill($meters['MT-7777'], '2026-10-01');
        $this->actingAs($admin)->post(route('payments.store'), [
            'bill_id' => $partial->id,
            'amount' => 100,
            'payment_date' => '2026-11-01',
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Paid with Balance', $partial->refresh()->paymentSettlementStatus());
        $this->assertSame(230.0, $partial->balance_due);
        $this->assertSame('Unpaid', $unpaid->paymentSettlementStatus());
        $this->assertSame(420.0, $unpaid->balance_due);
        $this->assertFalse($unpaid->payments()->exists());
        $this->actingAs($meters['MT-3333']->user)->get(route('payments.index'))
            ->assertOk()->assertSee('My Payments')->assertSee('100.00');

        $this->on('2026-11-24 09:00');
        $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertDatabaseCount('disconnection_notices', 0);
        foreach (['MT-3333' => '10.00', 'MT-7777' => '15.00'] as $meter => $baseline) {
            $this->actingAs($reader)->get(route('meter-readings.index', ['month' => '2026-11', 'search' => $meter]))
                ->assertOk()->assertSee($meter);
            $this->postJson(route('meter-readings.lookup-by-meter'), ['meter_number' => $meter, 'reading_date' => '2026-11-25'])
                ->assertOk()->assertJsonPath('already_read', false)->assertJsonPath('consumer.previous_reading', $baseline);
        }

        $issued = $this->on('2026-11-25 09:00');
        foreach (['MT-0909' => 27, 'MT-9191' => 37, 'MT-3333' => 17, 'MT-7777' => 23] as $meter => $reading) {
            $this->capture($reader, $meters[$meter], '2026-11-25', $reading);
        }
        $this->generate($admin, '2026-11-01', '2026-12-10');
        $this->generate($admin, '2026-11-01', '2026-12-10');
        $this->assertDatabaseCount('bills', 10);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('disconnection_notices', 2);

        $this->get(route('billing.index', ['period' => '2026-11']))->assertOk()
            ->assertSee('Water Billing')->assertSee('506.00')->assertSee('714.00');
        $grace = $this->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => 'grace']))
            ->assertOk()->assertSee('Within Grace Period')->assertSee('Water Billing Overdue');
        $this->assertSame(2, $grace->viewData('disconnectionNotices')->total());

        $balances = app(ConsumerBalanceService::class);
        foreach ([
            'MT-0909' => [17, 27, 330, 606, 936],
            'MT-9191' => [22, 37, 420, 696, 1116],
            'MT-3333' => [10, 17, 276, 230, 506],
            'MT-7777' => [15, 23, 294, 420, 714],
        ] as $meter => [$previous, $current, $charges, $carried, $total]) {
            $consumer = $meters[$meter];
            $bill = $this->bill($consumer, '2026-11-01');
            $this->assertSame('2026-11-25', $bill->created_at->copy()->timezone('Asia/Manila')->toDateString());
            $this->assertSame('2026-12-10', $bill->due_date->toDateString());
            $this->assertEquals($previous, $bill->meterReading->previous_reading);
            $this->assertEquals($current, $bill->meterReading->current_reading);
            $this->assertEquals($current - $previous, $bill->units_consumed);
            $this->assertEquals($charges, $bill->water_charge);
            $this->assertEquals($carried, $bill->arrears_amount);
            $this->assertEquals($total, $bill->amount);
            $this->assertEquals($total, $balances->totalOutstandingFor($consumer->id));
            $this->assertFalse($balances->isOverdue($bill));
            $this->assertContains($consumer->fresh()->status, Consumer::READING_QUEUE_STATUSES);
            $this->actingAs($consumer->user)->get(route('billing.show', $bill))->assertOk()
                ->assertSee('Previous Reading')->assertSee('Current Water Consumption')->assertSee('Previous Balance')
                ->assertSee('Verified Payments')->assertSee(number_format($total, 2));
        }

        foreach (['MT-0909', 'MT-9191'] as $meter) {
            $consumer = $meters[$meter];
            $notice = $consumer->disconnectionNotices()->sole();
            $bill = $this->bill($consumer, '2026-11-01');
            $this->assertSame('Active', $consumer->fresh()->status);
            $this->assertEquals($bill->id, $notice->bill_id);
            $this->assertSame(DisconnectionNotice::SOURCE_WATER, $notice->source);
            $this->assertSame('Water Billing Overdue', $notice->disconnection_reason);
            $this->assertSame(2, $notice->months_unpaid);
            $this->assertEquals($bill->amount, $notice->outstanding_amount);
            $this->assertTrue($notice->issued_at->equalTo($issued));
            $this->assertTrue($notice->deadline_at->equalTo($issued->copy()->addDays(3)));
            $this->assertSame('sent', ConsumerNotificationLog::query()->where('consumer_id', $consumer->id)
                ->where('event', 'disconnection_notice_'.$bill->id)->sole()->status);
        }

        $this->on('2026-11-28 08:59:59');
        $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => 'grace']))
            ->assertOk()->assertSee('data-record-status="Within Grace Period"', false);
        $this->on('2026-11-28 09:00');
        $ready = $this->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => 'ready']))
            ->assertOk()->assertSee('data-record-status="Ready for Disconnection"', false);
        $this->assertSame(2, $ready->viewData('disconnectionNotices')->total());
        $this->assertSame(4, Consumer::query()->whereIn('status', Consumer::READING_QUEUE_STATUSES)->whereNull('disconnected_at')->count());
    }

    private function on(string $date): Carbon
    {
        $time = Carbon::parse($date, 'Asia/Manila');
        $this->travelTo($time);

        return $time;
    }

    private function demoAccount(string $meter): Consumer
    {
        $email = strtolower($meter).'@viaflow.test';
        $user = User::factory()->create(['role' => 'consumer', 'email' => $email]);

        return Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Development Fixture '.$meter,
            'email' => $email,
            'account_number' => 'DEMO-'.$meter,
            'meter_number' => $meter,
            'membership_type' => 'Member',
            'status' => 'Active',
        ]);
    }

    private function capture(User $reader, Consumer $consumer, string $date, int $current): void
    {
        $this->actingAs($reader)->post(route('meter-readings.store'), [
            'meter_number' => $consumer->meter_number,
            'reading_date' => $date,
            'current_reading' => $current,
            'remarks' => 'Development test reading',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function generate(User $admin, string $period, string $due): void
    {
        $this->actingAs($admin)->post(route('billing.generate'), [
            'billing_period' => $period,
            'due_date' => $due,
            'base_charge' => BillingComputationService::DEFAULT_BASE_CHARGE,
            'member_unit_rate' => BillingComputationService::MEMBER_UNIT_RATE,
            'non_member_unit_rate' => BillingComputationService::NON_MEMBER_UNIT_RATE,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function bill(Consumer $consumer, string $period): Bill
    {
        return $consumer->bills()->whereDate('billing_period', $period)->sole();
    }
}
