<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ConsumerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWaterBillingRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_filters_limit_history_to_older_matching_bills_and_keep_current_account_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->consumer();
        $other = $this->consumer();
        $old = $this->bill($consumer, '2025-12-01', 'MATCH-OLD', ['amount' => 125]);
        $latest = $this->bill($consumer, '2026-11-01', 'MATCH-LATEST', [
            'amount' => 425,
            'arrears_amount' => 125,
            'carried_forward_from_bill_id' => $old->id,
        ]);
        $old->update(['status' => 'Paid', 'carried_forward_to_bill_id' => $latest->id]);
        $this->bill($other, '2026-11-01', 'OTHER-LATEST');

        $index = $this->actingAs($admin)->get(route('billing.index'))->assertOk();
        $this->assertSame(['2026', '2025'], $index->viewData('billingYears')->all());
        $all = $index->viewData('billingAccounts');
        $this->assertSame(2, $all->total());
        $this->assertEqualsCanonicalizing([$consumer->id, $other->id], $all->getCollection()->pluck('consumer.id')->all());

        foreach ([['year' => 2025], ['month' => 12], ['status' => 'Carried Forward']] as $filter) {
            $accounts = $this->get(route('billing.index', $filter))->assertOk()->viewData('billingAccounts');
            $this->assertSame([$consumer->id], $accounts->getCollection()->pluck('consumer.id')->all());
        }

        $filters = ['search' => $consumer->meter_number, 'year' => 2025, 'month' => 12, 'status' => 'Paid'];
        $index = $this->get(route('billing.index', $filters))->assertOk()->assertSee('class="record-filter-clear">Clear</a>', false);
        $accounts = $index->viewData('billingAccounts');
        $this->assertSame([$consumer->id], $accounts->getCollection()->pluck('consumer.id')->all());
        $this->assertSame('2026-11-01', $accounts->first()['consumer']->latest_billing_period->toDateString());
        $this->assertEquals(425, $accounts->first()['outstanding']);
        $this->assertSame('Unpaid', $accounts->first()['status']);

        $history = route('billing.monthly-history', ['consumer' => $consumer] + $filters);
        $result = $this->get($history)->assertOk()
            ->assertSee('MATCH-OLD')->assertDontSee('MATCH-LATEST')
            ->assertDontSee('<select', false)->assertDontSee('<form', false);
        $this->assertSame($old->id, $result->viewData('records')->first()->item->id);
        $this->get($history.'&matches=0')->assertOk()
            ->assertSee('MATCH-OLD')->assertDontSee('MATCH-LATEST');
        $this->get(route('billing.show', ['bill' => $old] + $filters))->assertOk()
            ->assertSee(route('billing.index', $filters));
        $this->assertDatabaseCount('bills', 3);
    }

    public function test_admin_search_matches_consumer_names_account_numbers_and_meter_numbers_and_clears_all_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->consumer();
        $consumer->update(['full_name' => 'Elena Marie Navarro', 'account_number' => 'VIVA-NAV-1001', 'meter_number' => 'MT-1234']);
        $this->bill($consumer, '2026-11-01', 'ONLY-IN-PRN');
        $other = $this->consumer();
        $this->bill($other, '2026-11-01', 'MARIE-ONLY-IN-REFERENCE');

        $this->actingAs($admin);
        foreach (['ELENA', 'mArIe', 'NAVARRO', 'elena marie navarro', 'avar', 'viva-nav-1001', 'nav-100', 'mT-1234', '1234'] as $search) {
            $response = $this->get(route('billing.index', ['search' => $search]))->assertOk();
            $this->assertSame([$consumer->id], $response->viewData('billingAccounts')->getCollection()->pluck('consumer.id')->all());
            $this->assertSame(1, $response->viewData('billingSummary')['count']);
        }

        $noMatch = $this->get(route('billing.index', ['search' => 'ONLY-IN-PRN']))->assertOk();
        $this->assertSame(0, $noMatch->viewData('billingAccounts')->total());
        $legacy = $this->get(route('billing.index', ['prn' => 'ONLY-IN-PRN']))->assertOk();
        $this->assertSame(2, $legacy->viewData('billingAccounts')->total());

        $filtered = $this->get(route('billing.index', [
            'search' => 'elena', 'year' => 2026, 'month' => 11, 'status' => 'Unpaid', 'page' => 2,
        ]))->assertOk()
            ->assertSeeInOrder(['>Search Consumer / Account / Meter Number</label>', '>Period</label>', '>Payment Status</label>', '>Clear</a>'], false)
            ->assertSee('placeholder="Search by name, account, or meter number..."', false)
            ->assertSee('href="'.route('billing.index').'" class="record-filter-clear">Clear</a>', false)
            ->assertDontSee('aria-label="Clear status filter"', false);
        $this->assertSame(1, substr_count($filtered->getContent(), 'class="record-filter-clear">Clear</a>'));

        $reset = $this->get(route('billing.index'))->assertOk()->assertSee('<option value="">All Status</option>', false)
            ->assertSeeInOrder(['>Account Number</th>', '>Meter Number</th>', '>Latest Billing Period</th>'], false)
            ->assertSeeInOrder(['>Elena Marie Navarro</th>', '<td>VIVA-NAV-1001</td>', '<td class="billing-meter-number">MT-1234</td>'], false);
        $this->assertSame(['search' => '', 'year' => '', 'month' => '', 'status' => ''], $reset->viewData('filters'));
        $this->assertSame(1, $reset->viewData('billingAccounts')->currentPage());
        $this->assertSame(2, $reset->viewData('billingAccounts')->total());
    }

    public function test_history_paginates_newest_first_and_filters_by_date_and_live_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->consumer();
        $old = $this->bill($consumer, '2025-01-01', 'HISTORY-CARRIED', ['status' => 'Paid']);
        $this->bill($consumer, '2025-02-01', 'HISTORY-PAID', ['status' => 'Paid', 'amount' => 0]);
        $overdue = $this->bill($consumer, '2026-03-01', 'HISTORY-OVERDUE', ['status' => 'Overdue']);
        $this->bill($consumer, '2026-04-01', 'HISTORY-NOTICE', [
            'status' => 'Overdue',
            'notice_level' => ConsumerBalanceService::DISCONNECTION_NOTICE,
        ]);
        $partial = $this->bill($consumer, '2026-05-01', 'HISTORY-PARTIAL', ['status' => 'Paid with Balance']);
        $this->payment($partial, 50, 'Verified');
        $this->bill($consumer, '2026-06-01', 'HISTORY-JUN', ['status' => 'Paid', 'amount' => 0]);
        $this->bill($consumer, '2026-07-01', 'HISTORY-JUL', ['status' => 'Paid', 'amount' => 0]);
        $this->bill($consumer, '2026-08-01', 'HISTORY-AUG');
        $old->update(['carried_forward_to_bill_id' => $overdue->id]);

        $params = ['consumer' => $consumer, 'search' => $consumer->account_number];
        $first = $this->actingAs($admin)->get(route('billing.monthly-history', $params))->assertOk();
        $first->assertSeeInOrder(['HISTORY-AUG', 'HISTORY-JUL', 'HISTORY-JUN', 'HISTORY-PARTIAL', 'HISTORY-NOTICE', 'HISTORY-OVERDUE'])
            ->assertDontSee('HISTORY-CARRIED')->assertSee('Next')->assertSee('Previous');
        $this->assertCount(6, $first->viewData('records'));
        $this->assertSame(route('billing.monthly-history', $params + ['page' => 2]), $first->viewData('records')->nextPageUrl());

        $second = $this->get(route('billing.monthly-history', $params + ['page' => 2]))->assertOk();
        $second->assertSeeInOrder(['HISTORY-PAID', 'HISTORY-CARRIED'])->assertDontSee('HISTORY-AUG');
        $this->assertCount(2, $second->viewData('records'));
        $this->assertSame(route('billing.monthly-history', $params + ['page' => 1]), $second->viewData('records')->previousPageUrl());
        $stalePage = $this->get(route('billing.monthly-history', $params + ['page' => 9]))->assertOk();
        $this->assertSame(2, $stalePage->viewData('records')->currentPage());
        $this->get(route('billing.monthly-history', $params + ['year' => 2025, 'month' => 2]))->assertOk()
            ->assertSee('HISTORY-PAID')->assertDontSee('HISTORY-CARRIED')->assertDontSee('HISTORY-OVERDUE');

        $unpaid = $this->get(route('billing.monthly-history', $params + ['status' => 'Unpaid']))->assertOk();
        $unpaid->assertSeeInOrder(['HISTORY-AUG', 'HISTORY-PARTIAL', 'HISTORY-NOTICE', 'HISTORY-OVERDUE'])
            ->assertDontSee('HISTORY-CARRIED')->assertDontSee('HISTORY-PAID');
        $this->assertCount(4, $unpaid->viewData('records'));

        foreach ([
            'Paid' => ['HISTORY-JUL', 'HISTORY-JUN', 'HISTORY-PAID', 'HISTORY-CARRIED'],
            'Paid with Balance' => ['HISTORY-PARTIAL'],
            'Overdue' => ['HISTORY-OVERDUE'],
            'Disconnection Notice' => ['HISTORY-NOTICE'],
            'Carried Forward' => ['HISTORY-CARRIED'],
        ] as $status => $expected) {
            $records = $this->get(route('billing.monthly-history', $params + ['status' => $status]))
                ->assertOk()->viewData('records');
            $this->assertSame($expected, $records->getCollection()->pluck('item.prn')->all());
        }

        $noticeConsumer = $this->consumer();
        $noticeBill = $this->bill($noticeConsumer, '2026-09-01', 'ACTIVE-NOTICE', ['status' => 'Overdue']);
        DisconnectionNotice::query()->create([
            'consumer_id' => $noticeConsumer->id,
            'bill_id' => $noticeBill->id,
            'status' => DisconnectionNotice::STATUS_ACTIVE,
            'issued_at' => now(),
            'deadline_at' => now()->addDays(3),
            'outstanding_amount' => 300,
            'months_unpaid' => 3,
        ]);
        $noticeFilters = ['year' => 2026, 'month' => 9, 'status' => 'Disconnection Notice'];
        $noticeAccounts = $this->get(route('billing.index', $noticeFilters))->assertOk()->viewData('billingAccounts');
        $this->assertSame([$noticeConsumer->id], $noticeAccounts->getCollection()->pluck('consumer.id')->all());
        $this->get(route('billing.monthly-history', ['consumer' => $noticeConsumer] + $noticeFilters))
            ->assertOk()->assertSee('ACTIVE-NOTICE');

        $this->actingAs(User::factory()->create(['role' => 'consumer']))
            ->get(route('billing.monthly-history', $params))->assertForbidden();
    }

    public function test_existing_payment_verification_settles_carried_and_current_history_without_duplicate_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = $this->consumer();
        $previous = $this->bill($consumer, '2026-09-01', 'SETTLED-PREVIOUS', ['amount' => 200]);
        $current = $this->bill($consumer, '2026-10-01', 'SETTLED-CURRENT', [
            'amount' => 500,
            'arrears_amount' => 200,
            'carried_forward_from_bill_id' => $previous->id,
        ]);
        $previous->update(['status' => 'Paid', 'carried_forward_to_bill_id' => $current->id]);
        $payment = $this->payment($current, 500, 'Pending');

        $this->actingAs($admin)->patch(route('payments.verify', $payment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bills', ['id' => $previous->id, 'status' => 'Paid', 'amount' => 200, 'carried_forward_to_bill_id' => $current->id]);
        $this->assertDatabaseHas('bills', ['id' => $current->id, 'status' => 'Paid', 'amount' => 500, 'arrears_amount' => 200]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'bill_id' => $current->id, 'status' => 'Verified', 'amount' => 500]);
        $this->assertDatabaseCount('payments', 1);

        $accounts = $this->get(route('billing.index'))->assertOk()->viewData('billingAccounts');
        $this->assertEquals(0, $accounts->first()['outstanding']);
        $this->assertSame('Paid', $accounts->first()['status']);
        $history = $this->get(route('billing.monthly-history', ['consumer' => $consumer, 'status' => 'Paid']))
            ->assertOk()->viewData('records');
        $this->assertSame(['Paid', 'Paid'], $history->getCollection()->pluck('status')->all());
    }

    private function consumer(): Consumer
    {
        $suffix = Consumer::query()->count() + 1;

        return Consumer::query()->create([
            'full_name' => "History Consumer {$suffix}",
            'account_number' => "HISTORY-{$suffix}",
            'meter_number' => "METER-{$suffix}",
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);
    }

    private function bill(Consumer $consumer, string $period, string $prn, array $attributes = []): Bill
    {
        return Bill::query()->create($attributes + [
            'consumer_id' => $consumer->id,
            'billing_period' => $period,
            'due_date' => $period,
            'prn' => $prn,
            'amount' => 300,
            'status' => 'Unpaid',
        ]);
    }

    private function payment(Bill $bill, float $amount, string $status): Payment
    {
        return Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $bill->consumer_id,
            'reference_number' => 'PAY-'.$bill->prn,
            'amount' => $amount,
            'payment_date' => today(),
            'payment_method' => 'Cash',
            'status' => $status,
        ]);
    }
}
