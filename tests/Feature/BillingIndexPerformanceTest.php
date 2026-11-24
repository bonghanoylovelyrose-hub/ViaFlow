<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the cost of the billing index.
 *
 * Overdue monitoring used to run once per bill, which made the page slow in
 * proportion to the whole billing history. It must scale with the number of
 * accounts holding an unpaid bill, not with the number of bills.
 */
class BillingIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_index_cost_does_not_grow_with_bill_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'consumer']);

        $this->seedHistory($admin, 5, 6);
        $fewBills = Bill::query()->count();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $queriesWithFewBills = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Double the billing history for the same number of accounts.
        $this->seedHistory($admin, 5, 6, offset: 12);
        $this->assertGreaterThan($fewBills, Bill::query()->count());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $queriesWithManyBills = count(DB::getQueryLog());
        DB::disableQueryLog();

        // With the same account count, doubling the bill count must not
        // materially increase the work done on page load.
        $this->assertLessThanOrEqual(
            (int) ($queriesWithFewBills * 1.5),
            $queriesWithManyBills,
            sprintf(
                'Billing index query count grew from %d to %d purely because more bills exist.',
                $queriesWithFewBills,
                $queriesWithManyBills,
            ),
        );
    }

    public function test_consumer_billing_page_does_not_scan_every_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumerUser = User::factory()->create(['role' => 'consumer']);

        // Many other accounts with unpaid bills that this consumer must not touch.
        $this->seedHistory($admin, 15, 6);

        $consumer = Consumer::query()->create([
            'user_id' => $consumerUser->id,
            'full_name' => 'Isolated Consumer',
            'account_number' => 'ISO-1',
            'meter_number' => 'ISO-M-1',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->subMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'ISO-BILL-1',
            'amount' => 300.00,
            'status' => 'Unpaid',
        ]);

        $otherConsumerIds = Consumer::query()
            ->where('id', '!=', $consumer->id)
            ->pluck('id')
            ->all();

        $before = Consumer::query()->whereIn('id', $otherConsumerIds)
            ->pluck('status', 'id')
            ->all();

        $this->actingAs($consumerUser)->get(route('billing.index'))->assertOk();

        $after = Consumer::query()->whereIn('id', $otherConsumerIds)
            ->pluck('status', 'id')
            ->all();

        // A consumer's page must not re-evaluate unrelated accounts.
        $this->assertSame($before, $after);
    }

    private function seedHistory(User $admin, int $consumers, int $months, int $offset = 0): void
    {
        for ($c = 1; $c <= $consumers; $c++) {
            $index = $offset + $c;

            $consumer = Consumer::query()->create([
                'full_name' => "History Consumer {$index}",
                'account_number' => "HIST-{$index}",
                'meter_number' => "HIST-M-{$index}",
                'status' => 'Active',
                'membership_type' => 'Non-Member',
            ]);

            for ($m = 1; $m <= $months; $m++) {
                $period = now()->startOfMonth()->subMonths($m);

                $bill = Bill::query()->create([
                    'consumer_id' => $consumer->id,
                    'billing_period' => $period->toDateString(),
                    'due_date' => $period->copy()->endOfMonth()->toDateString(),
                    'prn' => "HIST-{$index}-{$m}",
                    'amount' => 300.00,
                    'status' => 'Unpaid',
                ]);

                // Leave the most recent months unpaid so there is real work to do.
                if ($m > 2) {
                    Payment::query()->create([
                        'bill_id' => $bill->id,
                        'consumer_id' => $consumer->id,
                        'reference_number' => "HISTPAY-{$index}-{$m}",
                        'amount' => 300.00,
                        'payment_date' => $period->toDateString(),
                        'payment_method' => 'Cash',
                        'status' => 'Verified',
                        'verified_at' => now(),
                        'verified_by' => $admin->id,
                    ]);

                    $bill->update(['status' => 'Paid']);
                }
            }
        }
    }
}