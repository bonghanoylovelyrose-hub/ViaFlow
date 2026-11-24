<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\DisconnectionNoticeService;
use App\Services\OverdueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DisconnectionMonitoringRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_carried_forward_debt_retains_its_original_months_without_counting_money_twice(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('disconnectionNotices')->total());
        $this->assertSame(900.0, $response->viewData('disconnectionNotices')->first()['total_outstanding']);
        $this->assertSame(3, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame(900.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'bill_id' => $latest->id,
            'months_unpaid' => 3,
            'outstanding_amount' => 900,
        ]);

        $notice = DisconnectionNotice::query()->sole();
        $this->assertSame('2026-09-13 09:00:00', $notice->deadline_at->toDateTimeString());

        $this->bill($consumer, '2026-08-01');
        app(OverdueMonitoringService::class)->evaluate($consumer);
        $this->assertDatabaseCount('disconnection_notices', 1);
        $this->assertSame('2026-09-13 09:00:00', $notice->refresh()->deadline_at->toDateTimeString());
    }

    public function test_generating_the_next_cycle_issues_one_notice_for_two_carried_overdue_periods(): void
    {
        $consumer = $this->consumer();
        $consumer->update(['email' => 'cycle@example.test']);
        $admin = User::factory()->create(['role' => 'admin']);
        $first = $this->bill($consumer, '2026-07-01');
        $second = $this->bill($consumer, '2026-08-01');
        $consumer->syncAccountStanding();
        $this->assertDatabaseCount('disconnection_notices', 0);

        $reading = MeterReading::create(['consumer_id' => $consumer->id, 'reader_id' => $admin->id,
            'reading_date' => today(), 'previous_reading' => 0, 'current_reading' => 5, 'consumption' => 5, 'status' => 'Encoded']);
        $payload = ['billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'base_charge' => 150, 'member_unit_rate' => 18, 'non_member_unit_rate' => 20];
        $this->actingAs($admin)->post(route('billing.generate'), $payload)->assertSessionHasNoErrors();

        $current = $reading->bill()->sole();
        $notice = DisconnectionNotice::query()->sole();
        $this->assertSame($current->id, $notice->bill_id);
        $this->assertSame(2, $notice->months_unpaid);
        $this->assertSame($current->id, $first->fresh()->carried_forward_to_bill_id);
        $this->assertSame($current->id, $second->fresh()->carried_forward_to_bill_id);
        $this->assertSame('2026-09-13 09:00:00', $notice->deadline_at->toDateTimeString());
        $this->assertSame(850.0, $current->balance_due);
        $this->assertDatabaseHas('consumer_notification_logs', ['event' => 'disconnection_notice_'.$current->id, 'status' => 'sent']);
        $this->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => 'grace']))->assertOk()
            ->assertSee('data-record-status="Within Grace Period"', false)->assertSee('Water Billing Overdue');

        Carbon::setTestNow($notice->deadline_at);
        $this->post(route('billing.generate'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('disconnection_notices', 1);
        $this->assertSame(1, ConsumerNotificationLog::query()->where('event', 'disconnection_notice_'.$current->id)->count());
        $this->get(route('consumers.index', ['account_category' => 'active', 'notice_status' => 'ready']))->assertOk()
            ->assertSee('data-record-status="Ready for Disconnection"', false)->assertSee('Water Billing Overdue');
        $this->assertSame('Active', $consumer->fresh()->status);
    }

    public function test_verified_payments_on_a_carried_balance_settle_the_oldest_month_first(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $this->payment($latest, 300, 'Verified');

        $consumer->syncAccountStanding();

        $this->assertSame(2, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame(600.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertDatabaseCount('disconnection_notices', 0);
    }

    public function test_future_issued_bill_cannot_trigger_a_notice_for_two_overdue_periods(): void
    {
        Mail::fake();
        $consumer = $this->consumer();
        $reader = User::factory()->create(['role' => 'meter_reader']);
        $this->bill($consumer, '2026-07-01');
        $this->bill($consumer, '2026-08-01');

        $reading = MeterReading::create([
            'consumer_id' => $consumer->id,
            'reader_id' => $reader->id,
            'reading_date' => '2026-09-11',
            'previous_reading' => 0,
            'current_reading' => 5,
            'consumption' => 5,
            'status' => 'Encoded',
        ]);
        $future = $this->bill($consumer, '2026-09-01');
        $future->forceFill(['meter_reading_id' => $reading->id, 'created_at' => '2026-09-11 09:00:00'])->save();

        $balances = app(ConsumerBalanceService::class);
        $consumer->syncAccountStanding();
        $this->assertSame(2, $balances->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertFalse($balances->isOverdue($future));
        $this->assertDatabaseCount('disconnection_notices', 0);

        Carbon::setTestNow('2026-09-11 09:00:00');
        $consumer->syncAccountStanding();
        $notice = DisconnectionNotice::query()->sole();
        $this->assertSame($future->id, $notice->bill_id);
        $this->assertSame('2026-09-14 09:00:00', $notice->deadline_at->toDateTimeString());
        $this->assertFalse($balances->isOverdue($future));
    }

    public function test_pending_payments_do_not_clear_carried_forward_months_or_active_notices(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $payment = $this->payment($latest, 900, 'Pending');
        $consumer->syncAccountStanding();

        $this->assertSame(3, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame(DisconnectionNotice::STATUS_ACTIVE, DisconnectionNotice::query()->sole()->status);

        $payment->update(['status' => 'Verified']);
        $latest->syncPaymentStatus();

        $this->assertSame(0, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, DisconnectionNotice::query()->sole()->status);
        $this->assertNotNull(DisconnectionNotice::query()->sole()->settled_at);
    }

    public function test_a_missing_billing_month_breaks_the_unpaid_streak(): void
    {
        $consumer = $this->consumer();
        foreach (['2026-03-01', '2026-05-01', '2026-06-01'] as $period) {
            $this->bill($consumer, $period);
        }

        $consumer->syncAccountStanding();

        $this->assertSame(2, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertDatabaseCount('disconnection_notices', 0);
    }

    public function test_multiple_bills_in_one_month_do_not_count_as_multiple_months(): void
    {
        $consumer = $this->consumer();
        foreach (['2026-05-01', '2026-05-15', '2026-06-01'] as $period) {
            $this->bill($consumer, $period);
        }

        $consumer->syncAccountStanding();

        $this->assertSame(2, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertDatabaseCount('disconnection_notices', 0);
    }

    public function test_stale_consumer_instances_cannot_raise_new_notices_after_physical_disconnection(): void
    {
        $consumer = $this->consumer();
        $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $stale = $consumer->fresh();
        Carbon::setTestNow('2026-09-14 09:00:00');
        app(DisconnectionNoticeService::class)->confirmDisconnection($consumer, User::factory()->create(['role' => 'admin']));
        $this->bill($consumer, '2026-08-01');

        $result = app(OverdueMonitoringService::class)->evaluate($stale);

        $this->assertFalse($result['flagged']);
        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->assertDatabaseCount('disconnection_notices', 1);
    }

    public function test_unverified_accounts_never_receive_disconnection_notices(): void
    {
        $consumer = $this->consumer();
        $consumer->update(['status' => 'Pending Verification']);
        $this->carriedBills($consumer);

        $consumer->syncAccountStanding();

        $this->assertDatabaseCount('disconnection_notices', 0);
        $this->assertSame('Pending Verification', $consumer->refresh()->status);
    }

    public function test_confirmation_closes_a_paid_notice_even_before_the_deadline(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $this->payment($latest, 900, 'Verified');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('consumers.confirm-disconnection', $consumer))
            ->assertSessionHasErrors('disconnection');

        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, DisconnectionNotice::query()->sole()->status);
        $this->assertFalse($consumer->bills()->whereNotNull('notice_level')->exists());
    }

    public function test_sweeps_close_paid_notices_even_when_every_bill_is_already_marked_paid(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $this->payment($latest, 900, 'Verified');
        $latest->update(['status' => 'Paid']);

        $this->artisan('billing:monitor-overdue')->assertSuccessful();

        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, DisconnectionNotice::query()->sole()->status);
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_delayed_notice_delivery_preserves_three_full_days_to_pay_and_retries_do_not_extend_it(): void
    {
        Mail::fake();
        $consumer = $this->consumer();
        $consumer->update(['email' => 'records@example.com']);
        $notice = app(DisconnectionNoticeService::class)->raiseNotice($consumer, 3, $this->carriedBills($consumer));
        Carbon::setTestNow('2026-09-12 11:00:00');

        $notices = app(DisconnectionNoticeService::class);
        $this->assertTrue($notices->sendDisconnectionNotice($consumer));
        $this->assertSame('2026-09-15 11:00:00', $notice->refresh()->deadline_at->toDateTimeString());

        Carbon::setTestNow('2026-09-13 11:00:00');
        $this->assertFalse($notices->sendDisconnectionNotice($consumer));
        $this->assertSame('2026-09-15 11:00:00', $notice->refresh()->deadline_at->toDateTimeString());
        $this->assertSame(1, ConsumerNotificationLog::query()->where('event', 'like', 'disconnection_notice_%')->count());
    }

    public function test_failed_delivery_keeps_the_notice_active_and_records_the_failure(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $consumer = $this->consumer();
        $consumer->update(['email' => 'records@example.com']);
        $notice = app(DisconnectionNoticeService::class)->raiseNotice($consumer, 3, $this->carriedBills($consumer));
        Carbon::setTestNow('2026-09-12 11:00:00');

        $this->assertFalse(app(DisconnectionNoticeService::class)->sendDisconnectionNotice($consumer));

        $this->assertSame(DisconnectionNotice::STATUS_ACTIVE, $notice->refresh()->status);
        $this->assertSame('2026-09-13 09:00:00', $notice->deadline_at->toDateTimeString());
        $this->assertDatabaseHas('consumer_notification_logs', [
            'consumer_id' => $consumer->id,
            'event' => 'disconnection_notice_'.$notice->bill_id,
            'status' => ConsumerNotificationLog::STATUS_FAILED,
        ]);
    }

    public function test_active_accounts_page_closes_a_paid_notice_and_removes_it_from_notice_table(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $this->payment($latest, 900, 'Verified');
        $latest->update(['status' => 'Paid']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());
        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, DisconnectionNotice::query()->sole()->status);
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_full_verified_payment_settles_carried_forward_months_and_clears_the_active_notice(): void
    {
        $consumer = $this->consumer();
        $latest = $this->carriedBills($consumer);
        $consumer->syncAccountStanding();
        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->payment($latest, 900, 'Verified');

        app(\App\Services\PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $admin);

        $bills = Bill::query()->where('consumer_id', $consumer->id)->orderBy('billing_period')->get();
        $this->assertSame(['Paid', 'Paid', 'Paid'], $bills->pluck('status')->all());
        $this->assertSame(0.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, DisconnectionNotice::query()->sole()->status);

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseCount('disconnection_notices', 1);
    }

    private function consumer(): Consumer
    {
        return Consumer::query()->create([
            'full_name' => 'Billing Records Consumer',
            'account_number' => 'RECORDS-001',
            'meter_number' => 'RECORDS-METER-001',
            'membership_type' => 'Non-Member',
            'status' => 'Active',
        ]);
    }

    private function bill(Consumer $consumer, string $period, float $amount = 300): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => Carbon::parse($period)->endOfMonth()->toDateString(),
            'prn' => 'RECORDS-'.$period,
            'amount' => $amount,
            'status' => 'Unpaid',
        ]);
    }

    private function carriedBills(Consumer $consumer): Bill
    {
        $may = $this->bill($consumer, '2026-05-01');
        $june = $this->bill($consumer, '2026-06-01', 600);
        $july = $this->bill($consumer, '2026-07-01', 900);
        $may->update(['carried_forward_to_bill_id' => $june->id, 'status' => 'Paid']);
        $june->update(['arrears_amount' => 300, 'carried_forward_to_bill_id' => $july->id, 'status' => 'Paid']);
        $july->update(['arrears_amount' => 600]);

        return $july;
    }

    private function payment(Bill $bill, float $amount, string $status): Payment
    {
        return Payment::query()->create([
            'consumer_id' => $bill->consumer_id,
            'bill_id' => $bill->id,
            'reference_number' => 'RECORDS-PAYMENT-'.$bill->id,
            'amount' => $amount,
            'payment_date' => today(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => $status,
        ]);
    }
}
