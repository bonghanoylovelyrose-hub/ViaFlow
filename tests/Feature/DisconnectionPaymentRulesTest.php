<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The disconnection rule is three consecutive unpaid billing months, counted
 * only once each bill has passed its due date plus the three day grace period.
 *
 * Amount alone never raises a notice, and settling the balance always lifts it.
 */
class DisconnectionPaymentRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_balance_below_notice_threshold_allows_partial_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-999', 999.99, '2026-08-01');

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($bill, 100.00))
            ->assertRedirect();

        $bill->refresh()->loadSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');

        $this->assertEquals(899.99, $bill->balance_due);
        $this->assertSame('Paid with Balance', $bill->status);
        $this->assertNull($bill->notice_level);

        $this->actingAs($consumerUser)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Payment Status: <strong>Paid with Balance</strong>', false);
    }

    public function test_single_large_unpaid_bill_does_not_raise_a_notice(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-800-UNPAID', 800.00, '2026-08-01', '2026-08-31');
        $bill->syncPaymentStatus();

        $this->assertNull($bill->refresh()->notice_level);
        // A single unpaid bill is only overdue, never disconnected.
        $this->assertSame('Overdue', $bill->status);

        $this->actingAs($consumerUser)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Disconnection Notice: <strong>None</strong>', false)
            ->assertSee('Payment Status: <strong>Unpaid</strong>', false)
            ->assertSee('Total Outstanding Balance: <strong>&#8369;800.00</strong>', false)
            ->assertSee('Partial payment is allowed.')
            ->assertDontSee('Required Payment Amount: <strong>&#8369;800.00</strong>', false);
    }

    public function test_balance_of_800_full_payment_marks_bill_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-800-PAID', 800.00, '2026-08-01');

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($bill, 800.00))
            ->assertRedirect();

        $bill->refresh()->loadSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');

        $this->assertEquals(0.00, $bill->balance_due);
        $this->assertSame('Paid', $bill->status);

        $this->actingAs($consumerUser)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Payment Status: <strong>Paid</strong>', false);
    }

    public function test_three_consecutive_unpaid_months_raise_a_disconnection_notice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'BILL-MAY', 300.00, '2026-05-01');
        $june = $this->createBill($consumer, 'BILL-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->assertSame(3, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));

        foreach ([$may, $june, $july] as $bill) {
            $this->assertSame(
                ConsumerBalanceService::DISCONNECTION_NOTICE,
                $bill->refresh()->notice_level,
                'Expected a disconnection notice on '.$bill->prn,
            );
        }

        $this->assertSame('Active', $consumer->refresh()->status);

        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'bill_id' => $july->id,
            'status' => 'Active',
            'months_unpaid' => 3,
        ]);

        // Partial payment is refused once the notice is active.
        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), $this->paymentPayload($july, 100.00))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_two_consecutive_unpaid_months_do_not_raise_a_disconnection_notice(): void
    {
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'BILL-2M-MAY', 300.00, '2026-05-01');
        $june = $this->createBill($consumer, 'BILL-2M-JUNE', 300.00, '2026-06-01');

        $consumer->syncAccountStanding();

        $this->assertSame(2, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertNull($may->refresh()->notice_level);
        $this->assertNull($june->refresh()->notice_level);
        $this->assertSame('For Review', $consumer->refresh()->status);
        $this->assertDatabaseMissing('disconnection_notices', [
            'consumer_id' => $consumer->id,
        ]);
    }

    public function test_a_single_unpaid_month_never_raises_a_notice(): void
    {
        $consumer = $this->createConsumer();

        $july = $this->createBill($consumer, 'BILL-1M-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->assertSame(1, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertNull($july->refresh()->notice_level);
        $this->assertSame('For Review', $consumer->refresh()->status);
    }

    public function test_the_grace_period_defers_the_notice_until_after_the_due_date(): void
    {
        $balances = app(ConsumerBalanceService::class);
        $consumer = $this->createConsumer();

        // Newest period first: only the newest bill has passed its grace period.
        $pastGrace = $this->createBill($consumer, 'BILL-GRACE-PAST', 300.00, '2026-06-01', now()->subDays(4)->toDateString());
        $inGrace = $this->createBill($consumer, 'BILL-GRACE-IN', 300.00, '2026-05-01', now()->subDay()->toDateString());
        $future = $this->createBill($consumer, 'BILL-GRACE-FUTURE', 300.00, '2026-04-01', now()->addDays(10)->toDateString());

        $this->assertTrue($balances->isOverdue($pastGrace));
        $this->assertFalse($balances->isOverdue($inGrace));
        $this->assertFalse($balances->isOverdue($future));

        // Only the bill past its grace period counts toward the unpaid streak.
        $this->assertSame(1, $balances->consecutiveUnpaidMonthsFor($consumer->id));
    }

    public function test_a_bill_due_september_four_becomes_overdue_on_september_seven(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 09:00:00'));

        $balances = app(ConsumerBalanceService::class);
        $consumer = $this->createConsumer();
        $bill = $this->createBill($consumer, 'BILL-SEPT-EXAMPLE', 300.00, '2026-09-01', '2026-09-04');

        // Due 4 September, still inside the three day grace period on the 6th.
        $this->assertSame('2026-09-07', $balances->overdueDateFor($bill)->toDateString());
        $this->assertFalse($balances->isOverdue($bill));

        Carbon::setTestNow(Carbon::parse('2026-09-07 09:00:00'));

        // Not paid by 7 September, so the account is now eligible.
        $this->assertTrue($balances->isOverdue($bill));
    }

    public function test_three_consecutive_overdue_bills_raise_a_disconnection_notice_on_the_grace_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 09:00:00'));

        $consumer = $this->createConsumer();

        $march = $this->createBill($consumer, 'BILL-SEPT-MARCH', 300.00, '2026-03-01', '2026-03-04');
        $april = $this->createBill($consumer, 'BILL-SEPT-APRIL', 300.00, '2026-04-01', '2026-04-04');
        $may = $this->createBill($consumer, 'BILL-SEPT-MAY', 300.00, '2026-05-01', '2026-05-04');

        $consumer->syncAccountStanding();

        $this->assertSame(3, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $march->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $april->refresh()->notice_level);
        $this->assertSame(ConsumerBalanceService::DISCONNECTION_NOTICE, $may->refresh()->notice_level);
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_a_settled_month_breaks_the_consecutive_unpaid_streak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'BILL-STREAK-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-STREAK-JUNE', 300.00, '2026-06-01');
        $settled = $this->createBill($consumer, 'BILL-STREAK-JULY', 300.00, '2026-07-01');

        Payment::query()->create([
            'bill_id' => $settled->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-STREAK-JULY',
            'amount' => 300.00,
            'payment_date' => '2026-07-20',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $settled->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $settled->syncPaymentStatus();

        $consumer->syncAccountStanding();

        $this->assertSame('Paid', $settled->refresh()->status);
        // July is settled, so the unpaid run is May + June: two months, below
        // the three-month disconnection threshold.
        $this->assertSame(2, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertNull($may->refresh()->notice_level);
        $this->assertSame('For Review', $consumer->refresh()->status);
    }

    public function test_two_settled_months_leave_only_one_unpaid_month_and_no_notice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'BILL-BREAK-MAY', 300.00, '2026-05-01');
        $settled = $this->createBill($consumer, 'BILL-BREAK-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'BILL-BREAK-JULY', 300.00, '2026-07-01');

        Payment::query()->create([
            'bill_id' => $settled->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-BREAK-JUNE',
            'amount' => 300.00,
            'payment_date' => '2026-06-20',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $settled->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');
        $settled->syncPaymentStatus();

        $consumer->syncAccountStanding();

        // June is settled, so the unpaid run is May alone and never qualifies.
        $this->assertSame(1, app(ConsumerBalanceService::class)->consecutiveUnpaidMonthsFor($consumer->id));
        $this->assertNull($may->refresh()->notice_level);
        $this->assertSame('For Review', $consumer->refresh()->status);
    }

    public function test_consumer_payment_page_shows_required_amount_while_notice_is_active(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);

        $this->createBill($consumer, 'BILL-NOTICE-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-NOTICE-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'BILL-NOTICE-JULY', 300.00, '2026-07-01');

        $this->actingAs($consumerUser)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Disconnection Notice: <strong>Active</strong>', false)
            ->assertSee('Total Outstanding Balance: <strong>&#8369;900.00</strong>', false)
            ->assertSee('Required Payment Amount: <strong>&#8369;900.00</strong>', false)
            ->assertSee('Full payment is required. Partial payment is not allowed.');
    }

    public function test_consumer_payment_form_requires_confirmation_before_browser_submission(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $this->createBill($consumer, 'BILL-CONFIRM-FLOW', 800.00, '2026-08-01');

        $this->actingAs($consumerUser)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('id="paymentSubmitConfirmModal"', false)
            ->assertSee('Please double-check your payment details and receipt before submitting. Are you sure you want to submit this payment for verification?')
            ->assertSee('Yes, Submit for Confirmation')
            ->assertSee('Cancel')
            ->assertSee('paymentForm?.addEventListener(\'submit\'', false)
            ->assertSee('paymentForm.checkValidity()', false)
            ->assertSee('paymentSubmissionProcessing', false)
            ->assertSee('confirmSubmitButton.disabled = true;', false)
            ->assertSee('paymentForm.submit();', false);
    }

    public function test_consumer_online_payment_submission_uses_requested_success_message(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-CONSUMER-SUCCESS', 800.00, '2026-08-01');

        $this->actingAs($consumerUser)
            ->post(route('payments.store'), [
                'bill_id' => $bill->id,
                'amount' => 250.00,
                'payment_date' => '2026-08-15',
                'payment_channel' => 'Online',
                'payment_method' => 'GCash',
                'gateway_reference' => 'GCASH-CONFIRM-001',
                'sender_name' => 'Payment Sender',
                'sender_phone' => '09171234567',
            ])
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('success', 'Payment has been submitted and is Pending Verification by the administrator.');

        $this->assertDatabaseHas('payments', [
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'gateway_reference' => 'GCASH-CONFIRM-001',
            'status' => 'Pending',
        ]);
    }

    public function test_consumer_payment_prevents_duplicate_gateway_reference_submission(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-DUP-CHECK', 800.00, '2026-08-01');

        $payload = [
            'bill_id' => $bill->id,
            'amount' => 250.00,
            'payment_date' => '2026-08-15',
            'payment_channel' => 'Online',
            'payment_method' => 'GCash',
            'gateway_reference' => 'GCASH-DUP-001',
            'sender_name' => 'Payment Sender',
            'sender_phone' => '09171234567',
        ];

        $this->actingAs($consumerUser)
            ->post(route('payments.store'), $payload)
            ->assertRedirect(route('payments.index'))
            ->assertSessionHas('success', 'Payment has been submitted and is Pending Verification by the administrator.');

        $this->actingAs($consumerUser)
            ->from(route('payments.index'))
            ->post(route('payments.store'), $payload)
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasErrors(['gateway_reference' => 'This reference or transaction number has already been submitted.']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_consumer_billing_index_shows_partial_payment_rules_below_notice_threshold(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $this->createBill($consumer, 'BILL-800-BILLING', 800.00, '2026-08-01');

        $this->actingAs($consumerUser)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Account Payment Requirements')
            ->assertSee('Payment Status: <strong>Unpaid</strong>', false)
            ->assertSee('Disconnection Notice: <strong>None</strong>', false)
            ->assertSee('Total Outstanding Balance: <strong>&#8369;800.00</strong>', false)
            ->assertSee('Partial payment is allowed.')
            ->assertDontSee('Required Payment Amount: <strong>&#8369;800.00</strong>', false);
    }

    public function test_consumer_billing_statement_shows_partial_payment_rules_below_notice_threshold(): void
    {
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = $this->createConsumer(['user_id' => $consumerUser->id]);
        $bill = $this->createBill($consumer, 'BILL-800-STMT', 800.00, '2026-08-01');

        $this->actingAs($consumerUser)
            ->get(route('billing.show', $bill))
            ->assertOk()
            ->assertSee('Account Payment Requirements')
            ->assertSee('Payment Status: <strong>Unpaid</strong>', false)
            ->assertSee('Disconnection Notice: <strong>None</strong>', false)
            ->assertSee('Total Outstanding Balance: <strong>&#8369;800.00</strong>', false)
            ->assertSee('Partial payment is allowed.')
            ->assertDontSee('Required Payment Amount: <strong>&#8369;800.00</strong>', false);
    }

    public function test_full_payment_settles_every_month_and_lifts_the_notice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $may = $this->createBill($consumer, 'BILL-FULL-MAY', 300.00, '2026-05-01');
        $june = $this->createBill($consumer, 'BILL-FULL-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-FULL-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->assertTrue(app(ConsumerBalanceService::class)->hasDisconnectionNotice($consumer->id));

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($july, 900.00))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach ([$may, $june, $july] as $bill) {
            $bill->refresh()->loadSum(['payments as v' => fn ($q) => $q->where('status', 'Verified')], 'amount');

            $this->assertEquals(0.00, $bill->balance_due, $bill->prn.' should be fully settled');
            $this->assertSame('Paid', $bill->status);
            $this->assertNull($bill->notice_level);
        }

        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertFalse(app(ConsumerBalanceService::class)->hasDisconnectionNotice($consumer->id));
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Settled',
        ]);
        $this->assertEquals([300.00, 300.00, 300.00], Payment::query()
            ->orderBy('payment_date')
            ->orderBy('id')
            ->pluck('amount')
            ->map(fn ($amount) => (float) $amount)
            ->all());
    }

    public function test_full_payment_before_disconnection_keeps_the_account_active_and_preserves_notice_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'BILL-RECON-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-RECON-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-RECON-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();
        $graceNotice = DisconnectionNotice::query()->where('consumer_id', $consumer->id)->sole();
        $this->assertFalse($graceNotice->paymentWindowHasElapsed());
        $this->assertSame('Active', $consumer->refresh()->status);

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($july, 900.00))
            ->assertRedirect();

        $consumer->refresh();

        // No physical disconnection happened, so the account simply returns to
        // normal active service; reconnection is not required.
        $this->assertSame('Active', $consumer->status);
        $this->assertFalse(app(ConsumerBalanceService::class)->hasDisconnectionNotice($consumer->id));
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Settled',
        ]);

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());

        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer->update(['user_id' => $consumerUser->id]);
        $this->post(route('logout'));
        $this->post(route('login.attempt'), ['login' => $consumer->meter_number, 'password' => 'password'])
            ->assertRedirect();
        $this->get(route('payments.index'))->assertOk();
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', ['consumer_id' => $consumer->id, 'status' => 'Settled']);
    }

    public function test_full_payment_after_notice_deadline_but_before_disconnection_clears_notice_and_keeps_active_account(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();
        $this->createBill($consumer, 'BILL-LATE-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-LATE-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-LATE-JULY', 300.00, '2026-07-01');
        $consumer->syncAccountStanding();
        $notice = DisconnectionNotice::query()->where('consumer_id', $consumer->id)->sole();

        Carbon::setTestNow($notice->deadline_at->copy()->addMinute());
        $this->assertTrue($notice->refresh()->paymentWindowHasElapsed());
        $this->assertSame('Active', $consumer->refresh()->status);

        $this->actingAs($admin)->post(route('payments.store'), $this->paymentPayload($july, 900.00))
            ->assertRedirect()->assertSessionHasNoErrors();

        $response = $this->actingAs($admin)->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertSame(DisconnectionNotice::STATUS_SETTLED, $notice->refresh()->status);
    }

    public function test_settled_disconnected_account_waits_for_reconnection_rather_than_reconnecting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'BILL-RECON-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-RECON-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-RECON-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();
        $this->assertSame('Active', $consumer->refresh()->status);

        // The administrator confirms the physical disconnection after the
        // three-day payment window has elapsed.
        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00'));

        $this->actingAs($admin)
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']));

        $this->assertSame('Disconnected', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Disconnected',
        ]);

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($july, 900.00))
            ->assertRedirect();

        $consumer->refresh();

        // Supply is not restored automatically; it waits for the procedure.
        $this->assertSame('For Reconnection', $consumer->status);
        $this->assertFalse($consumer->isVerified() && $consumer->status === 'Disconnected');
    }

    public function test_confirmed_disconnection_still_requires_the_full_outstanding_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'BILL-CONFIRMED-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-CONFIRMED-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-CONFIRMED-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00'));

        $this->actingAs($admin)
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']));

        $this->assertSame('Disconnected', $consumer->refresh()->status);

        // The old bill flags are gone, but the inactive service status still
        // means only the exact outstanding balance can be paid.
        $this->assertNull($july->refresh()->notice_level);

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), $this->paymentPayload($july, 450.00))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_notice_blocks_partial_payment_but_allows_settling_the_exact_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'BILL-1500-MAY', 500.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-1500-JUNE', 500.00, '2026-06-01');
        $july = $this->createBill($consumer, 'BILL-1500-JULY', 500.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->post(route('payments.store'), $this->paymentPayload($july, 1000.00))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_full_payment_allocates_across_oldest_bills_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();
        $oldestBill = $this->createBill($consumer, 'BILL-500', 500.00, '2026-07-01');
        $newestBill = $this->createBill($consumer, 'BILL-1000', 1000.00, '2026-08-01');
        $consumer->syncAccountStanding();

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->paymentPayload($newestBill, 1500.00))
            ->assertRedirect();

        $oldestBill->refresh()->loadSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');
        $newestBill->refresh()->loadSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');

        $this->assertEquals(0.00, $oldestBill->balance_due);
        $this->assertEquals(0.00, $newestBill->balance_due);
        $this->assertSame('Paid', $oldestBill->status);
        $this->assertSame('Paid', $newestBill->status);
        $this->assertEquals(0.00, app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id));
        $this->assertEquals([500.00, 1000.00], Payment::query()->orderBy('payment_date')->orderBy('id')->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
    }

    public function test_pending_payment_below_required_amount_is_rejected_during_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'BILL-VERIFY-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'BILL-VERIFY-JUNE', 300.00, '2026-06-01');
        $bill = $this->createBill($consumer, 'BILL-VERIFY-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $pendingPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VERIFY-LOW',
            'amount' => 299.99,
            'payment_date' => '2026-08-15',
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('payments.verify', $pendingPayment))
            ->assertSessionHasErrors('amount');

        $this->assertSame('Pending', $pendingPayment->refresh()->status);
    }

    public function test_admin_account_management_shows_active_disconnection_notices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $flagged = $this->createConsumer(['full_name' => 'Flagged Consumer', 'account_number' => 'ADM-1']);
        $this->createBill($flagged, 'ADM-MAY', 300.00, '2026-05-01');
        $this->createBill($flagged, 'ADM-JUNE', 300.00, '2026-06-01');
        $this->createBill($flagged, 'ADM-JULY', 300.00, '2026-07-01');
        $flagged->syncAccountStanding();

        $healthy = $this->createConsumer(['full_name' => 'Healthy Consumer', 'account_number' => 'ADM-2']);
        $this->createBill($healthy, 'ADM-SINGLE', 300.00, '2026-07-01');
        $healthy->syncAccountStanding();

        $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'active']))
            ->assertOk()
            ->assertSee('Disconnection Notice')
            ->assertSee('Flagged Consumer')
            ->assertSee('Full payment required before disconnection.')
            // A healthy account is not listed as flagged.
            ->assertDontSee('Healthy Consumer</td>');
    }

    public function test_admin_disconnection_panel_clears_once_the_balance_is_settled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer(['account_number' => 'ADM-RC-1']);

        $this->createBill($consumer, 'RC-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'RC-JUNE', 300.00, '2026-06-01');
        $july = $this->createBill($consumer, 'RC-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();
        $this->assertSame('Active', $consumer->refresh()->status);

        // While unpaid, the account appears in the active notice panel.
        $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'active']))
            ->assertOk()
            ->assertSee('Disconnection Notice')
            ->assertSee('Disconnection Test Consumer');

        $payment = Payment::query()->create([
            'bill_id' => $july->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-ADMIN-RC',
            'amount' => 900.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        app(PaymentAllocationService::class)->allocateVerifiedFullPayment($payment, $admin);

        $this->assertSame(0, Bill::query()
            ->where('consumer_id', $consumer->id)
            ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
            ->count(), 'The notice is lifted once the balance is settled.');

        // The account never left active service, so it stays Active while the
        // notice history records that the balance settled the notice.
        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Settled',
        ]);

        $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'active']))
            ->assertOk()
            ->assertDontSee('data-record-status="Paid"', false);

        $response = $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk();
        $this->assertSame(0, $response->viewData('disconnectionNotices')->total());
        $this->assertContains($consumer->id, $response->viewData('consumers')->pluck('id')->all());
        $this->assertDatabaseHas('disconnection_notices', ['consumer_id' => $consumer->id, 'status' => 'Settled']);
    }

    public function test_notice_is_created_once_with_a_three_day_deadline_and_the_account_stays_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'DL-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'DL-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'DL-JULY', 300.00, '2026-07-01');

        $first = app(\App\Services\OverdueMonitoringService::class)->evaluate($consumer->fresh());
        $second = app(\App\Services\OverdueMonitoringService::class)->evaluate($consumer->fresh());

        $this->assertTrue($first['flagged']);
        $this->assertFalse($second['notice_issued']);

        $this->assertSame(1, DisconnectionNotice::query()
            ->where('consumer_id', $consumer->id)
            ->count(), 'Repeated sweeps must not create duplicate notices.');

        $notice = DisconnectionNotice::query()->where('consumer_id', $consumer->id)->firstOrFail();

        $this->assertSame(DisconnectionNotice::STATUS_ACTIVE, $notice->status);
        $this->assertSame('2026-09-07 09:00:00', $notice->deadline_at->toDateTimeString());
        $this->assertSame('Active', $consumer->refresh()->status);
    }

    public function test_admin_cannot_confirm_disconnection_before_the_three_day_window_closes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer();

        $this->createBill($consumer, 'DL-BLOCK-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'DL-BLOCK-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'DL-BLOCK-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        $this->actingAs($admin)
            ->from(route('consumers.index', ['account_category' => 'active']))
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'active']))
            ->assertSessionHasErrors('disconnection');

        $this->assertSame('Active', $consumer->refresh()->status);
        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Active',
        ]);
    }

    public function test_admin_confirmation_after_the_window_moves_the_account_to_inactive_and_keeps_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 09:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->createConsumer(['full_name' => 'Physically Disconnected Consumer']);

        $this->createBill($consumer, 'DL-CONFIRM-MAY', 300.00, '2026-05-01');
        $this->createBill($consumer, 'DL-CONFIRM-JUNE', 300.00, '2026-06-01');
        $this->createBill($consumer, 'DL-CONFIRM-JULY', 300.00, '2026-07-01');

        $consumer->syncAccountStanding();

        Carbon::setTestNow(Carbon::parse('2026-09-08 09:00:00'));

        $this->actingAs($admin)
            ->post(route('consumers.confirm-disconnection', $consumer))
            ->assertRedirect(route('consumers.index', ['account_category' => 'inactive']))
            ->assertSessionHasNoErrors();

        $consumer->refresh();

        $this->assertSame('Disconnected', $consumer->status);
        $this->assertNotNull($consumer->disconnected_at);

        $this->assertDatabaseHas('disconnection_notices', [
            'consumer_id' => $consumer->id,
            'status' => 'Disconnected',
            'confirmed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'inactive']))
            ->assertOk()
            ->assertSee('Physically Disconnected Consumer');

        $this->actingAs($admin)
            ->get(route('consumers.index', ['account_category' => 'active']))
            ->assertOk()
            ->assertDontSee('Physically Disconnected Consumer');

        $this->assertSame(1, DisconnectionNotice::query()
            ->where('consumer_id', $consumer->id)
            ->count(), 'The notice history remains available after disconnection.');
    }

    private function createConsumer(array $attributes = []): Consumer
    {
        static $sequence = 0;
        $sequence++;

        return Consumer::query()->create(array_merge([
            'full_name' => 'Disconnection Test Consumer',
            'account_number' => 'DTC-'.$sequence,
            'meter_number' => 'DTC-MTR-'.$sequence,
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ], $attributes));
    }

    private function createBill(Consumer $consumer, string $prn, float $amount, string $period, string $dueDate = '2026-08-31'): Bill
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
