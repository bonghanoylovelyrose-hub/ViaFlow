<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\OverdueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OverdueToReconnectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_local_billing_fixture_keeps_overdue_balances_without_collection_actions(): void
    {
        Carbon::setTestNow('2026-06-20 08:00:00');
        $consumer = Consumer::create(['full_name' => 'Local Billing Fixture', 'email' => 'fixture@example.test',
            'account_number' => 'FIX-1', 'meter_number' => 'FIX-METER', 'status' => 'Active']);
        config(['billing.local_fixture_consumer_ids' => [(string) $consumer->id]]);

        foreach (['03', '04', '05'] as $month) {
            $this->bill($consumer, 'FIX-'.$month, 300, "2026-{$month}-01", "2026-{$month}-20");
        }

        $environment = app()->environment();
        app()->instance('env', 'local');
        try {
            $standing = app(OverdueMonitoringService::class)->evaluate($consumer, notify: true);
        } finally {
            app()->instance('env', $environment);
        }

        $this->assertSame(3, $standing['months_unpaid']);
        $this->assertSame(900.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertSame('Active', $consumer->fresh()->status);
        $this->assertDatabaseCount('disconnection_notices', 0);
        $this->assertDatabaseCount('consumer_notification_logs', 0);

        // The allowlist has no effect outside the local environment.
        app(OverdueMonitoringService::class)->evaluate($consumer);
        $this->assertDatabaseCount('disconnection_notices', 1);
    }

    public function test_the_full_workflow_from_overdue_notice_to_the_next_meter_reading(): void
    {
        // 1. A bill falls due and passes its grace period.
        Carbon::setTestNow(Carbon::parse('2026-05-05 08:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $user = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'End To End Consumer',
            'email' => 'end.to.consumer@example.com',
            'account_number' => 'E2E-1',
            'meter_number' => 'MT-E2E-OLD',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $march = $this->bill($consumer, 'E2E-MARCH', 300.00, '2026-03-01', '2026-05-04');

        // Nothing is overdue yet.
        $this->assertFalse(app(ConsumerBalanceService::class)->isOverdue($march));
        $this->assertSame(0, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));

        Carbon::setTestNow(Carbon::parse('2026-05-07 08:00:00'));

        // 2. Past the due date plus the three day grace period.
        $this->assertTrue(app(ConsumerBalanceService::class)->isOverdue($march));

        $this->artisan('billing:monitor-overdue')->assertSuccessful();

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'overdue_bill_'.$march->id,
            'status' => 'sent',
        ]);

        // Two more months become overdue and the account qualifies.
        Carbon::setTestNow(Carbon::parse('2026-06-07 08:00:00'));

        $april = $this->bill($consumer, 'E2E-APRIL', 300.00, '2026-04-01', '2026-06-04');
        $may = $this->bill($consumer, 'E2E-MAY', 300.00, '2026-05-01', '2026-06-04');

        $this->artisan('billing:monitor-overdue')->assertSuccessful();

        $this->assertSame(3, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $march->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $april->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $may->refresh()->notice_level);

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'disconnection_notice_'.$may->id,
            'status' => 'sent',
        ]);

        // Re-running never re-sends either notice or creates a duplicate.
        $this->artisan('billing:monitor-overdue')->assertSuccessful();

        $this->assertSame(1, ConsumerNotificationLog::query()
            ->where('event', 'disconnection_notice_'.$may->id)
            ->count());

        $this->assertDatabaseCount('disconnection_notices', 1);

        // 3. The administrator confirms the physical disconnection only after
        // the three-day payment window has closed.
        Carbon::setTestNow(Carbon::parse('2026-06-11 08:00:00'));

        $this->actingAs($admin)
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'disconnection_notice_'.$may->id,
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 900.00');

        $this->post(route('payments.store'), [])->assertForbidden();
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id,
            'reference_number' => 'OFFICE-E2E-1',
            'bill_id' => $may->id,
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
            'status' => 'Pending',
        ]);
        $this->assertSame('Pending', $payment->status);

        // Still locked: the payment is only submitted, not verified, so the balance
        // stands and no reconnection can be requested.
        $user->unsetRelation('consumer');

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertRedirect(route('account.inactive'));

        $this->assertNull($consumer->refresh()->full_payment_verified_at);

        // 4. The administrator verifies the payment.
        $this->actingAs($admin)
            ->patch(route('payments.verify', $payment))
            ->assertRedirect(route('payments.receipt', $payment));

        $consumer->refresh();

        $this->assertNotNull($consumer->full_payment_verified_at);
        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertSame('For Reconnection', $consumer->status);
        $this->assertNull($march->refresh()->notice_level);

        // All three months are settled and the history survives.
        foreach ([$march, $april, $may] as $bill) {
            $bill->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
            $this->assertSame('Paid', $bill->status);
            $this->assertEquals(0.00, $bill->balance_due);
        }

        Storage::fake('local');
        $user->unsetRelation('consumer');

        $this->actingAs($user)
            ->post(route('billing.requests.store'), [
                'request_type' => 'Reconnection',
                'information' => 'Confirmation email received from the association on 8 June.',
                'proof' => UploadedFile::fake()->create('confirmation.png', 30, 'image/png'),
            ])
            ->assertForbidden();
        Storage::disk('local')->put('proof/existing-confirmation.png', 'Existing confirmation proof');
        $request = ServiceRequest::query()->create([
            'consumer_id' => $consumer->id, 'request_type' => 'Reconnection', 'status' => 'Pending',
            'requested_at' => now(), 'proof_path' => 'proof/existing-confirmation.png',
        ]);

        $this->assertSame('Pending', $request->status);
        $this->assertNotNull($request->proof_path);
        Storage::disk('local')->assertExists($request->proof_path);

        // Supply is not restored by the request.
        $this->assertSame('For Reconnection', $consumer->refresh()->status);

        // 6. The administrator reviews the proof and approves.
        $this->actingAs($admin)
            ->get(route('billing.requests.proof', $request))
            ->assertOk();

        $this->actingAs($admin)
            ->patch(route('billing.requests.update', $request), [
                'status' => 'Approved',
                'admin_notes' => 'Approved for scheduled restoration.',
            ])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Approved', $request->refresh()->status);
        $this->assertSame('For Reconnection', $consumer->refresh()->status, 'Approval alone does not restore supply.');

        // 7. The association issues a new meter.
        $this->actingAs($admin)
            ->patch(route('consumers.assign-meter', $consumer), [
                'meter_number' => 'MT-E2E-NEW',
                'reason' => 'New meter issued during reconnection.',
            ])
            ->assertRedirect(route('consumers.index'))
            ->assertSessionHasNoErrors();

        $consumer->refresh();
        $this->assertSame('MT-E2E-NEW', $consumer->meter_number);

        // The old meter is retired but kept, with its history.
        $history = MeterAssignment::query()
            ->where('consumer_id', $consumer->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $history);
        $this->assertSame('MT-E2E-OLD', $history->first()->meter_number);
        $this->assertNotNull($history->first()->retired_at);
        $this->assertTrue($history->last()->isActive());

        $this->actingAs($admin)->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => today()->toDateString(),
            'installation_scheduled_at' => now('Asia/Manila')->addMinute()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $record = $consumer->reconnections()->sole();
        $this->post(route('consumers.reconnection-complete', [$consumer, $record]))->assertSessionHasNoErrors();

        $user->unsetRelation('consumer');

        $this->actingAs($user)
            ->get(route('connection.status'))
            ->assertOk()
            ->assertSee('MT-E2E-NEW')
            ->assertSee('Meter History')
            ->assertSee('MT-E2E-OLD');

        // 9. The meter reader uses the new meter from now on.
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:00:00'));

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => 'MT-E2E-OLD',
                'reading_date' => '2026-07-15',
                'current_reading' => 20,
                'status' => 'Encoded',
            ])
            ->assertSessionHasErrors('meter_number');

        $this->actingAs($reader)
            ->post(route('meter-readings.store'), [
                'meter_number' => 'MT-E2E-NEW',
                'reading_date' => '2026-07-15',
                'current_reading' => 20,
                'status' => 'Encoded',
            ])
            ->assertRedirect(route('meter-readings.index'))
            ->assertSessionHasNoErrors();

        $reading = MeterReading::query()->where('consumer_id', $consumer->id)->firstOrFail();
        $this->assertSame('MT-E2E-NEW', $reading->meter_number);

        // 10. Billing can be generated from that reading against the new meter.
        $this->actingAs($admin)
            ->post(route('billing.generate'), [
                'billing_period' => '2026-07-01',
                'due_date' => '2026-07-31',
                'base_charge' => 100.00,
                'member_unit_rate' => 12.50,
                'non_member_unit_rate' => 15.00,
            ])
            ->assertRedirect(route('billing.index', ['period' => '2026-07']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bills', [
            'consumer_id' => $consumer->id,
            'meter_reading_id' => $reading->id,
        ]);

        $julyBill = Bill::query()
            ->where('consumer_id', $consumer->id)
            ->where('meter_reading_id', $reading->id)
            ->firstOrFail();

        // Nothing was carried forward: the arrears were fully settled.
        $this->assertEquals(0.00, (float) $julyBill->arrears_amount);
        $this->assertGreaterThan(0, (float) $julyBill->amount);

        // The March, April, and May payment history is untouched by the
        // reconnection: the verified payment still covers exactly what it
        // covered before.
        $this->assertEquals(
            900.00,
            round((float) Payment::query()
                ->where('consumer_id', $consumer->id)
                ->where('status', 'Verified')
                ->sum('amount'), 2),
        );

        $this->assertSame(3, Bill::query()
            ->where('consumer_id', $consumer->id)
            ->whereIn(DB::raw('DATE(billing_period)'), ['2026-03-01', '2026-04-01', '2026-05-01'])
            ->count());
    }

    private function bill(Consumer $consumer, string $prn, float $amount, string $period, string $dueDate): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => $dueDate,
            'prn' => $prn,
            'amount' => $amount,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
    }
}
