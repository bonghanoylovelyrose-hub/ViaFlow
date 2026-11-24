<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\OverdueMonitoringService;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Customer notices for overdue bills and disconnection.
 *
 * Every notice is keyed on the event that triggered it, so the same message is
 * never delivered twice for the same overdue event.
 */
class DisconnectionNoticeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Notification::fake();

        parent::tearDown();
    }

    public function test_a_bill_past_its_due_date_sends_one_overdue_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $consumer = $this->createConsumer();
        $bill = $this->createBill($consumer, 'NB-MAY', 300.00, '2026-05-01', '2026-05-04');

        app(DisconnectionNoticeService::class)->sendOverdueReminder($consumer->fresh(), $bill);

        $log = ConsumerNotificationLog::query()->where('event', 'overdue_bill_'.$bill->id)->first();

        $this->assertNotNull($log, 'An overdue reminder should be logged for the bill.');
        $this->assertSame(ConsumerNotificationLog::STATUS_SENT, $log->status);
        $this->assertSame($consumer->email, $log->recipient);

        // Running the sweep again must not resend it.
        app(DisconnectionNoticeService::class)->sendOverdueReminder($consumer->fresh(), $bill);

        $this->assertSame(1, ConsumerNotificationLog::query()
            ->where('event', 'overdue_bill_'.$bill->id)
            ->count());
    }

    public function test_no_overdue_reminder_is_sent_before_the_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 08:00:00'));

        $consumer = $this->createConsumer();
        $bill = $this->createBill($consumer, 'NB-FUTURE', 300.00, '2026-06-01', '2026-06-04');

        app(DisconnectionNoticeService::class)->sendOverdueReminder($consumer->fresh(), $bill);

        $this->assertSame(0, ConsumerNotificationLog::query()
            ->where('event', 'overdue_bill_'.$bill->id)
            ->count());
    }

    public function test_the_sweep_emails_once_per_overdue_bill_and_only_when_past_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $consumer = $this->createConsumer();

        $pastDue = $this->createBill($consumer, 'SWEEP-PAST', 300.00, '2026-05-01', '2026-05-04');
        $stillCurrent = $this->createBill($consumer, 'SWEEP-CURRENT', 300.00, '2026-06-01', '2026-06-30');

        $monitoring = app(OverdueMonitoringService::class);

        $monitoring->evaluate($consumer->fresh(), notify: true);
        $monitoring->evaluate($consumer->fresh(), notify: true);

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'overdue_bill_'.$pastDue->id,
            'status' => 'sent',
        ]);

        $this->assertSame(0, ConsumerNotificationLog::query()
            ->where('event', 'overdue_bill_'.$stillCurrent->id)
            ->count());
    }

    public function test_qualifying_for_disconnection_sends_one_formal_notice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $consumer = $this->createConsumer();
        $april = $this->createBill($consumer, 'DISC-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $may = $this->createBill($consumer, 'DISC-MAY', 300.00, '2026-05-01', '2026-05-04');
        $june = $this->createBill($consumer, 'DISC-JUNE', 300.00, '2026-06-01', '2026-06-04');

        $monitoring = app(OverdueMonitoringService::class);

        $first = $monitoring->evaluate($consumer->fresh(), notify: true);

        $this->assertTrue($first['flagged']);
        $this->assertSame('Active', $consumer->refresh()->status);

        $event = 'disconnection_notice_'.$june->id;

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => $event,
            'status' => 'sent',
        ]);

        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $april->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $may->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $june->refresh()->notice_level);

        // Re-running the sweep never repeats the same notice.
        $monitoring->evaluate($consumer->fresh(), notify: true);

        $this->assertSame(1, ConsumerNotificationLog::query()->where('event', $event)->count());
    }

    public function test_demo_arrears_stay_in_history_and_do_not_trigger_collection_actions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));
        $consumer = $this->createConsumer();
        $demo = $this->createBill($consumer, 'DEMO-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $demo->forceFill(['is_demo' => true])->save();
        $may = $this->createBill($consumer, 'REAL-MAY', 300.00, '2026-05-01', '2026-05-04');
        $june = $this->createBill($consumer, 'REAL-JUNE', 300.00, '2026-06-01', '2026-06-04');

        $result = app(OverdueMonitoringService::class)->evaluate($consumer, notify: true);
        $balances = app(ConsumerBalanceService::class);

        $this->assertSame(2, $result['months_unpaid']);
        $this->assertFalse($result['flagged']);
        $this->assertSame(900.0, $balances->recordedOutstandingFor($consumer->id));
        $this->assertSame(600.0, $balances->totalOutstandingFor($consumer->id));
        $this->assertSame([$may->id, $june->id], $balances->outstandingBefore($consumer->id, '2026-07-01')->pluck('id')->all());
        $this->assertDatabaseMissing('consumer_notification_logs', ['event' => 'overdue_bill_'.$demo->id]);
        $this->assertDatabaseMissing('disconnection_notices', ['consumer_id' => $consumer->id]);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'overdue_bill_'.$june->id, 'status' => 'sent']);
    }

    public function test_the_formal_notice_is_not_sent_while_inside_the_grace_period(): void
    {
        // Due 4 June, so the account is not eligible until 7 June.
        Carbon::setTestNow(Carbon::parse('2026-06-06 08:00:00'));

        $consumer = $this->createConsumer();
        $this->createBill($consumer, 'GRACE-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $this->createBill($consumer, 'GRACE-MAY', 300.00, '2026-05-01', '2026-05-04');
        $this->createBill($consumer, 'GRACE-JUNE', 300.00, '2026-06-01', '2026-06-04');

        $result = app(OverdueMonitoringService::class)->evaluate($consumer->fresh(), notify: true);

        $this->assertFalse($result['flagged']);
        $this->assertSame(0, ConsumerNotificationLog::query()
            ->where('event', 'like', 'disconnection_notice_%')
            ->count());
    }

    public function test_the_monitor_command_sends_notices_for_every_unpaid_account(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $consumer = $this->createConsumer();
        $this->createBill($consumer, 'CMD-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $this->createBill($consumer, 'CMD-MAY', 300.00, '2026-05-01', '2026-05-04');
        $this->createBill($consumer, 'CMD-JUNE', 300.00, '2026-06-01', '2026-06-04');

        $this->artisan('billing:monitor-overdue')
            ->expectsOutputToContain('Checked 1 consumer(s); 1 flagged for disconnection.')
            ->assertSuccessful();

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'status' => 'sent',
        ]);

        // Running it again is safe.
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
    }

    public function test_eligibility_is_only_granted_when_an_administrator_verifies_the_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $notices = app(DisconnectionNoticeService::class);

        $consumer = $this->createConsumer();
        $this->createBill($consumer, 'ELIG-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $this->createBill($consumer, 'ELIG-MAY', 300.00, '2026-05-01', '2026-05-04');
        $this->createBill($consumer, 'ELIG-JUNE', 300.00, '2026-06-01', '2026-06-04');
        $consumer->syncAccountStanding();

        $this->assertSame('Active', $consumer->refresh()->status);

        // The administrator confirms the physical disconnection after the
        // notice's three-day payment window has elapsed.
        Carbon::setTestNow(Carbon::parse('2026-06-14 08:00:00'));
        $notices->confirmDisconnection($consumer->fresh(), $admin);

        // Still owing: nothing is granted.
        $this->assertFalse($notices->grantEligibilityIfFullySettled($consumer->fresh(), 900.00));
        $this->assertNull($consumer->refresh()->full_payment_verified_at);

        // The consumer pays in full.
        $bill = Bill::query()->where('consumer_id', $consumer->id)->orderByDesc('billing_period')->first();
        $payment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-ELIG-1',
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        app(PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $admin);

        // Balance settled, but allocating the money is not the same as an
        // administrator approving it: eligibility is still locked.
        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertNull($consumer->refresh()->full_payment_verified_at);

        // The administrator verifies it.
        $this->assertTrue($notices->grantEligibilityIfFullySettled($consumer->fresh(), 900.00));
        $this->assertNotNull($consumer->refresh()->full_payment_verified_at);

        // Approving twice does not grant or email again.
        $this->assertFalse($notices->grantEligibilityIfFullySettled($consumer->fresh(), 900.00));

        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'status' => 'sent',
        ]);
    }

    public function test_verifying_a_payment_through_the_admin_endpoint_grants_eligibility(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'ENDPOINT-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $this->createBill($consumer, 'ENDPOINT-MAY', 300.00, '2026-05-01', '2026-05-04');
        $bill = $this->createBill($consumer, 'ENDPOINT-JUNE', 300.00, '2026-06-01', '2026-06-04');
        $consumer->syncAccountStanding();

        $this->assertSame('Active', $consumer->refresh()->status);

        // A notice alone does not disconnect the account. The administrator
        // confirms the physical disconnection after the payment window.
        Carbon::setTestNow(Carbon::parse('2026-06-14 08:00:00'));

        $this->actingAs($admin)
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Disconnected', $consumer->refresh()->status);

        $this->actingAs($consumer->user)->post(route('payments.store'), [])->assertForbidden();
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id,
            'reference_number' => 'OFFICE-ENDPOINT-1',
            'bill_id' => $bill->id,
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_channel' => 'Counter',
            'payment_method' => 'Cash',
            'status' => 'Pending',
        ]);

        // Pending: not verified yet, so no eligibility.
        $this->assertNull($consumer->refresh()->full_payment_verified_at);

        $this->actingAs($admin)
            ->patch(route('payments.verify', $payment))
            ->assertRedirect(route('payments.receipt', $payment));

        $consumer->refresh();

        $this->assertNotNull($consumer->full_payment_verified_at);
        $this->assertSame('For Reconnection', $consumer->status);
        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
    }

    private function createConsumer(array $overrides = []): Consumer
    {
        static $sequence = 0;
        $sequence++;

        $user = User::factory()->create([
            'role' => 'consumer',
            'email' => "notice.consumer+{$sequence}@example.com",
        ]);

        return Consumer::query()->create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Notice Consumer '.$sequence,
            'email' => "notice.consumer+{$sequence}@example.com",
            'account_number' => 'NB-'.$sequence,
            'meter_number' => 'NB-MTR-'.$sequence,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $overrides));
    }

    private function createBill(Consumer $consumer, string $prn, float $amount, string $period, string $dueDate): Bill
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
