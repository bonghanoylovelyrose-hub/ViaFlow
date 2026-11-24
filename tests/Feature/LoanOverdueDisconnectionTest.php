<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\ConsumerNotificationLog;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\DisconnectionNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoanOverdueDisconnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_terms_raise_one_notice_only_after_the_final_due_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 08:00', 'Asia/Manila'));
        foreach ([3, 6, 12] as $term) {
            $loans[$term] = $this->loan($term, '2026-11-20');
        }

        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->assertDatabaseCount('disconnection_notices', 0);

        $this->travel(1)->day();
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->assertDatabaseCount('disconnection_notices', 3);
        $messages = Mail::getSymfonyTransport()->messages();
        $this->assertCount(3, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame(DisconnectionNoticeService::LOAN_NOTICE_SUBJECT, $message->getSubject());
        $this->assertStringContainsString('background:#991b1b', $message->getHtmlBody());
        $this->assertStringContainsString('PHP 300.00', $message->getHtmlBody());
        $this->assertStringContainsString('November 24, 2026', $message->getHtmlBody());

        foreach ($loans as $term => $loan) {
            $notice = $loan->consumer->disconnectionNotices()->sole();
            $this->assertSame(DisconnectionNotice::SOURCE_LOAN, $notice->source);
            $this->assertSame($loan->id, $notice->loan_id);
            $this->assertEquals($term * 100, $notice->outstanding_amount);
            $this->assertTrue($notice->deadline_at->eq($notice->issued_at->copy()->addDays(3)));
            $this->assertFalse($notice->paymentWindowHasElapsed());
            $this->assertSame('Active', $loan->consumer->fresh()->status);
            $this->assertDatabaseHas('consumer_notification_logs', [
                'event' => 'loan_overdue_disconnection_'.$loan->id, 'status' => ConsumerNotificationLog::STATUS_SENT,
            ]);
        }
    }

    public function test_only_verified_full_payment_settles_the_notice(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 08:00', 'Asia/Manila'));
        $loan = $this->loan(6, '2026-11-20');
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $notice = $loan->consumer->disconnectionNotices()->sole();
        $admin = User::factory()->create(['role' => 'admin']);

        $partial = $this->pendingPayment($loan, 250);
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->assertEquals(600, $notice->fresh()->loan_balance_due);
        $this->actingAs($admin)->patch(route('payments.verify', $partial))->assertSessionHasNoErrors();
        $this->assertEquals(350, $notice->fresh()->loan_balance_due);
        $this->assertTrue($notice->fresh()->isActive());

        $this->patch(route('payments.verify', $this->pendingPayment($loan, 350)))->assertSessionHasNoErrors();
        $this->assertSame('Settled', $notice->fresh()->status);
        $this->assertNotNull($notice->fresh()->settled_at);
        $this->assertSame('Active', $loan->consumer->fresh()->status);
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->assertDatabaseCount('disconnection_notices', 1);
        $this->assertCount(1, Mail::getSymfonyTransport()->messages());
    }

    public function test_ready_loan_rows_sort_first_and_combined_notices_get_their_full_grace_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 08:00', 'Asia/Manila'));
        $loan = $this->loan(6, '2026-11-20');
        $admin = User::factory()->create(['role' => 'admin']);
        $confirm = route('consumers.confirm-disconnection', $loan->consumer);
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->actingAs($admin)->post($confirm)->assertSessionHasErrors('disconnection');
        $this->travel(3)->days();
        $combined = $this->loan(3, '2026-11-23');
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $period) {
            Bill::query()->create([
                'consumer_id' => $combined->consumer_id, 'billing_period' => $period,
                'due_date' => Carbon::parse($period)->addDays(15), 'amount' => 100, 'status' => 'Unpaid',
                'prn' => 'WATER-'.str_replace('-', '', $period),
            ]);
        }
        $this->artisan('billing:monitor-overdue')->assertSuccessful();

        $table = $this->get(route('consumers.index', ['account_category' => 'active']))->assertOk()
            ->assertSee('Disconnection Reason')->assertSee('Both Water Billing and Meter Loan Overdue')
            ->viewData('disconnectionNotices')->items();
        $this->assertSame([$loan->consumer_id, $combined->consumer_id], array_map(fn ($row) => $row['consumer']->id, $table));
        $this->assertSame('Ready for Disconnection', $table[0]['record_status']);
        $this->assertSame('Within Grace Period', $table[1]['record_status']);
        $this->assertSame(300.0, $table[1]['water_outstanding']);
        $this->assertSame(300.0, $table[1]['loan_outstanding']);
        $this->assertSame('Active', $loan->consumer->fresh()->status);

        $this->post($confirm, ['disconnection_reason' => 'Confirmed physical disconnection'])->assertSessionHasNoErrors();
        $this->assertSame('Disconnected', $loan->consumer->fresh()->status);
        $this->assertEquals(600, app(DisconnectionNoticeService::class)->outstandingForReconnection($loan->consumer->fresh()));
        $this->artisan('billing:monitor-overdue')->assertSuccessful();
        $this->assertSame('Disconnected', $loan->consumer->fresh()->status);

        $combinedConfirm = route('consumers.confirm-disconnection', $combined->consumer);
        $this->post($combinedConfirm)->assertSessionHasErrors('disconnection');
        $this->travel(3)->days();
        $this->post($combinedConfirm)->assertSessionHasNoErrors();
        $history = $combined->consumer->disconnectionNotices()->get();
        $this->assertCount(2, $history);
        $this->assertCount(1, $history->where('status', DisconnectionNotice::STATUS_DISCONNECTED));
        $this->assertCount(1, $history->where('status', DisconnectionNotice::STATUS_INCLUDED_IN_DISCONNECTION));
        $this->assertSame(600.0, app(DisconnectionNoticeService::class)->outstandingForReconnection($combined->consumer->fresh()));
    }

    private function loan(int $term, string $finalDue): Loan
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id, 'email' => $user->email, 'full_name' => $user->name,
            'account_number' => 'OVERDUE-'.$user->id, 'meter_number' => 'M-OVERDUE-'.$user->id,
            'status' => 'Active', 'membership_type' => 'Member',
        ]);
        $start = Carbon::parse($finalDue)->subMonthsNoOverflow($term - 1)->startOfMonth();
        $loan = $consumer->loans()->create([
            'term_months' => $term, 'amount' => $term * 100, 'monthly_installment' => 100,
            'status' => 'Active', 'purpose' => 'Meter installation',
            'applied_at' => $start->copy()->subDays(10), 'approved_at' => $start->copy()->subDays(5),
            'start_billing_period' => $start,
        ]);
        foreach (range(1, $term) as $number) {
            $due = Carbon::parse($finalDue)->subMonthsNoOverflow($term - $number);
            $loan->installments()->create([
                'installment_number' => $number, 'billing_period' => $due->copy()->startOfMonth(), 'due_date' => $due,
                'required_amount' => 100, 'amount_paid' => 0, 'remaining_balance' => 100, 'status' => 'Upcoming',
            ]);
        }

        return $loan;
    }

    private function pendingPayment(Loan $loan, float $amount): Payment
    {
        return Payment::query()->create([
            'consumer_id' => $loan->consumer_id, 'loan_id' => $loan->id, 'payment_type' => 'Loan',
            'amount' => $amount, 'status' => 'Pending', 'payment_method' => 'GCash', 'payment_channel' => 'Online',
            'payment_date' => today(), 'reference_number' => 'OVERDUE-PAY-'.$amount,
        ]);
    }
}
