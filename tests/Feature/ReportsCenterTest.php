<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Incident;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportsCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Consumer $consumer;

    private Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00:00'));

        $this->admin = User::factory()->create([
            'name' => 'Reports Admin',
            'role' => 'admin',
        ]);

        $this->consumer = Consumer::query()->create([
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'account_number' => 'ACCT-0001',
            'meter_number' => 'MTR-0001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'service_purok' => 'Purok 1',
            'contact_number' => '09170000000',
        ]);

        $reading = MeterReading::query()->create([
            'consumer_id' => $this->consumer->id,
            'meter_number' => 'MTR-0001',
            'reader_id' => $this->admin->id,
            'reading_date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'previous_reading' => 100,
            'current_reading' => 112,
            'consumption' => 12,
            'status' => 'Encoded',
            'remarks' => 'Regular reading',
        ]);

        $this->bill = Bill::query()->create([
            'consumer_id' => $this->consumer->id,
            'meter_reading_id' => $reading->id,
            'billing_period' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'prn' => 'PRN-2026-10-0001',
            'amount' => 500,
            'base_charge' => 150,
            'water_charge' => 350,
            'arrears_amount' => 0,
            'penalty_amount' => 0,
            'loan_installment_amount' => 0,
            'units_consumed' => 12,
            'unit_rate' => 20,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $this->bill->id,
            'consumer_id' => $this->consumer->id,
            'reference_number' => 'PAY-CURRENT-001',
            'receipt_number' => 'OR-0001',
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        Payment::query()->create([
            'bill_id' => $this->bill->id,
            'consumer_id' => $this->consumer->id,
            'reference_number' => 'PAY-PREVIOUS-001',
            'amount' => 200,
            'payment_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(4)->toDateString(),
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now()->subMonthNoOverflow(),
            'verified_by' => $this->admin->id,
        ]);

        Payment::query()->create([
            'bill_id' => $this->bill->id,
            'consumer_id' => $this->consumer->id,
            'reference_number' => 'PAY-PENDING-001',
            'amount' => 50,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'GCash',
            'payment_channel' => 'Online',
            'status' => 'Pending',
        ]);

        Incident::query()->create([
            'consumer_id' => $this->consumer->id,
            'reported_by' => 'Maria Santos',
            'category' => 'Water Leak',
            'location' => 'Purok 1',
            'description' => 'Water leaking near the meter.',
            'status' => 'Pending',
            'reported_at' => now()->subDay(),
        ]);

        $loan = Loan::query()->create([
            'consumer_id' => $this->consumer->id,
            'applied_by_user_id' => $this->admin->id,
            'amount' => 1200,
            'term_months' => 12,
            'purpose' => 'Meter installation',
            'material_meter_cost' => 500,
            'material_hose_cost' => 100,
            'material_other_cost' => 50,
            'installation_cost_total' => 650,
            'status' => 'Approved',
            'review_status' => 'Active',
            'installation_status' => 'Completed',
            'monthly_installment' => 100,
            'applied_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'next_due_date' => now()->addMonth()->toDateString(),
        ]);

        LoanRepayment::query()->create([
            'loan_id' => $loan->id,
            'recorded_by_user_id' => $this->admin->id,
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'notes' => 'First installment',
        ]);

        ServiceRequest::query()->create([
            'consumer_id' => $this->consumer->id,
            'request_type' => ServiceRequest::TYPE_NEW_CONNECTION,
            'status' => ServiceRequest::STATUS_PENDING,
            'information' => 'Initial connection request',
            'requested_at' => now()->subDay(),
        ]);

        $reconnected = Consumer::query()->create([
            'full_name' => 'Reconnected Resident',
            'account_number' => 'ACCT-0002',
            'meter_number' => 'MTR-0002',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'service_purok' => 'Purok 2',
        ]);

        $reconnected->forceFill(['status' => 'Disconnected'])->save();
        $reconnected->forceFill(['status' => 'Active'])->save();

        ServiceRequest::query()->create([
            'consumer_id' => $reconnected->id,
            'request_type' => ServiceRequest::TYPE_RECONNECTION,
            'status' => ServiceRequest::STATUS_COMPLETED,
            'current_balance_snapshot' => 0,
            'requested_at' => now()->subDays(3),
            'reviewed_at' => now()->toDateString(),
            'reviewed_by_user_id' => $this->admin->id,
        ]);

        $legacyDisconnected = Consumer::query()->create([
            'full_name' => 'Legacy Disconnected Resident',
            'account_number' => 'ACCT-0003',
            'meter_number' => 'MTR-0003',
            'status' => 'Disconnected',
            'membership_type' => 'Non-Member',
            'service_purok' => 'Purok 3',
        ]);

        // Simulate accounts disconnected before the new timestamp existed.
        $legacyDisconnected->forceFill(['disconnected_at' => null])->saveQuietly();
    }

    public function test_admin_can_open_every_report_in_the_catalog(): void
    {
        $this->actingAs($this->admin);

        $reports = app(ReportService::class)->definitions();

        $this->assertCount(12, $reports);

        foreach ($reports as $key => $definition) {
            $response = $this->get(route('reports.index', [
                'report' => $key,
                'month' => 10,
                'year' => 2026,
            ]));

            $response->assertOk();
            $response->assertSee($definition->label);
            $response->assertSee('PDF');
            $response->assertSee('Excel');
            $response->assertSee('CSV');
        }
    }

    public function test_reports_center_filters_records_by_month_and_year(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.index', [
            'report' => 'collections',
            'month' => 10,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('PAY-CURRENT-001');
        $response->assertDontSee('PAY-PREVIOUS-001');
        $response->assertSee('P 100.00');
    }

    public function test_secondary_filters_apply_to_the_displayed_records(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.index', [
            'report' => 'payment-monitoring',
            'month' => 10,
            'year' => 2026,
            'status' => 'Pending',
            'channel' => 'Online',
        ]));

        $response->assertOk();
        $response->assertSee('PAY-PENDING-001');
        $response->assertDontSee('PAY-CURRENT-001');
    }

    public function test_secondary_filters_that_do_not_belong_to_the_report_are_ignored(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.index', [
            'report' => 'payment-monitoring',
            'month' => 10,
            'year' => 2026,
            'status' => 'Unpaid',
        ]));

        $response->assertOk();
        $response->assertSee('PAY-CURRENT-001');
        $response->assertSee('PAY-PENDING-001');
    }

    public function test_csv_export_uses_the_same_month_filter_as_the_screen(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.export', [
            'report' => 'collections',
            'month' => 10,
            'year' => 2026,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload();

        $content = $response->streamedContent();

        $this->assertStringContainsString('PAY-CURRENT-001', $content);
        $this->assertStringNotContainsString('PAY-PREVIOUS-001', $content);
        $this->assertStringContainsString('Payment Date', $content);
    }

    public function test_pdf_and_excel_exports_are_valid_files(): void
    {
        $this->actingAs($this->admin);

        $pdf = $this->get(route('reports.export', [
            'report' => 'billing',
            'month' => 10,
            'year' => 2026,
            'format' => 'pdf',
        ]));

        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->baseResponse->getContent());

        $xlsx = $this->get(route('reports.export', [
            'report' => 'billing',
            'month' => 10,
            'year' => 2026,
            'format' => 'xlsx',
        ]));

        $xlsx->assertOk();
        $xlsx->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $xlsxContent = file_get_contents($xlsx->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith('PK', $xlsxContent);
    }

    public function test_every_report_can_be_exported_to_csv(): void
    {
        $this->actingAs($this->admin);

        foreach (array_keys(app(ReportService::class)->definitions()) as $key) {
            $response = $this->get(route('reports.export', [
                'report' => $key,
                'month' => 10,
                'year' => 2026,
                'format' => 'csv',
            ]));

            $response->assertOk();
            $this->assertNotSame('', trim($response->streamedContent()));
        }
    }

    public function test_reports_keep_automatic_filters_and_show_future_years_in_the_period_picker(): void
    {
        Bill::query()->create([
            'consumer_id' => $this->consumer->id, 'prn' => 'FUTURE-YEAR',
            'billing_period' => '2032-10-01', 'due_date' => '2032-10-25', 'amount' => 100, 'status' => 'Unpaid',
        ]);
        $response = $this->actingAs($this->admin)->get(route('reports.index'));

        $response->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('data-auto-filter-debounce="450"', false)
            ->assertSee('id="report"', false)
            ->assertDontSee('bi-funnel', false)
            ->assertSee('data-period-year', false)
            ->assertSee('data-period-month', false)
            ->assertDontSee('>Apply</button>', false)
            ->assertDontSee('>Cancel</button>', false)
            ->assertSeeInOrder(['<option value="2032"', '<option value="2031"', '<option value="2029"', '<option value="2028"', '<option value="2014"'], false)
            ->assertViewHas('filters', fn (ReportFilters $filters) => $filters->month === 10 && $filters->year === null);

        $this->get(route('reports.index', ['report' => 'billing', 'month' => 10, 'year' => '']))->assertOk()
            ->assertViewHas('filters', fn (ReportFilters $filters) => $filters->month === 10 && $filters->year === null)
            ->assertSee('FUTURE-YEAR');
        $this->get(route('reports.index', ['report' => 'billing', 'month' => 10, 'year' => 2031]))->assertOk()
            ->assertViewHas('filters', fn (ReportFilters $filters) => $filters->month === 10 && $filters->year === 2031)
            ->assertDontSee('FUTURE-YEAR');
    }

    public function test_consumer_cannot_access_the_reports_center(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($consumer)
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($consumer)
            ->get(route('reports.export', [
                'report' => 'collections',
                'format' => 'csv',
            ]))
            ->assertForbidden();
    }

    public function test_connection_status_changes_record_disconnection_and_reconnection_dates(): void
    {
        $consumer = Consumer::query()->create([
            'full_name' => 'Timestamp Resident',
            'account_number' => 'ACCT-0004',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
        ]);

        $consumer->update(['status' => 'Disconnected']);
        $this->assertNotNull($consumer->fresh()->disconnected_at);
        $this->assertNull($consumer->fresh()->reconnected_at);

        $consumer->update(['status' => 'Active']);
        $this->assertNotNull($consumer->fresh()->reconnected_at);
    }

    public function test_legacy_disconnected_accounts_are_visible_in_the_current_period(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('reports.index', [
            'report' => 'disconnected-accounts',
            'month' => 10,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('Legacy Disconnected Resident');
    }

    public function test_all_time_and_all_months_periods_are_preserved_in_links_and_exports(): void
    {
        $this->actingAs($this->admin);

        $allTime = $this->get(route('reports.index', [
            'report' => 'collections',
            'month' => '',
            'year' => '',
        ]));

        $allTime->assertOk();
        $this->assertSame(2, $allTime->viewData('result')->total);
        $allTime->assertSee('PAY-CURRENT-001');
        $allTime->assertSee('PAY-PREVIOUS-001');
        $allTime->assertSee('month=&amp;year=', false);

        $allTimeExport = $this->get(route('reports.export', [
            'report' => 'collections',
            'month' => '',
            'year' => '',
            'format' => 'csv',
        ]));

        $allTimeExport->assertOk();
        $allTimeContent = $allTimeExport->streamedContent();
        $this->assertStringContainsString('PAY-CURRENT-001', $allTimeContent);
        $this->assertStringContainsString('PAY-PREVIOUS-001', $allTimeContent);

        $allMonths = $this->get(route('reports.index', [
            'report' => 'collections',
            'month' => '',
            'year' => 2026,
        ]));

        $allMonths->assertOk();
        $this->assertSame(2, $allMonths->viewData('result')->total);
        $allMonths->assertSee('month=&amp;year=2026', false);

        $allMonthsExport = $this->get(route('reports.export', [
            'report' => 'collections',
            'month' => '',
            'year' => 2026,
            'format' => 'csv',
        ]));

        $allMonthsExport->assertOk();
        $allMonthsContent = $allMonthsExport->streamedContent();
        $this->assertStringContainsString('PAY-CURRENT-001', $allMonthsContent);
        $this->assertStringContainsString('PAY-PREVIOUS-001', $allMonthsContent);
    }

    public function test_all_months_pagination_links_keep_the_explicit_period(): void
    {
        Incident::query()->create([
            'reported_by' => 'Pagination Reporter',
            'category' => 'Water Leak',
            'location' => 'Purok 1',
            'description' => 'Second incident for pagination.',
            'status' => 'Pending',
            'reported_at' => '2026-10-21 09:00:00',
        ]);

        $filters = ReportFilters::fromArray([
            'month' => '',
            'year' => 2026,
        ]);

        $paginator = app(ReportService::class)
            ->paginate('incidents', $filters, page: 1, perPage: 1)
            ->paginator();

        $this->assertGreaterThan(1, $paginator->total());
        $this->assertStringContainsString('month=&year=2026', $paginator->url(2));
    }

    public function test_outstanding_balance_report_respects_payment_cutoff_and_carried_balances(): void
    {
        $consumer = Consumer::query()->create([
            'full_name' => 'Cutoff Report Resident',
            'account_number' => 'CUTOFF-RPT-0001',
            'meter_number' => 'CUTOFF-RPT-MTR-0001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'service_purok' => 'Purok 1',
        ]);

        $septemberBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'CUTOFF-RPT-SEP',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $octoberBill = Bill::query()->create([
            'consumer_id' => $consumer->id,
            'billing_period' => '2026-10-01',
            'due_date' => '2026-10-31',
            'prn' => 'CUTOFF-RPT-OCT',
            'amount' => 1200,
            'arrears_amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        $septemberBill->forceFill(['carried_forward_to_bill_id' => $octoberBill->id])->save();

        Payment::query()->create([
            'bill_id' => $octoberBill->id,
            'consumer_id' => $consumer->id,
            'reference_number' => 'CUTOFF-RPT-PAY',
            'amount' => 200,
            'payment_date' => '2026-10-11',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        $latePayer = Consumer::query()->create([
            'full_name' => 'Late Payer Resident',
            'account_number' => 'LATE-PAY-RPT-0001',
            'meter_number' => 'LATE-PAY-RPT-MTR-0001',
            'status' => 'Active',
            'membership_type' => 'Non-Member',
            'service_purok' => 'Purok 1',
        ]);

        $lateSeptemberBill = Bill::query()->create([
            'consumer_id' => $latePayer->id,
            'billing_period' => '2026-09-01',
            'due_date' => '2026-09-30',
            'prn' => 'LATE-PAY-RPT-SEP',
            'amount' => 500,
            'status' => 'Unpaid',
            'months_unpaid' => 0,
        ]);

        Payment::query()->create([
            'bill_id' => $lateSeptemberBill->id,
            'consumer_id' => $latePayer->id,
            'reference_number' => 'LATE-PAY-RPT-PAY',
            'amount' => 500,
            'payment_date' => '2026-10-11',
            'payment_method' => 'Cash',
            'payment_channel' => 'Counter',
            'status' => 'Verified',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        $september = $this->get(route('reports.index', [
            'report' => 'outstanding-balances',
            'month' => 9,
            'year' => 2026,
        ]));

        $september->assertOk();
        $septemberRow = collect($september->viewData('result')->rows)
            ->firstWhere('account_number', 'CUTOFF-RPT-0001');

        $this->assertNotNull($septemberRow);
        $this->assertSame(500.00, $septemberRow['outstanding_balance']);
        $this->assertSame(1, $septemberRow['open_bills']);

        $latePayerSeptemberRow = collect($september->viewData('result')->rows)
            ->firstWhere('account_number', 'LATE-PAY-RPT-0001');

        $this->assertNotNull($latePayerSeptemberRow);
        $this->assertSame(500.00, $latePayerSeptemberRow['outstanding_balance']);
        $this->assertSame(1, $latePayerSeptemberRow['open_bills']);

        $october = $this->get(route('reports.index', [
            'report' => 'outstanding-balances',
            'month' => 10,
            'year' => 2026,
        ]));

        $october->assertOk();
        $octoberRow = collect($october->viewData('result')->rows)
            ->firstWhere('account_number', 'CUTOFF-RPT-0001');

        $this->assertNotNull($octoberRow);
        $this->assertSame(1000.00, $octoberRow['outstanding_balance']);
        $this->assertSame(1, $octoberRow['open_bills']);

        $latePayerOctoberRow = collect($october->viewData('result')->rows)
            ->firstWhere('account_number', 'LATE-PAY-RPT-0001');

        $this->assertNull($latePayerOctoberRow);

        $export = $this->get(route('reports.export', [
            'report' => 'outstanding-balances',
            'month' => 9,
            'year' => 2026,
            'format' => 'csv',
        ]));

        $export->assertOk();
        $this->assertStringContainsString('CUTOFF-RPT-0001', $export->streamedContent());
    }
}
