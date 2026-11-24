<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_history_tracks_remaining_balance_after_verified_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'TEST-PAY-HISTORY',
            'amount' => 665.00,
            'base_charge' => 150.00,
            'water_charge' => 390.00,
            'arrears_amount' => 250.00,
            'penalty_amount' => 25.00,
            'units_consumed' => 12,
            'unit_rate' => 20.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $firstPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-HISTORY-001',
            'amount' => 410.00,
            'payment_date' => now()->startOfMonth()->day(9)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $secondPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-HISTORY-002',
            'amount' => 100.00,
            'payment_date' => now()->startOfMonth()->day(10)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->assertEquals(255.00, $firstPayment->remaining_balance_after_payment);
        $this->assertEquals(155.00, $secondPayment->remaining_balance_after_payment);
    }

    public function test_pending_payment_cannot_be_verified_when_it_exceeds_remaining_bill_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'TEST-PAY-VERIFY',
            'amount' => 100.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VERIFY-001',
            'amount' => 80.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $pendingPayment = Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VERIFY-002',
            'amount' => 30.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('payments.verify', $pendingPayment))
            ->assertSessionHasErrors('amount');

        $this->assertSame('Pending', $pendingPayment->refresh()->status);
    }

    public function test_payment_history_filters_by_status_channel_search_and_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Filter Consumer',
            'account_number' => 'PAY-FILTER-1001',
            'meter_number' => 'PAY-FILTER-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
        $otherConsumer = Consumer::query()->create([
            'full_name' => 'Other Filter Consumer',
            'account_number' => 'PAY-FILTER-1002',
            'meter_number' => 'PAY-FILTER-M-1002',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $consumerBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PAY-FILTER-BILL-1001',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
        $otherBill = Bill::query()->create([
            'consumer_id' => $otherConsumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PAY-FILTER-BILL-1002',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $verifiedPayment = Payment::query()->create([
            'bill_id' => $consumerBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-ALPHA',
            'amount' => 100,
            'payment_date' => now()->subDays(3)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);
        Payment::query()->create([
            'bill_id' => $otherBill->id,
            'consumer_id' => $otherConsumer->id,
            'reference_number' => 'PAY-BETA',
            'amount' => 80,
            'payment_date' => now()->subDays(2)->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);
        Payment::query()->create([
            'bill_id' => $otherBill->id,
            'consumer_id' => $otherConsumer->id,
            'reference_number' => 'PAY-GAMMA',
            'amount' => 60,
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Rejected',
        ]);

        $pending = $this->actingAs($admin)->get(route('payments.index', ['status' => 'Pending']));
        $pending->assertOk()
            ->assertSee('PAY-BETA')
            ->assertDontSee('PAY-ALPHA')
            ->assertDontSee('PAY-GAMMA')
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="payment_filter_status"', false);

        $online = $this->actingAs($admin)->get(route('payments.index', ['view' => 'review', 'channel' => 'Online']));
        $online->assertOk()
            ->assertSee('PAY-BETA')
            ->assertDontSee('PAY-ALPHA')
            ->assertDontSee('PAY-GAMMA');

        $search = $this->actingAs($admin)->get(route('payments.index', ['search' => 'GAMMA']));
        $search->assertOk()
            ->assertSee('PAY-GAMMA')
            ->assertDontSee('PAY-ALPHA')
            ->assertDontSee('PAY-BETA');

        $consumerPendingPayment = Payment::query()->create([
            'bill_id' => $consumerBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-CONSUMER-PENDING',
            'amount' => 50,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);
        $consumerRejectedPayment = Payment::query()->create([
            'bill_id' => $consumerBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-CONSUMER-REJECTED',
            'amount' => 40,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'payment_channel' => 'Online',
            'status' => 'Rejected',
        ]);

        $consumerAll = $this->actingAs($consumerUser)->get(route('payments.index'));
        $consumerAll->assertOk()
            ->assertSee('PAY-ALPHA')
            ->assertSee('PAY-CONSUMER-PENDING')
            ->assertSee('PAY-CONSUMER-REJECTED')
            ->assertSee('View Receipt')
            ->assertSee(route('payments.receipt', $verifiedPayment), false)
            ->assertDontSee(route('payments.receipt', $consumerPendingPayment), false)
            ->assertDontSee(route('payments.receipt', $consumerRejectedPayment), false)
            ->assertSee('pp-filter-all')
            ->assertSee('pp-filter-pending')
            ->assertSee('pp-filter-verified')
            ->assertSee('pp-filter-rejected');

        $this->assertSame(1, substr_count($consumerAll->getContent(), 'View Receipt'));

        $consumerPending = $this->actingAs($consumerUser)->get(route('payments.index', ['status' => 'Pending']));
        $consumerPending->assertOk()
            ->assertSee('PAY-CONSUMER-PENDING')
            ->assertDontSee('PAY-ALPHA')
            ->assertDontSee('PAY-CONSUMER-REJECTED')
            ->assertDontSee('View Receipt');

        $consumerView = $this->actingAs($consumerUser)->get(route('payments.index', ['status' => 'Verified']));
        $consumerView->assertOk()
            ->assertSee('PAY-ALPHA')
            ->assertDontSee('PAY-BETA')
            ->assertDontSee('PAY-GAMMA')
            ->assertDontSee('PAY-CONSUMER-PENDING')
            ->assertDontSee('PAY-CONSUMER-REJECTED')
            ->assertSee('View Receipt')
            ->assertSee(route('payments.receipt', $verifiedPayment), false)
            ->assertSee('data-auto-filter', false);
    }

    public function test_admin_payment_verification_lists_newest_submission_first_regardless_of_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'full_name' => 'Submission Order Consumer',
            'account_number' => 'PAY-ORDER-1001',
            'meter_number' => 'PAY-ORDER-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PAY-ORDER-BILL-1001',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Carbon::setTestNow('2026-11-04 09:00:00');
        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => '1PAYNEWESTSUBMISSION',
            'amount' => 100,
            // Deliberately older than the other payment's manual date.
            'payment_date' => '2026-10-01',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Pending',
        ]);

        Carbon::setTestNow('2026-11-03 09:00:00');
        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => '1PAYOLDERSUBMISSION',
            'amount' => 100,
            // Deliberately newer than the other payment's manual date.
            'payment_date' => '2026-11-04',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        Carbon::setTestNow();

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSeeInOrder(['1PAYNEWESTSUBMISSION', '1PAYOLDERSUBMISSION']);
    }

    public function test_admin_payment_verification_keeps_submission_order_across_pagination_and_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'full_name' => 'Pagination Order Consumer',
            'account_number' => 'PAY-ORDER-1002',
            'meter_number' => 'PAY-ORDER-M-1002',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PAY-ORDER-BILL-1002',
            'amount' => 1000,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $base = Carbon::parse('2026-11-04 12:00:00');
        $references = [];

        for ($index = 0; $index < 11; $index++) {
            Carbon::setTestNow($base->copy()->subMinutes($index));

            $reference = '1PAYSUBMISSION'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            Payment::query()->create([
                'bill_id' => $bill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => $reference,
                'amount' => 10 + $index,
                // Reverse the manual dates so payment_date ordering would put
                // the oldest submission first.
                'payment_date' => $base->copy()->subDays(10 - $index)->toDateString(),
                'payment_method' => 'Cash',
                'payment_channel' => 'Counter',
                'status' => 'Pending',
            ]);

            $references[] = $reference;
        }

        Carbon::setTestNow();

        $pageOne = $this->actingAs($admin)->get(route('payments.index', ['view' => 'review']));
        $pageOne->assertOk()
            ->assertSee('placeholder="Search by name, account, meter, or reference..."', false)
            ->assertSeeInOrder(['>Consumer</th>', '>Account Number</th>', '>Meter Number</th>', '>Payment Date</th>'], false)
            ->assertSeeInOrder(['>Pagination Order Consumer</div>', '>PAY-ORDER-1002</td>', '>PAY-ORDER-M-1002</td>'], false)
            ->assertSeeInOrder([$references[0], $references[1], $references[9]])
            ->assertDontSee($references[10]);
        $this->assertNull($pageOne->viewData('paymentAccounts'));

        $filters = ['view' => 'review', 'payment_type' => 'Water', 'channel' => 'Counter'];
        foreach (['pagination order', 'pay-order-1002', 'pAy-OrDeR-M-1002', 'm-1002', 'pay-order-bill-1002'] as $search) {
            $results = $this->get(route('payments.index', $filters + ['search' => $search]))->assertOk();
            $records = $results->viewData('paymentRecords');
            $this->assertSame(11, $records->total());
            $this->assertSame(array_slice($references, 0, 10), $records->getCollection()->pluck('record.reference_number')->all());
        }

        $pageTwo = $this->get(route('payments.index', $filters + ['page' => 2, 'search' => 'm-1002']));
        $pageTwo->assertOk()
            ->assertSee($references[10])
            ->assertDontSee($references[0])
            ->assertSee('href="'.route('payments.index', ['view' => 'review']).'"', false);

        $lastSubmission = $this->get(route('payments.index', $filters + ['search' => strtolower($references[10])]))->assertOk();
        $this->assertSame([$references[10]], $lastSubmission->viewData('paymentRecords')->getCollection()->pluck('record.reference_number')->all());
    }
}
