<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMonitoringLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_payment_monitoring_uses_structured_summary_and_table_layouts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);

        $consumer = Consumer::query()->create([
            'full_name' => 'Layout Consumer',
            'account_number' => 'VIVA-LAYOUT-1001',
            'meter_number' => 'MTR-LAYOUT-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PRN-LAYOUT-1001',
            'amount' => 1000.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $bill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-LAYOUT-1001',
            'amount' => 250.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'proof_path' => 'proofs/layout-receipt.jpg',
        ]);

        $response = $this->actingAs($admin)->get(route('payments.index'));

        $response->assertOk()
            ->assertSee('payments-page-heading', false)
            ->assertSee('payments-summary-grid', false)
            ->assertSeeInOrder([
                'Pending Verification',
                'Verified Today',
                'Online Submissions',
                'Verified Amount Today',
            ])
            ->assertSee('Bills and Payments')
            ->assertSee('payments-panel-head', false)
            ->assertSee('payments-records-table', false)
            ->assertSee('data-admin-page-scroll', false)
            ->assertSee('data-admin-freeze', false)
            ->assertDontSee('Open Bills for Payment')
            ->assertDontSee('Payment History</h2>')
            ->assertSee('name="view"', false)
            ->assertDontSee('Water Bills')->assertSee('Water Payments')->assertDontSee('Loan Bills')->assertSee('Loan Payments')
            ->assertSee('payments-table-wrap', false)
            ->assertSee('payments-filter-form', false)
            ->assertSee('payments-filter-actions', false)
            ->assertSee('payments-row-actions', false)
            ->assertSee('Receipt')
            ->assertSee('Proof');

        $this->assertSame(1, substr_count($response->getContent(), '<table class="table align-middle payments-table payments-records-table">'));
        $this->assertSame(['Paid', 'Pending Payment', 'Rejected'], $response->viewData('statuses'));
        $response->assertDontSee('Select Status');
        preg_match('/<select[^>]*id="payment_filter_status"[^>]*>(.*?)<\/select>/s', $response->getContent(), $statusFilter);
        $this->assertStringContainsString('value="Paid"', $statusFilter[1]);
        $this->assertStringContainsString('value="Pending Payment"', $statusFilter[1]);
        $this->assertStringContainsString('value="Rejected"', $statusFilter[1]);
        foreach (['Unpaid', 'Overdue', 'Pending', 'Verified'] as $removedStatus) {
            $this->assertStringNotContainsString('value="'.$removedStatus.'"', $statusFilter[1]);
        }
    }

    public function test_admin_payment_views_separate_water_bills_from_water_payment_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = Consumer::query()->create([
            'full_name' => 'View Filter Consumer',
            'account_number' => 'VIVA-VIEW-1001',
            'meter_number' => 'MTR-VIEW-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);
        $openBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'prn' => 'PRN-VIEW-OPEN',
            'amount' => 900,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);
        $overdueBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonth()->startOfMonth()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'prn' => 'PRN-VIEW-OVERDUE',
            'amount' => 700,
            'status' => 'Overdue',
            'months_unpaid' => 1,
        ]);
        $paidBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'due_date' => now()->subMonth()->toDateString(),
            'prn' => 'PRN-VIEW-PAID',
            'amount' => 500,
            'status' => 'Paid',
            'months_unpaid' => 0,
        ]);
        $pending = Payment::query()->create([
            'bill_id' => $openBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VIEW-PENDING',
            'amount' => 900,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Pending',
        ]);
        $verified = Payment::query()->create([
            'bill_id' => $paidBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VIEW-ONLINE',
            'amount' => 500,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);
        $rejected = Payment::query()->create([
            'bill_id' => $openBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'PAY-VIEW-REJECTED',
            'amount' => 900,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Counter',
            'status' => 'Rejected',
        ]);

        $default = $this->actingAs($admin)->get(route('payments.index', ['view' => 'water-payments']));
        $default->assertOk()->assertSee('PAY-VIEW-PENDING')->assertSee('PAY-VIEW-ONLINE')->assertSee('PAY-VIEW-REJECTED')
            ->assertSee('Water Payments')
            ->assertSee('Add Payment')->assertSee('Loan Payments')
            ->assertSee('name="view"', false);
        $accounts = $default->viewData('paymentAccounts');
        $this->assertSame(1, $accounts->total());
        $defaultRecords = $accounts->first()['history'];
        $this->assertSame(3, $defaultRecords->total());
        $this->assertSame(0, $defaultRecords->getCollection()->where('kind', 'bill')->count());
        $this->assertSame(3, $defaultRecords->getCollection()->where('kind', 'payment')->count());
        $default->assertSee('Reference / PRN')->assertSee('Amount')->assertSee('Remaining Balance');

        $this->get(route('billing.index'))
            ->assertOk()->assertSee('View Filter Consumer')->assertSee('View Monthly Billings');
        $this->get(route('billing.monthly-history', $consumer))
            ->assertOk()->assertSee('PRN-VIEW-OPEN')->assertSee('PRN-VIEW-OVERDUE')->assertSee('PRN-VIEW-PAID');

        $waterPayments = $this->get(route('payments.index', ['view' => 'water-bills']))->assertOk()->assertSee('Water Payments');
        $this->assertTrue($waterPayments->viewData('paymentAccounts')->first()['history']->getCollection()->every(fn (array $row): bool => $row['kind'] === 'payment'));

        $filtered = $this->get(route('payments.index', [
            'view' => 'water-payments', 'status' => 'Paid', 'search' => 'PAY-VIEW',
        ]))->assertOk()->assertSee('PAY-VIEW-ONLINE')->assertDontSee('PAY-VIEW-PENDING');
        $this->assertSame([$verified->id], $filtered->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
        $paidHistory = $this->get(route('payments.index', ['view' => 'water-payments', 'status' => 'Paid']))
            ->assertSee('PAY-VIEW-ONLINE')->assertDontSee('PAY-VIEW-PENDING')->assertDontSee('PAY-VIEW-REJECTED');
        $this->assertSame(1, $paidHistory->viewData('transactionCount'));
        $pendingRecords = $this->get(route('payments.index', ['view' => 'water-payments', 'status' => 'Pending Payment']))
            ->assertOk()->assertSee('PAY-VIEW-PENDING')->assertDontSee('PAY-VIEW-REJECTED')->assertDontSee('PAY-VIEW-ONLINE');
        $this->assertSame(1, $pendingRecords->viewData('transactionCount'));
    }

    public function test_shared_records_pagination_and_counts_follow_all_active_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Paginated Payment Consumer',
            'account_number' => 'VIVA-PAGE-1001',
            'meter_number' => 'MTR-PAGE-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        for ($index = 1; $index <= 11; $index++) {
            $bill = Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => now()->subMonths($index)->startOfMonth()->toDateString(),
                'due_date' => now()->subDays($index)->toDateString(),
                'prn' => sprintf('PRN-PAGINATED-%02d', $index),
                'amount' => 100 + $index,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);
            Payment::query()->create([
                'bill_id' => $bill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => sprintf('PAY-PAGINATED-%02d', $index),
                'amount' => 10,
                'payment_date' => now()->subDays($index)->toDateString(),
                'payment_method' => 'Cash',
                'payment_channel' => 'Counter',
                'status' => 'Pending',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('payments.index', [
            'view' => 'water-payments',
            'status' => 'Pending Payment',
            'search' => 'PRN-PAGINATED',
            'payment_account' => $consumer->id,
            'history_page' => 2,
        ]))->assertOk();

        $account = $response->viewData('paymentAccounts')->first();
        $this->assertTrue($account['expanded']);
        $records = $account['history'];
        $this->assertSame(11, $records->total());
        $this->assertCount(5, $records->items());
        $this->assertSame(6, $records->perPage());
        parse_str((string) parse_url($records->previousPageUrl(), PHP_URL_QUERY), $previousPageQuery);
        $this->assertSame('Pending Payment', $previousPageQuery['status'] ?? null);
        $this->assertStringContainsString('search=PRN-PAGINATED', $records->previousPageUrl());
        $this->assertStringContainsString('view=water-payments', $records->previousPageUrl());
        $this->assertStringContainsString('history_page=1', $records->previousPageUrl());
    }

    public function test_bill_status_filters_use_remaining_balance_and_remain_separate_from_payment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Settlement Filter Consumer',
            'account_number' => 'VIVA-SETTLE-1001',
            'meter_number' => 'MTR-SETTLE-1001',
            'status' => 'Active',
            'membership_type' => 'Member',
        ]);

        $paidWaterBill = Bill::query()->create([
            'consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-08-01',
            'due_date' => '2026-08-31', 'prn' => 'PRN-WATER-PAID', 'amount' => 100, 'status' => 'Paid',
        ]);
        $partialWaterBill = Bill::query()->create([
            'consumer_id' => $consumer->id, 'bill_type' => 'Water', 'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30', 'prn' => 'PRN-WATER-PARTIAL', 'amount' => 100, 'status' => 'Unpaid',
        ]);
        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id, 'applied_by_user_id' => $admin->id, 'amount' => 100,
            'term_months' => 1, 'purpose' => 'Installation materials', 'status' => 'Approved',
            'monthly_installment' => 100, 'applied_at' => '2026-08-01',
        ]);
        $loanBill = Bill::query()->create([
            'consumer_id' => $consumer->id, 'bill_type' => 'Loan', 'loan_id' => $loan->id,
            'billing_period' => '2026-09-01', 'due_date' => '2026-09-30',
            'prn' => 'PRN-LOAN-PARTIAL', 'amount' => 100, 'status' => 'Unpaid',
        ]);
        $installment = LoanInstallment::query()->create([
            'loan_id' => $loan->id, 'bill_id' => $loanBill->id, 'installment_number' => 1,
            'billing_period' => '2026-09-01', 'due_date' => '2026-09-30', 'required_amount' => 100,
            'amount_paid' => 20, 'remaining_balance' => 80, 'status' => 'Partially Paid',
        ]);

        $createPayment = function (Bill $bill, string $reference, string $type, float $amount, string $status, string $channel) use ($consumer, $admin): Payment {
            return Payment::query()->create([
                'bill_id' => $bill->id, 'consumer_id' => $consumer->id, 'payment_type' => $type,
                'reference_number' => $reference, 'amount' => $amount, 'payment_date' => '2026-09-15',
                'payment_method' => $channel === 'Counter' ? 'Cash' : 'GCash', 'payment_channel' => $channel,
                'status' => $status, 'verified_at' => $status === 'Verified' ? now() : null,
                'verified_by' => $status === 'Verified' ? $admin->id : null,
            ]);
        };

        $paid = $createPayment($paidWaterBill, 'WATER-PAID-REF', 'Water', 100, 'Verified', 'Online');
        $partial = $createPayment($partialWaterBill, 'WATER-PARTIAL-REF', 'Water', 20, 'Verified', 'Counter');
        $createPayment($partialWaterBill, 'WATER-PENDING-REF', 'Water', 10, 'Pending', 'Online');
        $loanPayment = $createPayment($loanBill, 'LOAN-PENDING-REF', 'Loan', 10, 'Pending', 'Online');
        $loanVerifiedPayment = $createPayment($loanBill, 'LOAN-VERIFIED-REF', 'Loan', 20, 'Verified', 'Counter');

        $paidResponse = $this->actingAs($admin)->get(route('payments.index', [
            'view' => 'water-payments', 'status' => 'Paid', 'search' => 'WATER-PAID-REF',
        ]))->assertOk();
        $this->assertSame([$paid->id], $paidResponse->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
        $paidResponse->assertSee('Payment Status')->assertSee('Bill Status')->assertSee('Verified')->assertSee('Paid');

        $unpaidResponse = $this->get(route('payments.index', [
            'view' => 'water-payments', 'status' => 'Paid', 'search' => 'WATER-PARTIAL-REF',
        ]))->assertOk();
        $this->assertSame([$partial->id], $unpaidResponse->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
        $unpaidResponse->assertSee('Verified')->assertSee('Paid with Balance');

        $loanResponse = $this->get(route('payments.index', ['view' => 'loan-payments', 'status' => 'Pending Payment']))->assertOk();
        $this->assertSame([$loanPayment->id], $loanResponse->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
        $loanResponse->assertSee('PRN-LOAN-PARTIAL');
        $loanPaidResponse = $this->get(route('payments.index', [
            'view' => 'loan-payments', 'status' => 'Paid', 'search' => 'LOAN-VERIFIED-REF',
        ]))->assertOk();
        $this->assertSame([$loanVerifiedPayment->id], $loanPaidResponse->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
        $loanPaidResponse->assertSee('Verified')->assertSee('Paid with Balance');

        $legacyUnpaidFilter = $this->get(route('payments.index', ['view' => 'water-payments', 'status' => 'Unpaid']))
            ->assertRedirect(route('payments.index', ['view' => 'water-payments']));
        $clearedUnpaidFilter = $this->followRedirects($legacyUnpaidFilter);
        $this->assertSame('', $clearedUnpaidFilter->viewData('filters')['status']);
        $this->assertSame(3, $clearedUnpaidFilter->viewData('transactionCount'));

        $reviewResponse = $this->get(route('payments.index', [
            'view' => 'review', 'payment_type' => 'Loan', 'channel' => 'Online', 'search' => 'LOAN-PENDING-REF',
        ]))->assertOk();
        $this->assertSame([$loanPayment->id], $reviewResponse->viewData('paymentRecords')->getCollection()->pluck('record.id')->all());
        $reviewResponse->assertSee('Loan')->assertDontSee('WATER-PENDING-REF');
        $this->assertSame(80.0, (float) $installment->fresh()->remaining_balance);
    }

    public function test_admin_water_and_loan_history_apply_the_same_date_filters_and_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Combined Filter Consumer', 'account_number' => 'FILTER-DATES',
            'meter_number' => 'MT-9407', 'status' => 'Active',
        ]);

        foreach (['water-payments' => 'Water', 'loan-payments' => 'Loan'] as $view => $type) {
            $bill = Bill::query()->create([
                'consumer_id' => $consumer->id, 'bill_type' => $type, 'billing_period' => '2026-01-01',
                'due_date' => '2026-01-31', 'prn' => 'DATE-PRN-'.$type, 'amount' => 100, 'status' => 'Unpaid',
            ]);
            $create = function (string $reference, string $status, string $date, string $submitted) use ($bill, $type): Payment {
                $payment = Payment::query()->create([
                    'bill_id' => $bill->id, 'consumer_id' => $bill->consumer_id, 'payment_type' => $type,
                    'reference_number' => $reference.'-'.$type, 'status' => $status, 'amount' => 10,
                    'payment_date' => $date, 'payment_method' => 'GCash', 'payment_channel' => 'Online',
                ]);
                $payment->forceFill(['created_at' => $submitted])->save();

                return $payment;
            };
            $first = $create('FILTER-TIE-1', 'Verified', '2026-01-03', '2026-02-20 14:00:00');
            $first->update(['receipt_number' => 'RECEIPT-DATE-'.$type, 'gateway_reference' => 'GATEWAY-DATE-'.$type]);
            $second = $create('FILTER-TIE-2', 'Verified', '2026-01-03', '2025-12-31 14:00:00');
            $pending = $create('FILTER-PENDING', 'Pending', '2025-12-31', '2026-01-04 09:00:00');
            $old = $create('FILTER-OLD', 'Verified', '2025-12-01', '2026-03-01 10:00:00');

            $page = $this->actingAs($admin)->get(route('payments.index', [
                'view' => $view, 'channel' => 'Counter', 'sort' => 'oldest',
            ]))->assertOk()
                ->assertSee('Search Consumer / Account / Meter / Reference')
                ->assertSee('<option value="">All Status</option>', false)
                ->assertSee('placeholder="Search by name, account, meter, or reference..."', false)
                ->assertSeeInOrder(['>Consumer</th>', '>Account Number</th>', '>Meter Number</th>', '>Latest Transaction</th>', '>Transactions</th>', '>Actions</th>'], false)
                ->assertSeeInOrder(['>Combined Filter Consumer</td>', '>FILTER-DATES</td>', '>MT-9407</td>'], false)
                ->assertDontSee('id="payment_filter_channel"', false)
                ->assertDontSee('name="sort"', false);
            $this->assertArrayNotHasKey('channel', $page->viewData('filters'));
            $this->assertSame([2026, 2025], $page->viewData('paymentYears')->all());
            $this->assertSame(1, $page->viewData('paymentAccounts')->total());
            $this->assertSame([$pending->id, $second->id, $first->id, $old->id], $page->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());

            foreach (['combined filter consumer', 'FILTER CON', 'filter-dates', 'mT-9407', '9407', strtolower($bill->prn)] as $search) {
                $accounts = $this->get(route('payments.index', ['view' => $view, 'search' => $search]))->assertOk()->viewData('paymentAccounts');
                $this->assertSame([$consumer->id], $accounts->getCollection()->pluck('id')->all());
                $this->assertSame(4, $accounts->first()['history']->total());
            }

            foreach (['filter-tie-1', 'receipt-date', 'gateway-date'] as $search) {
                $accounts = $this->get(route('payments.index', ['view' => $view, 'search' => $search]))->assertOk()->viewData('paymentAccounts');
                $this->assertSame([$first->id], $accounts->first()['history']->getCollection()->pluck('record.id')->all());
            }

            $filters = ['view' => $view, 'year' => 2026, 'month' => 1];
            $paid = $this->get(route('payments.index', $filters + ['status' => 'Paid', 'search' => '9407', 'payment_account' => $consumer->id]))->assertOk();
            $this->assertTrue($paid->viewData('paymentAccounts')->first()['expanded']);
            $paid->assertSee(route('payments.receipt', $first), false);
            $this->assertSame([$second->id, $first->id], $paid->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());

            $clearUrl = route('payments.index', ['view' => $view]);
            $paid->assertSee('href="'.$clearUrl.'" class="record-filter-clear">Clear</a>', false);
            $cleared = $this->get($clearUrl)->assertOk();
            $this->assertSame(
                ['status' => '', 'search' => '', 'year' => 0, 'month' => 0],
                collect($cleared->viewData('filters'))->only(['status', 'search', 'year', 'month'])->all(),
            );
            $this->assertSame(4, $cleared->viewData('paymentAccounts')->first()['history']->total());

            $this->get(route('payments.index', ['year' => 2025] + $filters + ['status' => 'Paid', 'search' => '9407']))
                ->assertOk()->assertSee('No '.strtolower($type).' payments match the selected filters.');
            $submissions = $this->get(route('payments.index', $filters + ['status' => 'Pending Payment', 'search' => $bill->prn]))->assertOk();
            $this->assertSame([$pending->id], $submissions->viewData('paymentAccounts')->first()['history']->getCollection()->pluck('record.id')->all());
            $this->get(route('payments.index', $filters + ['search' => $old->reference_number]))->assertOk()
                ->assertSee('No '.strtolower($type).' payments match the selected filters.');
        }
    }

    public function test_admin_history_labels_the_saved_dates_and_linked_installment_periods(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Payment Date Consumer', 'account_number' => 'PAY-DATES',
            'meter_number' => 'PAY-DATES-METER', 'status' => 'Active',
        ]);
        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id, 'billing_period' => '2026-10-01', 'due_date' => '2026-10-30',
            'prn' => 'PAY-DATES-WATER', 'amount' => 100, 'status' => 'Unpaid',
        ]);
        $water = Payment::query()->create([
            'bill_id' => $bill->id, 'consumer_id' => $consumer->id, 'reference_number' => 'DATES-VERIFIED',
            'amount' => 25, 'payment_date' => '2026-11-14', 'payment_method' => 'Cash', 'payment_channel' => 'Counter',
            'status' => 'Verified', 'verified_at' => '2026-11-17 01:30:00', 'verified_by' => $admin->id,
        ]);
        $pending = Payment::query()->create([
            'bill_id' => $bill->id, 'consumer_id' => $consumer->id, 'reference_number' => 'DATES-PENDING',
            'amount' => 10, 'payment_date' => '2026-10-20', 'payment_method' => 'GCash', 'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);
        $pending->forceFill(['created_at' => '2026-11-17 03:45:00'])->save();

        $waterFilters = ['view' => 'water-payments', 'year' => 2026, 'month' => 11];
        $this->actingAs($admin)->get(route('payments.index', $waterFilters + ['status' => 'Paid']))
            ->assertOk()->assertSeeInOrder(['Billing Period', 'Payment Date', 'Oct 2026', 'Nov 14, 2026', 'Verified Date', 'Nov 17, 2026 · 9:30 AM'])
            ->assertDontSee('<th scope="col">Date</th>', false);
        $this->get(route('payments.index', $waterFilters + ['status' => 'Pending Payment']))
            ->assertOk()->assertSeeInOrder(['Billing Period', 'Payment Date', 'Oct 2026', 'Oct 20, 2026', 'Submission Date', 'Nov 17, 2026 · 11:45 AM'])
            ->assertSee('data-billing-periods="Oct 2026"', false)
            ->assertSee('data-submission-date="Nov 17, 2026 · 11:45 AM"', false)
            ->assertDontSee('Verified Date');
        $this->get(route('payments.receipt', ['payment' => $water, 'embed' => 1]))
            ->assertOk()->assertSeeInOrder(['Verified Date', 'Nov 17, 2026 · 9:30 AM', 'Payment Date', 'Nov 14, 2026']);

        $loan = Loan::query()->create([
            'consumer_id' => $consumer->id, 'amount' => 100, 'term_months' => 3, 'status' => 'Completed',
            'purpose' => 'Meter installation', 'monthly_installment' => 33, 'applied_at' => '2026-11-01',
        ]);
        $loanPayment = Payment::query()->create([
            'loan_id' => $loan->id, 'consumer_id' => $consumer->id, 'payment_type' => 'Loan',
            'reference_number' => 'DATES-LOAN', 'amount' => 50, 'payment_date' => '2026-12-20',
            'payment_method' => 'Cash', 'payment_channel' => 'Counter', 'status' => 'Verified',
            'verified_at' => '2026-12-20 19:30:00', 'verified_by' => $admin->id,
        ]);
        $repayment = LoanRepayment::query()->create([
            'loan_id' => $loan->id, 'payment_id' => $loanPayment->id, 'amount' => 50, 'payment_date' => '2026-12-20',
        ]);
        foreach ([2, 1] as $installmentNumber) {
            $installment = LoanInstallment::query()->create([
                'loan_id' => $loan->id, 'installment_number' => $installmentNumber,
                'billing_period' => '2027-0'.$installmentNumber.'-01', 'due_date' => '2027-0'.$installmentNumber.'-15',
                'required_amount' => 33, 'amount_paid' => 25, 'remaining_balance' => 8, 'status' => 'Partially Paid',
            ]);
            $repayment->allocations()->create([
                'loan_installment_id' => $installment->id, 'amount' => 25, 'installment_balance_after' => 8,
            ]);
        }

        $this->get(route('payments.index', ['view' => 'loan-payments', 'year' => 2026, 'month' => 12]))
            ->assertOk()->assertSeeInOrder([
                'Loan Reference', 'Billing Period', 'Payment Date', 'Loan #'.$loan->id, 'Jan 2027', 'Feb 2027',
                'Dec 20, 2026', 'Verified Date', 'Dec 21, 2026 · 3:30 AM',
            ])
            ->assertDontSee('<th scope="col">Date</th>', false);
        $this->assertSame('2026-12-20 19:30:00', $loanPayment->fresh()->getRawOriginal('verified_at'));
    }
}
