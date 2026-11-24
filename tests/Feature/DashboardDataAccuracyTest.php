<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Incident;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_actual_open_balances_and_all_carried_balances(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $previousBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->subMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'DASH-PREV',
            'amount' => 450.00,
            'arrears_amount' => 0.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $previousBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'DASH-PAY-PREV',
            'amount' => 200.00,
            'payment_date' => now()->startOfMonth()->subMonth()->day(20)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'DASH-CURRENT',
            'amount' => 665.00,
            'arrears_amount' => 250.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => Bill::query()->where('prn', 'DASH-CURRENT')->firstOrFail()->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'DASH-PAY-CURRENT',
            'amount' => 410.00,
            'payment_date' => now()->startOfMonth()->day(9)->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();

        $dashboard = $response->viewData('dashboard');

        $this->assertEquals(505.00, $dashboard['outstanding']['total']);
        $this->assertEquals(1, $dashboard['outstanding']['accounts']);
        $this->assertEquals(415.00, $dashboard['financial']['new_charges']);
        $this->assertEquals(665.00, $dashboard['financial']['gross_billed']);
        $this->assertEquals(250.00, $dashboard['financial']['carry_forward']);
        $this->assertEquals(410.00, $dashboard['financial']['collected']);
    }

    public function test_consumer_dashboard_uses_total_open_balance_not_only_latest_bill(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->subMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'DASH-CONS-PREV',
            'amount' => 250.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'DASH-CONS-CURRENT',
            'amount' => 255.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        $this->assertEquals(505.00, $response->viewData('billingSummary')['outstanding_balance']);
    }

    public function test_consumer_dashboard_bills_and_payments_share_carried_forward_balance_total(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'John Ver Navarra',
            'account_number' => 'JVN-2233',
            'meter_number' => 'MT-2233',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $previousBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->subMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'SYNC-PREV',
            'amount' => 450.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $previousBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'SYNC-PREV-PAY',
            'amount' => 200.00,
            'payment_date' => now()->startOfMonth()->subMonth()->day(28)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $currentBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'SYNC-CURRENT',
            'amount' => 665.00,
            'arrears_amount' => 250.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        // The current bill carries the previous bill's remaining 250, so the
        // previous period is closed - that debt now lives on one bill only.
        $previousBill->forceFill([
            'carried_forward_to_bill_id' => $currentBill->id,
            'status' => 'Paid',
            'months_unpaid' => 0,
        ])->save();

        Payment::query()->create([
            'bill_id' => $currentBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'SYNC-CURRENT-PAY',
            'amount' => 410.00,
            'payment_date' => now()->startOfMonth()->day(9)->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $dashboard = $this->actingAs($user)->get(route('dashboard'));
        $dashboard->assertOk();
        $this->assertEquals(255.00, $dashboard->viewData('billingSummary')['outstanding_balance']);

        $bills = $this->actingAs($user)->get(route('billing.index'));
        $bills->assertOk();
        $this->assertEquals(255.00, $bills->viewData('consumerTotalBalanceDue'));

        $payments = $this->actingAs($user)->get(route('payments.index'));
        $payments->assertOk();
        $this->assertEquals(255.00, $payments->viewData('availableBills')->sum('balance_due'));
    }

    public function test_admin_dashboard_incident_categories_come_from_actual_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00'));

        try {
            $admin = User::factory()->create(['role' => 'admin']);

            $categories = [
                'Water Leak',
                'Low Water Pressure',
                'Water Quality Issue',
                'Meter Concern',
                'No Water Supply',
                'Illegal Connection',
                'Billing Concern',
            ];

            foreach ($categories as $index => $category) {
                Incident::query()->create([
                    'reported_by' => 'Dashboard Reporter '.$index,
                    'category' => $category,
                    'location' => 'Purok 1-A',
                    'description' => 'Dashboard complaint category test.',
                    'status' => 'Pending',
                    'reported_at' => now()->subDays($index),
                ]);
            }

            $response = $this->actingAs($admin)->get(route('dashboard'));

            $response->assertOk();

            $dashboard = $response->viewData('dashboard');

            $this->assertSame(7, $dashboard['incidents']['total']);
            $this->assertCount(7, $dashboard['incidents']['categories']);

            $reportedCategories = collect($dashboard['incidents']['categories'])->pluck('label')->all();

            foreach ($categories as $category) {
                $this->assertContains($category, $reportedCategories);
            }

            $response
                ->assertSee('Incidents by Category')
                ->assertSee('No Water Supply')
                ->assertSee('Illegal Connection')
                ->assertSee('Billing Concern');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_dashboard_defaults_to_current_month_and_uses_actual_incident_categories(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-04 10:00:00'));

        try {
            $admin = User::factory()->create(['role' => 'admin']);

            foreach (range(1, 5) as $index) {
                Incident::query()->create([
                    'reported_by' => 'August Reporter '.$index,
                    'category' => 'Water Leak',
                    'location' => 'Purok 1-A',
                    'description' => 'August Purok 1-A report.',
                    'status' => $index === 5 ? 'Resolved' : 'Pending',
                    'reported_at' => '2026-08-'.$index,
                ]);
            }

            foreach (range(1, 5) as $index) {
                Incident::query()->create([
                    'reported_by' => 'August Low Pressure Reporter '.$index,
                    'category' => 'Low Water Pressure',
                    'location' => 'Purok 2-B',
                    'description' => 'August low pressure report.',
                    'status' => $index === 3 ? 'Resolved' : 'Pending',
                    'reported_at' => '2026-08-2'.$index,
                ]);
            }

            foreach (range(1, 4) as $index) {
                Incident::query()->create([
                    'reported_by' => 'August Purok 1-B Reporter '.$index,
                    'category' => 'Water Quality Issue',
                    'location' => 'Purok 1-B',
                    'description' => 'August Purok 1-B report.',
                    'status' => 'Pending',
                    'reported_at' => '2026-08-1'.$index,
                ]);
            }

            Incident::query()->create([
                'reported_by' => 'July Reporter',
                'category' => 'Low Water Pressure',
                'location' => 'Purok 2-A',
                'description' => 'Historical report.',
                'status' => 'Resolved',
                'reported_at' => '2026-07-10',
            ]);

            Incident::query()->create([
                'reported_by' => 'Legacy Reporter',
                'category' => 'Billing Concern',
                'location' => 'Via Vida',
                'description' => 'Legacy invalid location report.',
                'status' => 'Pending',
                'reported_at' => '2026-08-20',
            ]);

            $response = $this->actingAs($admin)->get(route('dashboard'));

            $response->assertOk();

            $dashboard = $response->viewData('dashboard');
            $categories = collect($dashboard['incidents']['categories'])->keyBy('label');

            $this->assertSame(15, $dashboard['incidents']['total']);
            $this->assertSame(13, $dashboard['incidents']['open']);
            $this->assertSame(2, $dashboard['incidents']['resolved']);
            $this->assertSame('Low Water Pressure', $dashboard['incidents']['top_category']);
            $this->assertSame(5, $categories['Water Leak']['total']);
            $this->assertSame(5, $categories['Low Water Pressure']['total']);
            $this->assertSame(4, $categories['Water Quality Issue']['total']);
            $this->assertSame(1, $categories['Billing Concern']['total']);
            $this->assertSame(['Pending', 'Resolved'], collect($dashboard['incidents']['status_labels'])->filter(
                fn (string $status, int $index): bool => ($dashboard['incidents']['status_counts'][$index] ?? 0) > 0,
            )->values()->all());

            $response
                ->assertSee('Showing August 2026')
                ->assertSee('<option value="2026" selected>2026</option>', false)
                ->assertSee('<option value="8" selected>August</option>', false)
                ->assertSee('<option value="">All Months</option>', false)
                ->assertSee('Billing Concern')
                ->assertSee('Low Water Pressure')
                ->assertSee('Water Leak')
                ->assertSee('Water Quality Issue');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_dashboard_period_filter_updates_period_metrics_and_keeps_current_snapshots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00'));

        try {
            $admin = User::factory()->create(['role' => 'admin']);
            $consumer = Consumer::query()->create([
                'full_name' => 'Period Filter Resident',
                'account_number' => 'PERIOD-0001',
                'meter_number' => 'PERIOD-MTR-0001',
                'status' => 'Active',
                'membership_type' => 'Non-Member',
                'service_purok' => 'Purok 1',
            ]);

            $secondConsumer = Consumer::query()->create([
                'full_name' => 'Second Period Resident',
                'account_number' => 'PERIOD-0002',
                'meter_number' => 'PERIOD-MTR-0002',
                'status' => 'Active',
                'membership_type' => 'Non-Member',
                'service_purok' => 'Purok 1',
            ]);

            $septemberBill = Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => '2026-09-01',
                'due_date' => '2026-09-30',
                'prn' => 'PERIOD-SEP-BILL',
                'amount' => 500,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);

            Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => '2026-10-01',
                'due_date' => '2026-10-31',
                'prn' => 'PERIOD-OCT-BILL',
                'amount' => 700,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);

            Payment::query()->create([
                'bill_id' => $septemberBill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => 'PERIOD-SEP-PAY',
                'amount' => 200,
                'payment_date' => '2026-09-10',
                'payment_method' => 'Cash',
                'payment_channel' => 'Counter',
                'status' => 'Verified',
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            Payment::query()->create([
                'bill_id' => $septemberBill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => 'PERIOD-OCT-PAY',
                'amount' => 300,
                'payment_date' => '2026-10-15',
                'payment_method' => 'Cash',
                'payment_channel' => 'Counter',
                'status' => 'Verified',
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            MeterReading::query()->create([
                'consumer_id' => $consumer->id,
                'reader_id' => $admin->id,
                'reading_date' => '2026-09-12',
                'previous_reading' => 100,
                'current_reading' => 200,
                'consumption' => 100,
                'status' => 'Encoded',
            ]);

            MeterReading::query()->create([
                'consumer_id' => $consumer->id,
                'reader_id' => $admin->id,
                'reading_date' => '2026-10-12',
                'previous_reading' => 200,
                'current_reading' => 320,
                'consumption' => 120,
                'status' => 'Encoded',
            ]);

            Incident::query()->create([
                'reported_by' => 'September Reporter',
                'category' => 'Water Leak',
                'location' => 'Purok 1',
                'description' => 'September leak.',
                'status' => 'Resolved',
                'reported_at' => '2026-09-14',
            ]);

            Incident::query()->create([
                'reported_by' => 'October Reporter One',
                'category' => 'Low Water Pressure',
                'location' => 'Purok 1',
                'description' => 'October pressure issue.',
                'status' => 'Pending',
                'reported_at' => '2026-10-05',
            ]);

            Incident::query()->create([
                'reported_by' => 'October Reporter Two',
                'category' => 'Meter Concern',
                'location' => 'Purok 1',
                'description' => 'October meter issue.',
                'status' => 'Ongoing',
                'reported_at' => '2026-10-16',
            ]);

            $septemberLoan = Loan::query()->create([
                'consumer_id' => $consumer->id,
                'applied_by_user_id' => $admin->id,
                'amount' => 1000,
                'term_months' => 6,
                'purpose' => 'September application',
                'status' => 'Approved',
                'monthly_installment' => 100,
                'applied_at' => '2026-09-05 10:00:00',
            ]);

            $octoberLoan = Loan::query()->create([
                'consumer_id' => $secondConsumer->id,
                'applied_by_user_id' => $admin->id,
                'amount' => 2000,
                'term_months' => 6,
                'purpose' => 'October application',
                'status' => 'Pending Review',
                'monthly_installment' => 200,
                'applied_at' => '2026-10-06 10:00:00',
            ]);

            LoanRepayment::query()->create([
                'loan_id' => $septemberLoan->id,
                'recorded_by_user_id' => $admin->id,
                'amount' => 50,
                'payment_date' => '2026-09-20',
            ]);

            LoanRepayment::query()->create([
                'loan_id' => $octoberLoan->id,
                'recorded_by_user_id' => $admin->id,
                'amount' => 75,
                'payment_date' => '2026-10-20',
            ]);

            $september = $this->actingAs($admin)->get(route('dashboard', [
                'month' => 9,
                'year' => 2026,
            ]));

            $september->assertOk()
                ->assertSee('data-auto-filter', false)
                ->assertSee('id="dashboard-month"', false)
                ->assertDontSee('bi-funnel', false);
            $septemberDashboard = $september->viewData('dashboard');

            $this->assertSame(500.00, $septemberDashboard['financial']['new_charges']);
            $this->assertSame(200.00, $septemberDashboard['financial']['collected']);
            $this->assertSame(100.00, $septemberDashboard['consumption']['total']);
            $this->assertSame(1, $septemberDashboard['incidents']['total']);
            $this->assertSame(1, $septemberDashboard['loans']['applications']);
            $this->assertSame(50.00, $septemberDashboard['loans']['repayments']);
            $this->assertSame(300.00, $septemberDashboard['outstanding']['total']);
            $this->assertSame(2, $septemberDashboard['accounts']['total']);
            $this->assertSame(0, $septemberDashboard['accounts']['new_accounts']);
            $this->assertSame(8, $septemberDashboard['charts']['financial']['selected_index']);
            $this->assertSame(500.00, $septemberDashboard['charts']['financial']['billed'][8]);
            $this->assertSame(700.00, $septemberDashboard['charts']['financial']['billed'][9]);
            $this->assertSame(200.00, $septemberDashboard['charts']['financial']['collected'][8]);
            $this->assertSame(300.00, $septemberDashboard['charts']['financial']['collected'][9]);
            $this->assertSame(100.00, $septemberDashboard['charts']['consumption']['totals'][8]);
            $this->assertSame(120.00, $septemberDashboard['charts']['consumption']['totals'][9]);
            $this->assertSame(1, $septemberDashboard['charts']['loans']['applications'][8]);
            $this->assertSame(1, $septemberDashboard['charts']['loans']['applications'][9]);

            $allMonths = $this->actingAs($admin)->get(route('dashboard', [
                'month' => '',
                'year' => 2026,
            ]));

            $allMonths->assertOk();
            $allMonthsDashboard = $allMonths->viewData('dashboard');

            $this->assertNull($allMonths->viewData('period')->month);
            $this->assertSame(1200.00, $allMonthsDashboard['financial']['new_charges']);
            $this->assertSame(500.00, $allMonthsDashboard['financial']['collected']);
            $this->assertSame(220.00, $allMonthsDashboard['consumption']['total']);
            $this->assertSame(3, $allMonthsDashboard['incidents']['total']);
            $this->assertSame(2, $allMonthsDashboard['loans']['applications']);
            $this->assertSame(125.00, $allMonthsDashboard['loans']['repayments']);
            $this->assertSame(700.00, $allMonthsDashboard['outstanding']['total']);
            $this->assertSame(2, $allMonthsDashboard['accounts']['total']);

            $emptyMonth = $this->actingAs($admin)->get(route('dashboard', [
                'month' => 1,
                'year' => 2026,
            ]));

            $emptyMonth->assertOk();
            $emptyMonthDashboard = $emptyMonth->viewData('dashboard');

            $this->assertSame(0.0, $emptyMonthDashboard['financial']['new_charges']);
            $this->assertTrue($emptyMonthDashboard['charts']['financial']['has_data']);
            $this->assertTrue($emptyMonthDashboard['charts']['consumption']['has_data']);
            $this->assertTrue($emptyMonthDashboard['charts']['loans']['has_data']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_dashboard_outstanding_snapshot_respects_cutoff_and_counts_carried_balance_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 10:00:00'));

        try {
            $admin = User::factory()->create(['role' => 'admin']);
            $consumer = Consumer::query()->create([
                'full_name' => 'Cutoff Resident',
                'account_number' => 'CUTOFF-0001',
                'meter_number' => 'CUTOFF-MTR-0001',
                'status' => 'Active',
                'membership_type' => 'Non-Member',
            ]);

            $septemberBill = Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => '2026-09-01',
                'due_date' => '2026-09-30',
                'prn' => 'CUTOFF-SEP-BILL',
                'amount' => 500,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);

            $octoberBill = Bill::query()->create([
                'consumer_id' => $consumer->id,
                'billing_period' => '2026-10-01',
                'due_date' => '2026-10-31',
                'prn' => 'CUTOFF-OCT-BILL',
                'amount' => 1200,
                'arrears_amount' => 500,
                'status' => 'Unpaid',
                'months_unpaid' => 0,
            ]);

            $septemberBill->forceFill(['carried_forward_to_bill_id' => $octoberBill->id])->save();

            Payment::query()->create([
                'bill_id' => $octoberBill->id,
                'consumer_id' => $consumer->id,
                'reference_number' => 'CUTOFF-OCT-PAY',
                'amount' => 200,
                'payment_date' => '2026-10-11',
                'payment_method' => 'Cash',
                'payment_channel' => 'Counter',
                'status' => 'Verified',
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            $september = $this->actingAs($admin)->get(route('dashboard', [
                'month' => 9,
                'year' => 2026,
            ]));

            $september->assertOk();
            $this->assertSame(500.00, $september->viewData('dashboard')['outstanding']['total']);
            $this->assertSame(1, $september->viewData('dashboard')['outstanding']['open_bills']);

            $october = $this->actingAs($admin)->get(route('dashboard', [
                'month' => 10,
                'year' => 2026,
            ]));

            $october->assertOk();
            $this->assertSame(1000.00, $october->viewData('dashboard')['outstanding']['total']);
            $this->assertSame(1, $october->viewData('dashboard')['outstanding']['open_bills']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_consumer_full_bill_list_uses_payment_deadline_labels_without_due_badge(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Full Bill List Consumer',
            'account_number' => 'FBLC-1001',
            'meter_number' => 'FBLC-MTR-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-18',
            'prn' => 'FULL-LIST-LABELS',
            'amount' => 934.20,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Full bill list');
        $response->assertDontSee('Recent consumption and charges');
        $response->assertDontSee('Latest available record');
        $response->assertSee('FULL-LIST-LABELS');
        $response->assertSee('View Statement');
        $response->assertSee('Total Amount');
        $response->assertSee('Payment Deadline');
        $response->assertSee('Aug 18, 2026');
        $response->assertDontSee('Total Amount Due');
        $response->assertDontSee('Due Date');
        $this->assertStringNotContainsString('>Due</span>', $response->getContent());
    }

    public function test_admin_billing_filter_form_filters_by_status_and_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = Consumer::query()->create([
            'full_name' => 'Admin Billing Filter Consumer',
            'account_number' => 'ABF-1001',
            'meter_number' => 'ABF-M-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'ADMIN-FILTER-UNPAID',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $paid = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonth()->startOfMonth()->toDateString(),
            'due_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'prn' => 'ADMIN-FILTER-PAID',
            'amount' => 450,
            'status' => 'Paid',
            'months_unpaid' => 0,
        ]);
        Payment::query()->create([
            'bill_id' => $paid->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'ADMIN-FILTER-VERIFIED',
            'amount' => $paid->amount,
            'payment_date' => $paid->due_date,
            'payment_method' => 'Cash',
            'status' => 'Verified',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'due_date' => now()->subMonths(2)->endOfMonth()->toDateString(),
            'prn' => 'ADMIN-FILTER-PARTIAL',
            'amount' => 350,
            'status' => 'Paid with Balance',
            'months_unpaid' => 0,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => now()->subMonths(3)->startOfMonth()->toDateString(),
            'due_date' => now()->subMonths(3)->endOfMonth()->toDateString(),
            'prn' => 'ADMIN-FILTER-OVERDUE',
            'amount' => 300,
            'status' => 'Overdue',
            'months_unpaid' => 1,
        ]);

        $all = $this->actingAs($admin)->get(route('billing.index'));
        $all->assertOk()
            ->assertDontSee('Select Status')
            ->assertSee('Admin Billing Filter Consumer')
            ->assertSee('View Monthly Billings')
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="billing_filter_status"', false)
            ->assertDontSee('bi-funnel', false);
        $this->get(route('billing.monthly-history', $consumer))->assertOk()
            ->assertSee('ADMIN-FILTER-PAID')
            ->assertSee('ADMIN-FILTER-PARTIAL')
            ->assertSee('ADMIN-FILTER-UNPAID')
            ->assertSee('ADMIN-FILTER-OVERDUE');

        $statusFilters = [
            'Paid' => 'ADMIN-FILTER-PAID',
            'Paid with Balance' => 'ADMIN-FILTER-PARTIAL',
            'Unpaid' => 'ADMIN-FILTER-UNPAID',
            'Overdue' => 'ADMIN-FILTER-OVERDUE',
        ];

        foreach ($statusFilters as $status => $expectedPrn) {
            $statusResponse = $this->actingAs($admin)->get(route('billing.index', ['status' => $status]));

            $statusResponse->assertOk()
                ->assertSee('Admin Billing Filter Consumer')
                ->assertSee('Clear Filters')
                ->assertSee('data-auto-filter', false)
                ->assertSee('id="billing_filter_status"', false)
                ->assertDontSee('bi-funnel', false);

            $history = $this->get(route('billing.monthly-history', ['consumer' => $consumer, 'status' => $status]))
                ->assertOk()->assertSee($expectedPrn);
            $expected = $status === 'Unpaid'
                ? ['ADMIN-FILTER-UNPAID', 'ADMIN-FILTER-PARTIAL', 'ADMIN-FILTER-OVERDUE']
                : [$expectedPrn];
            $this->assertSame($expected, $history->viewData('records')->getCollection()->pluck('item.prn')->all());
        }

        $filters = ['search' => 'abf-1001', 'status' => 'Unpaid'];
        $search = $this->actingAs($admin)->get(route('billing.index', $filters));
        $search->assertOk()
            ->assertSee('Admin Billing Filter Consumer')
            ->assertSee('View Monthly Billings');
        $this->get(route('billing.monthly-history', ['consumer' => $consumer] + $filters))->assertOk()
            ->assertSee('ADMIN-FILTER-UNPAID')
            ->assertDontSee('ADMIN-FILTER-PAID');
    }

    public function test_consumer_billing_records_filter_by_billing_month_and_year(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Filtered Billing Consumer',
            'account_number' => 'FBC-1001',
            'meter_number' => 'FBC-MTR-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $otherConsumer = Consumer::query()->create([
            'full_name' => 'Other Billing Consumer',
            'account_number' => 'OBC-1001',
            'meter_number' => 'OBC-MTR-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-18',
            'prn' => 'FILTER-AUG-2026',
            'amount' => 800.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-07-01',
            'due_date' => '2026-07-18',
            'prn' => 'FILTER-JUL-2026',
            'amount' => 700.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Bill::query()->create([
            'consumer_id' => $otherConsumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-18',
            'prn' => 'FILTER-OTHER-AUG-2026',
            'amount' => 900.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $august = $this->actingAs($user)->get(route('billing.index', ['billing_period' => '2026-08']));
        $august->assertOk();
        $this->assertSame('2026-08', $august->viewData('selectedBillingPeriod'));
        $this->assertSame(['2026-08'], $august->viewData('filteredBills')->getCollection()->map(fn (Bill $bill) => $bill->billing_period->format('Y-m'))->unique()->values()->all());
        $august->assertSee('August 2026');
        $august->assertSee('FILTER-AUG-2026');
        $august->assertDontSee('July 2026');
        $august->assertDontSee('FILTER-JUL-2026');
        $august->assertDontSee('FILTER-OTHER-AUG-2026');
        $august->assertSee('id="consumer-billing-year" name="year"', false);
        $august->assertSee('<option value="2026" selected', false);
        $august->assertSee('<option value="8" selected', false);
        $august->assertSee('/billing/'.$august->viewData('filteredBills')->first()->id.'?billing_period=2026-08', false);
        $august->assertSee('Show All');
        $august->assertDontSee('<i class="bi bi-search"></i> Apply', false);
        $august->assertSee('data-auto-filter', false);
        $august->assertSee('data-auto-filter-debounce="450"', false);
        $august->assertDontSee('applyBillingPeriodFilter', false);

        $refresh = $this->actingAs($user)->get('/billing?billing_period=2026-08');
        $refresh->assertOk();
        $this->assertSame('2026-08', $refresh->viewData('selectedBillingPeriod'));
        $this->assertSame(['2026-08'], $refresh->viewData('filteredBills')->getCollection()->map(fn (Bill $bill) => $bill->billing_period->format('Y-m'))->unique()->values()->all());
        $refresh->assertSee('<option value="2026" selected', false);
        $refresh->assertSee('<option value="8" selected', false);
        $refresh->assertSee('FILTER-AUG-2026');
        $refresh->assertDontSee('FILTER-JUL-2026');

        $july = $this->actingAs($user)->get(route('billing.index', ['billing_period' => '2026-07']));
        $july->assertOk();
        $this->assertSame('2026-07', $july->viewData('selectedBillingPeriod'));
        $this->assertSame(['2026-07'], $july->viewData('filteredBills')->getCollection()->map(fn (Bill $bill) => $bill->billing_period->format('Y-m'))->unique()->values()->all());
        $july->assertSee('July 2026');
        $july->assertSee('FILTER-JUL-2026');
        $july->assertDontSee('August 2026');
        $july->assertDontSee('FILTER-AUG-2026');

        $empty = $this->actingAs($user)->get(route('billing.index', ['billing_period' => '2026-06']));
        $empty->assertOk();
        $this->assertSame('2026-06', $empty->viewData('selectedBillingPeriod'));
        $this->assertCount(0, $empty->viewData('filteredBills')->getCollection());
        $empty->assertSee('No billing record found for the selected month.');
        $empty->assertDontSee('FILTER-AUG-2026');
        $empty->assertDontSee('FILTER-JUL-2026');

        $showAll = $this->actingAs($user)->get(route('billing.index'));
        $showAll->assertOk();
        $this->assertSame('', $showAll->viewData('selectedBillingPeriod'));
        $this->assertSame(['2026-08', '2026-07'], $showAll->viewData('filteredBills')->getCollection()->map(fn (Bill $bill) => $bill->billing_period->format('Y-m'))->values()->all());
        $showAll->assertSee('FILTER-AUG-2026');
        $showAll->assertSee('FILTER-JUL-2026');
        $showAll->assertDontSee('Show All');

        $yearOnly = $this->get(route('billing.index', ['year' => 2026]))->assertOk();
        $this->assertSame(2, $yearOnly->viewData('filteredBills')->total());
        $this->get(route('billing.index', ['year' => '', 'month' => 8]))->assertOk()
            ->assertSee('FILTER-AUG-2026')->assertDontSee('FILTER-JUL-2026');
    }

    public function test_consumer_billing_statement_preserves_selected_billing_filter_on_back_link(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $consumer = Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Statement Filter Consumer',
            'account_number' => 'SFC-1001',
            'meter_number' => 'SFC-MTR-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-07-01',
            'due_date' => '2026-07-18',
            'prn' => 'STMT-FILTER-JUL-2026',
            'amount' => 500.00,
            'status' => 'Paid with Balance',
            'months_unpaid' => 0,
        ]);

        $bill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-08-01',
            'due_date' => '2026-08-18',
            'prn' => 'STMT-FILTER-AUG-2026',
            'amount' => 800.00,
            'arrears_amount' => 210.00,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('billing.show', [
            'bill' => $bill,
            'billing_period' => '2026-08',
        ]));

        $response->assertOk();
        $this->assertSame('2026-08', $response->viewData('selectedBillingPeriod'));
        $response->assertSee('/billing?billing_period=2026-08', false);
        $response->assertSee('Previous Balance (July 2026)');
        $response->assertSee('P 210.00');
        $response->assertDontSee('Arrears Carryover');

        $this->get(route('billing.show', ['bill' => $bill, 'year' => 2026]))->assertOk()
            ->assertSee('/billing?year=2026', false);
    }

    public function test_consumer_billing_records_reject_invalid_billing_period_filter(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        Consumer::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Invalid Filter Consumer',
            'account_number' => 'IFC-1001',
            'meter_number' => 'IFC-MTR-1001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->get(route('billing.index', ['billing_period' => 'August 2026']))
            ->assertSessionHasErrors('billing_period');
    }
}
