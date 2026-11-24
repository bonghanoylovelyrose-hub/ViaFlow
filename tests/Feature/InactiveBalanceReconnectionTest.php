<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerReconnectionService;
use App\Services\ConsumerStatusService;
use App\Services\ReconnectionFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InactiveBalanceReconnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 02:00:00');
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(): Consumer
    {
        $user = User::factory()->create(['role' => 'consumer']);

        return Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => 'Balance Account '.$user->id, 'email' => $user->email,
            'account_number' => 'ZERO-'.$user->id, 'meter_number' => 'ZERO-METER-'.$user->id,
            'status' => 'Disconnected', 'membership_type' => 'Non-Member',
        ]);
    }

    private function bill(Consumer $consumer, string $prn, float $amount, string $period = '2026-10-01'): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id, 'prn' => $prn, 'amount' => $amount, 'status' => 'Unpaid',
            'billing_period' => $period, 'due_date' => Carbon::parse($period)->endOfMonth(),
        ]);
    }

    private function payment(Bill $bill, float $amount, string $status = 'Verified'): Payment
    {
        return Payment::query()->create([
            'consumer_id' => $bill->consumer_id, 'bill_id' => $bill->id,
            'reference_number' => 'ZERO-PAY-'.(Payment::query()->count() + 1),
            'amount' => $amount, 'status' => $status, 'payment_date' => today(),
            'payment_method' => 'Cash', 'payment_channel' => 'Counter',
        ]);
    }

    private function activation(): array
    {
        return ['confirm_full_payment' => 1, 'installation_scheduled_at' => '2026-11-05T10:00'];
    }

    public function test_zero_balance_enables_reconnect_and_positive_balance_disables_it_without_fee_displays(): void
    {
        $zero = $this->account();
        $owing = $this->account();
        $this->bill($owing, 'WATER-100', 100.25);
        $response = $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk()
            ->assertSee('Outstanding Balance')->assertSee('100.25')->assertSee('Activate Account')
            ->assertSee('Reconnection Date & Time')
            ->assertSee('I confirm that the outstanding balance is zero and approve the reconnection schedule.')
            ->assertDontSee('id="reconnectDate"', false)->assertDontSee('Reconnection Fee')->assertDontSee('name="reconnection_fee"', false)
            ->assertDontSee('Record and verify the full required payment in Payments')
            ->assertDontSee('Record / verify payment in Payments');
        preg_match('/<button[^>]*data-reconnect-account="'.preg_quote($zero->account_number, '/').'"[^>]*>/s', $response->getContent(), $zeroButton);
        preg_match('/<button[^>]*data-reconnect-account="'.preg_quote($owing->account_number, '/').'"[^>]*>/s', $response->getContent(), $owingButton);
        $this->assertStringNotContainsString('disabled', $zeroButton[0]);
        $this->assertStringContainsString('disabled', $owingButton[0]);
        $response->assertDontSee('Activate this account and mark the water service Active and Connected?');
    }

    public function test_zero_balance_without_payment_or_fee_assessment_can_activate_and_moves_tables(): void
    {
        $consumer = $this->account();
        $this->assertTrue(app(ConsumerReconnectionService::class)->eligibility($consumer)['eligible']);
        $this->post(route('consumers.reconnect', $consumer), $this->activation() + ['reconnection_date' => '2026-11-07'])
            ->assertSessionHasNoErrors();
        $consumer->refresh();
        $this->assertSame('Active', $consumer->status);
        $this->assertSame('Pending Reconnection', app(ConsumerStatusService::class)->present($consumer)['sub_label']);
        $this->assertSame('2026-11-05', $consumer->reconnection_date->toDateString());
        $this->assertSame('2026-11-05 02:00:00', $consumer->reconnection_scheduled_at->toDateTimeString());
        $this->assertNull($consumer->installation_date);
        $this->assertNull($consumer->installation_time);
        $this->assertDatabaseCount('payments', 0);
        $reconnection = $consumer->reconnections()->sole();
        $this->assertSame('2026-11-05', $reconnection->reconnection_date->toDateString());
        $this->assertSame('2026-11-05 02:00:00', $reconnection->installation_scheduled_at->toDateTimeString());
        $this->assertSame(0.0, (float) $reconnection->reconnection_fee);
        $inactive = $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk();
        $this->assertNotContains($consumer->id, $inactive->viewData('consumers')->pluck('id')->all());
        $active = $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertContains($consumer->id, $active->viewData('consumers')->pluck('id')->all());
    }

    public function test_new_debt_after_opening_the_form_prevents_activation(): void
    {
        $consumer = $this->account();
        $this->get(route('consumers.index', ['account_category' => 'inactive']))->assertOk();
        $this->bill($consumer, 'NEW-DEBT', 50);
        $this->post(route('consumers.reconnect', $consumer), $this->activation())->assertSessionHasErrors('reconnection');
        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->assertDatabaseCount('consumer_reconnections', 0);
    }

    public function test_pending_payment_does_not_clear_debt_but_verified_payment_does(): void
    {
        $consumer = $this->account();
        $bill = $this->bill($consumer, 'PENDING-BILL', 250);
        $payment = $this->payment($bill, 250, 'Pending');
        $this->assertFalse(app(ConsumerReconnectionService::class)->eligibility($consumer)['eligible']);
        $payment->update(['status' => 'Verified']);
        $this->assertTrue(app(ConsumerReconnectionService::class)->eligibility($consumer)['eligible']);
    }

    public function test_fee_waiver_preserves_original_bills_and_payments_and_does_not_waive_water_debt(): void
    {
        $consumer = $this->account();
        $water = $this->bill($consumer, 'WATER-ORIGINAL', 300);
        $fee = $this->bill($consumer, 'RECON-FEE-LEGACY', 100);
        $paid = $this->payment($fee, 25);
        $consumer->update(['reconnection_fee' => 100, 'reconnection_fee_bill_id' => $fee->id]);
        app(ReconnectionFeeService::class)->removeAllCharges();
        $this->assertSame('100.00', $fee->refresh()->amount);
        $this->assertSame('75.00', $fee->waived_reconnection_fee_amount);
        $this->assertSame(0.0, $fee->balance_due);
        $this->assertSame('25.00', $paid->refresh()->amount);
        $this->assertSame(300.0, $water->refresh()->balance_due);
        $this->assertSame(300.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        app(ReconnectionFeeService::class)->removeAllCharges();
        $this->assertSame('75.00', $fee->refresh()->waived_reconnection_fee_amount);
        $this->assertDatabaseCount('bills', 2);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_carried_fee_is_waived_once_on_the_bill_holding_the_debt_and_later_payments_remain_correct(): void
    {
        $consumer = $this->account();
        $fee = $this->bill($consumer, 'RECON-FEE-CARRIED', 100, '2026-08-01');
        $water = $this->bill($consumer, 'WATER-CARRIED', 300, '2026-09-01');
        $latest = $this->bill($consumer, 'CONSOLIDATED', 400);
        $fee->update(['status' => 'Paid', 'carried_forward_to_bill_id' => $latest->id]);
        $water->update(['status' => 'Paid', 'carried_forward_to_bill_id' => $latest->id]);
        $latest->update(['arrears_amount' => 400]);
        $this->payment($latest, 50);
        app(ReconnectionFeeService::class)->removeAllCharges();
        $this->assertSame('50.00', $latest->refresh()->waived_reconnection_fee_amount);
        $this->assertSame(300.0, $latest->balance_due);
        $this->payment($latest, 100);
        app(ReconnectionFeeService::class)->removeAllCharges();
        $this->assertSame('50.00', $latest->refresh()->waived_reconnection_fee_amount);
        $this->assertSame(200.0, $latest->balance_due);
        $this->assertSame(200.0, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
    }

    public function test_previously_paid_fee_is_not_refunded_or_removed_from_payment_history(): void
    {
        $consumer = $this->account();
        $fee = $this->bill($consumer, 'RECON-FEE-PAID', 100);
        $paid = $this->payment($fee, 100);
        app(ReconnectionFeeService::class)->removeAllCharges();
        $this->assertSame('0.00', $fee->refresh()->waived_reconnection_fee_amount);
        $this->assertSame('100.00', $fee->amount);
        $this->assertSame('100.00', $paid->refresh()->amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertTrue(app(ConsumerReconnectionService::class)->eligibility($consumer->fresh())['eligible']);
    }
}
