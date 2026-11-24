<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerReconnection;
use App\Models\DisconnectionNotice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerDisconnectionHistoryService;
use App\Services\ConsumerPasswordSetupService;
use App\Services\ConsumerReconnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConsumerDisconnectionPortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(8, 0));
    }

    private function account(): array
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id, 'full_name' => 'Historical Consumer', 'email' => $user->email,
            'account_number' => 'HISTORY-100', 'meter_number' => 'HISTORY-METER-100',
            'status' => 'Active', 'membership_type' => 'Non-Member',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        return [$user, $consumer, $admin];
    }

    private function bill(Consumer $consumer, string $period, float $amount = 300): Bill
    {
        return Bill::query()->create([
            'consumer_id' => $consumer->id, 'billing_period' => $period,
            'due_date' => Carbon::parse($period)->endOfMonth(),
            'prn' => 'HISTORY-'.str_replace('-', '', $period), 'amount' => $amount, 'status' => 'Unpaid',
        ]);
    }

    private function disconnect(Consumer $consumer, User $admin, string $reason): DisconnectionNotice
    {
        $consumer->syncAccountStanding();
        $notice = $consumer->disconnectionNotices()->active()->sole();
        $this->travel(4)->days();
        $this->actingAs($admin)->post(route('consumers.confirm-disconnection', $consumer), [
            'disconnection_reason' => $reason,
        ])->assertSessionHasNoErrors()->assertRedirect(route('consumers.index', ['account_category' => 'inactive']));

        return $notice->fresh();
    }

    private function settleAndReconnect(Consumer $consumer, User $admin, Bill $bill, float $amount): void
    {
        $this->travel(1)->days();
        $this->actingAs($admin)->post(route('payments.store'), [
            'bill_id' => $bill->id, 'amount' => $amount, 'payment_date' => today()->toDateString(),
            'payment_method' => 'Cash', 'payment_channel' => 'Counter',
        ])->assertSessionHasNoErrors();
        $this->assertSame('For Reconnection', $consumer->refresh()->status);
        $this->actingAs($consumer->user)->get(route('dashboard'))->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 0.00');
        $this->travel(1)->days();
        $this->actingAs($admin)->post(route('consumers.reconnect', $consumer), [
            'confirm_full_payment' => 1, 'reconnection_date' => today()->toDateString(),
            'installation_scheduled_at' => now('Asia/Manila')->addMinute()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('Active', $consumer->refresh()->status);
        $user = $consumer->user()->firstOrFail();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Pending Reconnection');
        $setupUrl = app(ConsumerPasswordSetupService::class)->createLink($consumer);
        $this->post($setupUrl, ['password' => 'CyclePassword123!', 'password_confirmation' => 'CyclePassword123!'])->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->must_change_reactivation_password);
        $reconnection = $consumer->reconnections()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('consumers.reconnection-complete', [$consumer, $reconnection]))->assertSessionHasNoErrors();
    }

    public function test_two_complete_cycles_preserve_all_records_and_display_independent_history(): void
    {
        [$user, $consumer, $admin] = $this->account();
        $reading = MeterReading::query()->create([
            'consumer_id' => $consumer->id, 'reader_id' => $admin->id, 'meter_number' => $consumer->meter_number,
            'reading_date' => '2026-07-01', 'previous_reading' => 100, 'current_reading' => 110,
            'consumption' => 10, 'status' => 'Encoded', 'remarks' => 'Preserve original meter record',
        ]);
        $originalReading = $reading->refresh()->getRawOriginal();
        foreach (['2026-05-01', '2026-06-01', '2026-07-01'] as $period) {
            $this->bill($consumer, $period);
        }
        $consumer->syncAccountStanding();
        $notice = $consumer->disconnectionNotices()->active()->sole();
        $this->assertSame('900.00', $notice->outstanding_amount);
        $lastBill = $this->bill($consumer, '2026-08-01', 75);
        $firstNotice = $this->disconnect($consumer, $admin, 'First physical cutoff after unpaid notices');
        $this->assertSame('975.00', $firstNotice->outstanding_at_disconnection);
        $this->assertSame('900.00', $firstNotice->outstanding_amount);
        $this->settleAndReconnect($consumer, $admin, $lastBill, 975);

        $firstNotice->refresh();
        $this->assertNotNull($firstNotice->settled_at);
        $firstReconnection = $consumer->reconnections()->sole();
        $this->assertSame($firstNotice->id, $firstReconnection->disconnection_notice_id);
        $originalNotice = $firstNotice->getRawOriginal();
        $originalReconnection = $firstReconnection->getRawOriginal();
        $originalPayments = Payment::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $originalBills = Bill::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Disconnection History')
            ->assertSee('First physical cutoff after unpaid notices')->assertSee('PHP 975.00')->assertSee('Active / Connected');

        $this->travelTo(now()->setDate(2027, 3, 10));
        foreach (['2026-12-01', '2027-01-01', '2027-02-01'] as $period) {
            $lastBill = $this->bill($consumer, $period, 200);
        }
        $secondNotice = $this->disconnect($consumer, $admin, 'Second physical cutoff');
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('account.inactive'));
        $this->get(route('account.inactive'))->assertOk()->assertSee('PHP 600.00');
        $this->settleAndReconnect($consumer, $admin, $lastBill, 600);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('First physical cutoff after unpaid notices')->assertSee('Second physical cutoff')
            ->assertSee('PHP 975.00')->assertSee('PHP 600.00')->assertSee('Payment Settlement Date');
        $history = $response->viewData('disconnectionHistory');
        $this->assertCount(2, $history);
        $this->assertSame($secondNotice->disconnected_at->toDateTimeString(), $history[0]['disconnection_date']->toDateTimeString());
        $this->assertTrue($history[0]['payment_settlement_date']->greaterThan($history[1]['payment_settlement_date']));
        $this->assertTrue($history[0]['reconnection_date']->greaterThan($history[1]['reconnection_date']));
        $this->assertSame($originalNotice, $firstNotice->refresh()->getRawOriginal());
        $this->assertSame($originalReconnection, $firstReconnection->refresh()->getRawOriginal());
        $this->assertSame($originalReading, $reading->refresh()->getRawOriginal());
        $this->assertSame($originalPayments, Payment::query()->whereIn('id', array_column($originalPayments, 'id'))->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($originalBills, Bill::query()->whereIn('id', array_column($originalBills, 'id'))->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertTrue(Hash::check('CyclePassword123!', $user->refresh()->password));
        $this->assertSame('HISTORY-100', $consumer->account_number);
        $this->assertSame('HISTORY-METER-100', $consumer->meter_number);
        $this->get(route('billing.index'))->assertOk()->assertSee('HISTORY-20260501');
        $this->get(route('payments.index'))->assertOk();
        $this->get(route('consumer.profile.show'))->assertOk();
        $this->assertDatabaseCount('consumer_reconnections', 2);
        $this->assertDatabaseCount('disconnection_notices', 2);
    }

    public function test_disconnection_blocks_existing_sessions_direct_files_signed_routes_and_all_mutations(): void
    {
        Storage::fake('local');
        [$user, $consumer, $admin] = $this->account();
        foreach (['2026-05-01', '2026-06-01', '2026-07-01'] as $period) {
            $bill = $this->bill($consumer, $period);
        }
        $active = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertStringContainsString('no-store', $active->headers->get('Cache-Control'));
        $this->disconnect($consumer, $admin, 'Confirmed physical disconnection');
        Storage::disk('local')->put('private-proof.txt', 'Confidential payment proof');
        $this->actingAs($user);
        foreach ([
            route('dashboard'), route('billing.index'), route('billing.show', $bill), route('billing.statement.pdf', $bill),
            route('payments.index'), route('consumer.profile.show'), route('consumer.password.change'),
            route('connection.status'), route('uploads.serve', ['path' => 'private-proof.txt']),
            route('installation.reschedule.connection', $consumer),
            URL::temporarySignedRoute('password.setup', now()->addHour(), ['user' => $user->id, 'token' => str_repeat('a', 64)]),
        ] as $url) {
            $this->get($url)->assertRedirect(route('account.inactive'));
            $this->getJson($url)->assertForbidden();
            $this->call('HEAD', $url)->assertRedirect(route('account.inactive'));
        }
        $this->post(route('payments.store'), [])->assertForbidden();
        $this->post(route('payments.parse-receipt'), [])->assertForbidden();
        $this->post(route('notifications.read-all'))->assertForbidden();
        $this->patch(route('consumer.password.update'), [])->assertForbidden();
        $popup = $this->get(route('account.inactive'))->assertOk()->assertSee('Account Disconnection')
            ->assertSee('Log Out')->assertDontSee('View Dashboard')->assertDontSee('View Account Information')
            ->assertDontSee('<nav', false)->assertDontSee('<aside', false)->assertDontSee('data-bs-dismiss', false);
        $this->assertStringContainsString('no-store', $popup->headers->get('Cache-Control'));
        $this->assertStringContainsString('event.persisted', $popup->getContent());
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $signedSchedule = URL::temporarySignedRoute('installation.reschedule.connection', now()->addHour(), ['consumer' => $consumer->id]);
        $this->get($signedSchedule)->assertForbidden();
    }

    public function test_popup_balance_uses_the_ledger_even_when_a_cached_bill_status_is_paid(): void
    {
        [$user, $consumer] = $this->account();
        $bill = $this->bill($consumer, '2026-08-01', 123.45);
        $bill->update(['status' => 'Paid']);
        $consumer->update(['status' => 'Disconnected']);
        $this->actingAs($user)->get(route('account.inactive'))->assertOk()->assertSee('PHP 123.45');
        $this->assertSame('Paid', $bill->refresh()->status);
    }

    public function test_popup_updates_water_and_loan_balances_independently_after_verification(): void
    {
        [$user, $consumer, $admin] = $this->account();
        $sourceBill = $this->bill($consumer, '2026-08-01', 90);
        $waterBill = $this->bill($consumer, '2026-09-01', 150);
        $sourceBill->update(['carried_forward_to_bill_id' => $waterBill->id]);
        $loan = $consumer->loans()->create([
            'status' => 'Ready for Scheduling', 'purpose' => 'Installation', 'amount' => 240, 'monthly_installment' => 40,
            'term_months' => 6, 'applied_at' => '2026-11-01', 'approved_at' => '2026-11-04', 'start_billing_period' => '2026-12-01',
            'installment_schedule_snapshot' => array_map(fn (int $month) => ['month' => $month, 'amount' => 40], range(1, 6)),
        ]);
        $consumer->loans()->create([
            'status' => 'Pending Review', 'purpose' => 'Unapproved application', 'amount' => 120,
            'term_months' => 3, 'monthly_installment' => 40, 'applied_at' => today(),
        ]);
        $loanBill = $this->bill($consumer, '2026-12-01', 40);
        $loanBill->update(['bill_type' => 'Loan', 'loan_id' => $loan->id, 'loan_installment_amount' => 40]);
        $consumer->update(['status' => 'Disconnected']);

        $paymentDetails = [
            'consumer_id' => $consumer->id, 'payment_date' => today(), 'payment_method' => 'GCash', 'payment_channel' => 'Online',
        ];
        $waterPayment = Payment::create($paymentDetails + [
            'bill_id' => $waterBill->id, 'payment_type' => 'Water', 'status' => 'Pending', 'amount' => 150, 'reference_number' => 'WATER-PENDING',
        ]);
        $loanPayment = Payment::create($paymentDetails + [
            'loan_id' => $loan->id, 'payment_type' => 'Loan', 'status' => 'Pending', 'amount' => 40, 'reference_number' => 'LOAN-PENDING',
        ]);
        Payment::create($paymentDetails + [
            'loan_id' => $loan->id, 'payment_type' => 'Loan', 'status' => 'Rejected', 'amount' => 20, 'reference_number' => 'LOAN-REJECTED',
        ]);

        $this->actingAs($user)->get(route('account.inactive'))->assertOk()
            ->assertSeeInOrder(['Outstanding Water Balance', 'PHP 150.00', 'Outstanding Loan Balance', 'PHP 240.00', 'Total Amount to Pay', 'PHP 390.00'])
            ->assertDontSee('WATER-PENDING')->assertDontSee('LOAN-PENDING');
        $this->getJson(route('account.access-status'))->assertOk()->assertExactJson([
            'portal_access' => false,
            'balances' => ['outstanding_water_balance' => 150, 'outstanding_loan_balance' => 240, 'total_amount_to_pay' => 390],
        ]);

        $this->actingAs($admin)->patch(route('payments.verify', $loanPayment))->assertRedirect(route('payments.receipt', $loanPayment));
        $this->get(route('payments.receipt', $loanPayment))->assertOk()->assertSee('Loan Payment')->assertDontSee($waterBill->prn);
        $this->actingAs($user)->getJson(route('account.access-status'))->assertOk()->assertExactJson([
            'portal_access' => false,
            'balances' => ['outstanding_water_balance' => 150, 'outstanding_loan_balance' => 200, 'total_amount_to_pay' => 350],
        ]);

        $this->actingAs($admin)->patch(route('payments.verify', $waterPayment))->assertRedirect(route('payments.receipt', $waterPayment));
        $this->get(route('payments.receipt', $waterPayment))->assertOk()->assertSee('Water Payment')->assertDontSee($loanBill->prn);
        $this->get(route('payments.index', ['view' => 'water-payments']))->assertOk()
            ->assertViewHas('paymentAccounts', fn ($accounts) => $accounts->first()['history']->getCollection()->pluck('record.id')->all() === [$waterPayment->id]);
        $this->get(route('payments.index', ['view' => 'loan-payments']))->assertOk()
            ->assertViewHas('paymentAccounts', function ($accounts) use ($loanPayment, $waterPayment) {
                $payments = $accounts->first()['history']->getCollection()->where('kind', 'payment')->pluck('record');

                return $payments->contains(fn (Payment $payment) => $payment->is($loanPayment))
                    && ! $payments->contains(fn (Payment $payment) => $payment->is($waterPayment));
            });
        $this->actingAs($user)->getJson(route('account.access-status'))->assertOk()->assertExactJson([
            'portal_access' => false,
            'balances' => ['outstanding_water_balance' => 0, 'outstanding_loan_balance' => 200, 'total_amount_to_pay' => 200],
        ]);
        $this->assertTrue(app(ConsumerReconnectionService::class)->eligibility($consumer->fresh())['eligible']);
    }

    public function test_the_open_popup_detects_authorized_reactivation_without_a_new_login(): void
    {
        [$user, $consumer, $admin] = $this->account();
        $consumer->update(['status' => 'Disconnected']);
        $this->actingAs($user)->get(route('account.inactive'))->assertOk()->assertSee('checkPortalAccess', false);
        $this->getJson(route('account.access-status'))->assertOk()->assertJsonPath('portal_access', false);
        app(ConsumerReconnectionService::class)->reconnect(
            $consumer, $admin, now(),
        );
        $status = $this->getJson(route('account.access-status'))->assertOk()->assertExactJson(['portal_access' => true]);
        $this->assertStringContainsString('no-store', $status->headers->get('Cache-Control'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.inactive'))->assertRedirect(route('dashboard'));
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()->assertSee('Pending Reconnection');
        $this->actingAs($admin)->getJson(route('account.access-status'))->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->getJson(route('account.access-status'))->assertUnauthorized();
    }

    public function test_legacy_history_does_not_invent_historical_balances_or_expose_another_account(): void
    {
        [$user, $consumer, $admin] = $this->account();
        $bill = $this->bill($consumer, '2026-08-01');
        DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'Disconnected',
            'issued_at' => now()->subDays(8), 'deadline_at' => now()->subDays(5),
            'disconnected_at' => now()->subDays(4), 'outstanding_amount' => 9876,
            'notes' => 'Original legacy reason',
        ]);
        $other = Consumer::query()->create(['full_name' => 'Other consumer', 'status' => 'Active']);
        DisconnectionNotice::query()->create([
            'consumer_id' => $other->id, 'status' => 'Settled', 'issued_at' => now(), 'deadline_at' => now(),
            'notes' => 'Other private history',
        ]);
        $history = app(ConsumerDisconnectionHistoryService::class)->forConsumer($consumer);
        $this->assertCount(1, $history);
        $this->assertNull($history[0]['outstanding_balance']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Original legacy reason')
            ->assertSee('Not recorded')->assertDontSee('PHP 9,876.00')->assertDontSee('Other private history');
    }

    public function test_schema_upgrade_preserves_legacy_records_and_links_only_recorded_cycle_dates_and_payments(): void
    {
        [$user, $consumer, $admin] = $this->account();
        $bill = $this->bill($consumer, '2026-08-01');
        $notice = DisconnectionNotice::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'status' => 'Disconnected',
            'issued_at' => now()->subDays(8), 'deadline_at' => now()->subDays(5),
            'disconnected_at' => now()->subDays(4), 'outstanding_amount' => 300, 'notes' => 'Legacy notice preserved',
        ]);
        $payment = Payment::query()->create([
            'consumer_id' => $consumer->id, 'bill_id' => $bill->id, 'reference_number' => 'LEGACY-VERIFIED-PAYMENT',
            'amount' => 300, 'payment_date' => today()->subDay(), 'payment_method' => 'Cash', 'payment_channel' => 'Counter',
            'status' => 'Verified', 'verified_at' => now()->subDay(), 'verified_by' => $admin->id,
        ]);
        $record = ConsumerReconnection::query()->create([
            'consumer_id' => $consumer->id, 'confirmed_by' => $admin->id, 'disconnected_at' => $notice->disconnected_at,
            'reconnection_date' => today(), 'installation_scheduled_at' => now()->addDay(), 'activated_at' => now(),
            'reconnection_fee' => 0, 'verified_payment_ids' => [$payment->id],
        ]);
        $originalPayment = $payment->refresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_11_06_000002_preserve_disconnection_cycles.php');
        $migration->down();
        $migration->up();

        $this->assertSame($notice->id, $record->refresh()->disconnection_notice_id);
        $this->assertTrue($record->payment_settled_at->equalTo($payment->verified_at));
        $this->assertTrue($notice->refresh()->settled_at->equalTo($payment->verified_at));
        $this->assertNull($notice->outstanding_at_disconnection);
        $this->assertSame('300.00', $notice->outstanding_amount);
        $this->assertSame('Legacy notice preserved', $notice->notes);
        $this->assertSame($originalPayment, $payment->refresh()->getRawOriginal());
        $history = app(ConsumerDisconnectionHistoryService::class)->forConsumer($consumer);
        $this->assertCount(1, $history);
        $this->assertTrue($history[0]['payment_settlement_date']->equalTo($payment->verified_at));
        $this->assertDatabaseCount('bills', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('disconnection_notices', 1);
        $this->assertDatabaseCount('consumer_reconnections', 1);
    }
}
