<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_status_filter_controls_heading_table_and_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Billing Status Filter Consumer',
            'account_number' => 'BSF-1001',
            'meter_number' => 'BSF-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        foreach (range(1, 12) as $index) {
            Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => now()->subMonths($index)->startOfMonth()->toDateString(),
                'due_date' => now()->subMonths($index)->endOfMonth()->toDateString(),
                'prn' => sprintf('OD-%02d', $index),
                'amount' => 100,
                'arrears_amount' => 10,
                'status' => 'Overdue',
                'months_unpaid' => 1,
            ]);
        }

        foreach (range(1, 3) as $index) {
            $bill = Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => now()->subMonths(20 + $index)->startOfMonth()->toDateString(),
                'due_date' => now()->subMonths(20 + $index)->endOfMonth()->toDateString(),
                'prn' => sprintf('PAID-%02d', $index),
                'amount' => 900,
                'status' => 'Paid',
                'months_unpaid' => 0,
            ]);

            Payment::query()->create([
                'bill_id' => $bill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => sprintf('PAY-PAID-%02d', $index),
                'amount' => 900,
                'payment_date' => $bill->due_date,
                'payment_method' => 'Cash',
                'status' => 'Verified',
            ]);
        }

        foreach (range(1, 2) as $index) {
            Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => now()->addMonths($index)->startOfMonth()->toDateString(),
                'due_date' => now()->addMonths($index)->endOfMonth()->toDateString(),
                'prn' => sprintf('UNPAID-%02d', $index),
                'amount' => 50,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);
        }

        $partialBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonths(4)->startOfMonth()->toDateString(),
            'due_date' => now()->subMonths(4)->endOfMonth()->toDateString(),
            'prn' => 'PARTIAL-01',
            'amount' => 200,
            'status' => 'Paid with Balance',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $partialBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-PARTIAL-01',
            'amount' => 50,
            'payment_date' => $partialBill->due_date,
            'payment_method' => 'Cash',
            'status' => 'Verified',
        ]);

        $cases = [
            'Paid' => [
                'heading' => 'Paid Bills',
                'count' => 3,
                'total_amount' => 2700.0,
                'open_count' => 0,
                'previous_balance' => 0.0,
                'balance_due' => 0.0,
            ],
            'Paid with Balance' => [
                'heading' => 'Paid with Balance Bills',
                'count' => 1,
                'total_amount' => 200.0,
                'open_count' => 1,
                'previous_balance' => 0.0,
                'balance_due' => 150.0,
            ],
            'Unpaid' => [
                'heading' => 'Unpaid Bills',
                'count' => 2,
                'total_amount' => 100.0,
                'open_count' => 2,
                'previous_balance' => 0.0,
                'balance_due' => 100.0,
            ],
            'Overdue' => [
                'heading' => 'Overdue Bills',
                'count' => 12,
                'total_amount' => 1200.0,
                'open_count' => 12,
                'previous_balance' => 120.0,
                'balance_due' => 1200.0,
            ],
        ];

        foreach ($cases as $status => $expected) {
            $response = $this->actingAs($admin)->get(route('billing.index', ['status' => $status]));

            $response->assertOk()
                ->assertSee('<h1 class="billing-title">'.$expected['heading'].'</h1>', false)
                ->assertViewHas('billingHeading', $expected['heading'])
                ->assertViewHas('filteredBills', fn ($bills) => $bills->getCollection()
                    ->every(fn (Bill $bill) => $bill->status === $status))
                ->assertViewHas('billingSummary', fn (array $summary) => $summary['count'] === $expected['count']
                    && abs($summary['total_amount'] - $expected['total_amount']) < 0.001
                    && $summary['open_count'] === $expected['open_count']
                    && abs($summary['previous_balance'] - $expected['previous_balance']) < 0.001
                    && abs($summary['balance_due'] - $expected['balance_due']) < 0.001);
        }

        $all = $this->actingAs($admin)->get(route('billing.index'));

        $all->assertOk()
            ->assertSee('<h1 class="billing-title">Billing Center</h1>', false)
            ->assertDontSee('Select Status')
            ->assertViewHas('billingSummary', fn (array $summary) => $summary['count'] === 18
                && abs($summary['total_amount'] - 4200) < 0.001
                && $summary['open_count'] === 15
                && abs($summary['previous_balance'] - 120) < 0.001
                && abs($summary['balance_due'] - 1450) < 0.001);

        $allBillsAlias = $this->actingAs($admin)->get(route('billing.index', ['status' => 'All Bills']));

        $allBillsAlias->assertOk()
            ->assertViewHas('billingHeading', 'Billing Center')
            ->assertViewHas('billingSummary', fn (array $summary) => $summary['count'] === 18);
    }

    public function test_billing_status_filter_is_preserved_by_search_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Billing Status Pagination Consumer',
            'account_number' => 'BSP-1001',
            'meter_number' => 'BSP-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        foreach (range(1, 12) as $index) {
            Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => now()->subMonths($index)->startOfMonth()->toDateString(),
                'due_date' => now()->subMonths($index)->endOfMonth()->toDateString(),
                'prn' => sprintf('PAGE-OD-%02d', $index),
                'amount' => 100,
                'status' => 'Overdue',
                'months_unpaid' => 1,
            ]);
        }

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PAGE-PAID-01',
            'amount' => 300,
            'status' => 'Paid',
            'months_unpaid' => 0,
        ]);

        $pageTwo = $this->actingAs($admin)->get(route('billing.index', [
            'status' => 'Overdue',
            'page' => 2,
        ]));

        $pageTwo->assertOk()
            ->assertSee('<h1 class="billing-title">Overdue Bills</h1>', false)
            ->assertSee('status=Overdue', false)
            ->assertViewHas('filteredBills', fn ($bills) => $bills->currentPage() === 2
                && $bills->total() === 12
                && $bills->count() === 2
                && $bills->getCollection()->every(fn (Bill $bill) => $bill->status === 'Overdue'))
            ->assertViewHas('billingSummary', fn (array $summary) => $summary['count'] === 12);

        $search = $this->actingAs($admin)->get(route('billing.index', [
            'status' => 'Overdue',
            'search' => 'BSP-1001',
            'billing_period' => now()->subMonth()->format('Y-m'),
        ]));

        $search->assertOk()
            ->assertSee('<h1 class="billing-title">Overdue Bills</h1>', false)
            ->assertSee('Billing Status Pagination Consumer')
            ->assertViewHas('filteredBills', fn ($bills) => $bills->total() === 1
                && $bills->first()?->prn === 'PAGE-OD-01')
            ->assertViewHas('billingSummary', fn (array $summary) => $summary['count'] === 1
                && abs($summary['total_amount'] - 100) < 0.001);
    }
}
