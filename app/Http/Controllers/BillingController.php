<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\MeterReading;
use App\Models\ServiceRequest;
use App\Notifications\ServiceRequestStatusNotification;
use App\Services\AdminWaterBillingRecords;
use App\Services\BillingComputationService;
use App\Services\ConsumerBalanceService;
use App\Services\ConsumerBillingScenario;
use App\Services\WaterBillingLedger;
use App\Services\DisconnectionNoticeService;
use App\Services\DisconnectionRecordsService;
use App\Services\DssEligibilityService;
use App\Services\LoanBillingSynchronizer;
use App\Services\LoanMaterialRateService;
use App\Services\MeterReadingBaselineService;
use App\Services\RagStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, AdminWaterBillingRecords $adminRecords, ConsumerBillingScenario $scenario, WaterBillingLedger $ledger): View
    {
        $user = $request->user();
        $consumer = $user->isConsumer() ? $user->consumer : null;
        $billingScenario = $scenario->isAvailableFor($consumer) ? $scenario->snapshot($consumer) : null;

        // A consumer viewing their own statement only needs their own account
        // reconciled; only staff roles trigger the system-wide check.
        if (! $billingScenario) {
            $this->syncOpenBillStatuses($consumer);
        }

        $billingPeriodFilter = trim((string) $request->query('billing_period', $request->query('period', '')));
        $status = trim((string) $request->string('status'));
        $prn = $user->isConsumer() ? trim((string) $request->string('prn')) : '';

        if ($status === 'All Bills') {
            $status = '';
        }

        $validatedFilters = Validator::make([
            'billing_period' => $billingPeriodFilter,
        ], [
            'billing_period' => ['nullable', 'date_format:Y-m'],
        ])->validate();

        $billingPeriodFilter = $validatedFilters['billing_period'] ?? '';
        $billingPeriodDate = $billingPeriodFilter !== ''
            ? Carbon::createFromFormat('Y-m', $billingPeriodFilter)->startOfMonth()
            : null;
        $consumerPeriod = $user->isConsumer() ? $this->billingDateFilters($request, $billingPeriodFilter) : [];
        $filters = $user->isAdmin() ? $this->adminBillingFilters($request, $billingPeriodFilter) : [
            'billing_period' => $billingPeriodFilter,
            'status' => $status,
            'prn' => $prn,
        ] + $consumerPeriod;

        $billingQuery = $user->isAdmin() || $billingScenario ? $adminRecords->bills($filters, $consumer?->id) : Bill::query()
            ->where('bill_type', 'Water')
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->when(! $user->isConsumer() && $billingPeriodDate, function ($query) use ($billingPeriodDate) {
                $query
                    ->whereYear('billing_period', $billingPeriodDate->year)
                    ->whereMonth('billing_period', $billingPeriodDate->month);
            })
            ->when($consumerPeriod['year'] ?? '', fn ($query, $year) => $query->whereYear('billing_period', $year))
            ->when($consumerPeriod['month'] ?? '', fn ($query, $month) => $query->whereMonth('billing_period', $month))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($prn !== '', fn ($query) => $query->where('prn', 'like', "%{$prn}%"));

        $filteredBills = $ledger->withAmounts(clone $billingQuery)->with('consumer')
            ->orderByDesc('billing_period')
            ->orderBy('prn')
            ->paginate(10)
            ->appends($user->isAdmin() ? array_filter($filters, fn ($value) => $value !== '') : $request->query());

        // Summaries cover the full filtered result set, not just the current page.
        // The aggregate is aliased so the Bill balance accessor cannot mask it.
        $summarizeScenario = app()->isLocal() && ($user->isAdmin() || $billingScenario);
        $billingSummaryRow = (clone $billingQuery)
            ->when($summarizeScenario, fn ($query) => $query->whereNotIn('bills.consumer_id', $ledger->localAccountIds()))
            ->leftJoinSub(
                DB::table('payments')
                    ->select('bill_id')
                    ->selectRaw('SUM(amount) as verified_total')
                    ->where('status', 'Verified')
                    ->where('payment_type', 'Water')
                    ->groupBy('bill_id'),
                'verified_payments',
                'verified_payments.bill_id',
                '=',
                'bills.id',
            )
            ->selectRaw('COUNT(*) as record_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.is_billing_fixture = 0 THEN bills.amount ELSE 0 END), 0) as total_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN bills.is_billing_fixture = 0 AND bills.status IN ('Unpaid', 'Overdue', 'Paid with Balance') THEN 1 ELSE 0 END), 0) as open_count")
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.is_billing_fixture = 0 THEN bills.arrears_amount ELSE 0 END), 0) as previous_balance')
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.is_billing_fixture = 0 AND bills.carried_forward_to_bill_id IS NULL AND bills.amount > COALESCE(verified_payments.verified_total, 0) + bills.waived_reconnection_fee_amount THEN ROUND(bills.amount - COALESCE(verified_payments.verified_total, 0) - bills.waived_reconnection_fee_amount, 2) ELSE 0 END), 0) as balance_due_total')
            ->first();

        $billingSummary = [
            'count' => (int) ($billingSummaryRow->record_count ?? 0),
            'total_amount' => (float) ($billingSummaryRow->total_amount ?? 0),
            'open_count' => (int) ($billingSummaryRow->open_count ?? 0),
            'previous_balance' => (float) ($billingSummaryRow->previous_balance ?? 0),
            'balance_due' => (float) ($billingSummaryRow->balance_due_total ?? 0),
        ];
        if ($summarizeScenario) {
            $scenarioBills = $ledger->withAmounts((clone $billingQuery)->whereIn('bills.consumer_id', $ledger->localAccountIds()))->get();
            $summary = $ledger->summary($scenarioBills);
            $open = $summary['issued_bills']->filter(fn (Bill $bill) => $bill->isOutstanding());
            $billingSummary['count'] += $summary['issued_bills']->count();
            $billingSummary['total_amount'] += (float) $summary['issued_bills']->sum('water_charge');
            $billingSummary['open_count'] += $open->count();
            $billingSummary['previous_balance'] += (float) $open->sum('arrears_amount');
            $billingSummary['balance_due'] += $summary['outstanding_balance'];
        }

        $status = $filters['status'];
        $billingHeading = $status === '' ? 'Water Billing' : "{$status} Water Bills";

        $disconnectionBills = Bill::query()
            ->where('bill_type', 'Water')
            ->with('consumer')
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
            ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
            ->orderByDesc('billing_period')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        $serviceRequestsTableExists = Schema::hasTable('service_requests');

        $connectionRequests = $serviceRequestsTableExists
            ? ServiceRequest::query()
                ->with(['consumer', 'reviewer'])
                ->when($user->isConsumer(), fn ($query) => $query->whereHas('consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)))
                ->orderByRaw("case when status = 'Pending' then 0 when status = 'Approved' then 1 else 2 end")
                ->orderByDesc('requested_at')
                ->limit(8)
                ->get()
            : collect();

        $readingsReadyForBilling = MeterReading::query()
            ->with(['consumer', 'reader', 'bill'])
            ->where('status', 'Encoded')
            ->whereDoesntHave('bill')
            ->orderByDesc('reading_date')
            ->limit(5)
            ->get();
        $pendingLoanSetups = $user->isAdmin() && Schema::hasTable('loans')
            ? Loan::query()
                ->with('consumer')
                ->where('status', 'Pending Review')
                ->where('amount', '<=', 0)
                ->orderByDesc('applied_at')
                ->get()
            : collect();

        $rag = app(RagStatusService::class);
        $balances = app(ConsumerBalanceService::class);
        $consumerPaymentRuleSummary = $consumer && ! $billingScenario
            ? $balances->paymentRuleSummaryFor($consumer->id)
            : null;
        $consumerTotalBalanceDue = $billingScenario['outstanding_balance'] ?? $consumerPaymentRuleSummary['total_outstanding'] ?? null;
        $billRag = [];
        foreach ($filteredBills as $bill) {
            $billRag[$bill->id] = $rag->billStatus($bill);
        }

        return view($this->roleViewPath($request, 'billing.index'), [
            'bills' => $filteredBills,
            'filteredBills' => $filteredBills,
            'billingScenario' => $billingScenario,
            'monthlyRecords' => $billingScenario
                ? $adminRecords->history($consumer, $filters, $filteredBills->currentPage())->appends($request->query()) : null,
            'billingAccounts' => $user->isAdmin() ? $adminRecords->accounts($filters) : null,
            'billingYears' => match (true) {
                $user->isAdmin() => $adminRecords->years(),
                $user->isConsumer() => $this->billingYears($ledger->bills()->where('consumer_id', $consumer?->id ?? 0)),
                default => [],
            },
            'billingFilterStatuses' => AdminWaterBillingRecords::STATUSES,
            'billingSummary' => $billingSummary,
            'billingHeading' => $billingHeading,
            'billRag' => $billRag,
            'disconnectionBills' => $disconnectionBills,
            'connectionRequests' => $connectionRequests,
            'readingsReadyForBilling' => $readingsReadyForBilling,
            'pendingLoanSetups' => $pendingLoanSetups,
            'consumers' => Consumer::orderBy('account_number')->get(),
            'filters' => $filters,
            'statuses' => ['Paid', 'Paid with Balance', 'Unpaid', 'Overdue'],
            'requestTypes' => ['New Connection', 'Reconnection'],
            'requestStatuses' => ['Pending', 'Approved', 'Rejected', 'Completed'],
            'canManageBilling' => $user->isAdmin(),
            'serviceRequestsTableExists' => $serviceRequestsTableExists,
            'consumerTotalBalanceDue' => $consumerTotalBalanceDue,
            'consumerPaymentRuleSummary' => $consumerPaymentRuleSummary,
            'selectedBillingPeriod' => $billingPeriodFilter,
            'defaults' => [
                'base_charge' => BillingComputationService::DEFAULT_BASE_CHARGE,
                'member_unit_rate' => BillingComputationService::MEMBER_UNIT_RATE,
                'non_member_unit_rate' => BillingComputationService::NON_MEMBER_UNIT_RATE,
            ],
            'disconnectionNotice' => $this->disconnectionNoticeSnapshot(),
        ]);
    }

    public function monthlyHistory(Request $request, Consumer $consumer, AdminWaterBillingRecords $adminRecords, DisconnectionRecordsService $notices): View
    {
        $pagination = $request->validate([
            'billing_period' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = array_filter(
            $this->adminBillingFilters($request, $pagination['billing_period'] ?? ''),
            fn ($value) => $value !== '',
        );
        $history = $adminRecords->history($consumer, $filters, (int) ($pagination['page'] ?? 1));
        $notice = WaterBillingLedger::isScenarioAccount($consumer) ? $notices->activeFor($consumer) : null;

        return view('admin.billing.monthly-history', [
            'consumer' => $consumer,
            'records' => $history->appends($filters),
            'filters' => $filters,
            'notice' => ($notice && ! $notice['is_billing_fixture']) ? $notice : null,
        ]);
    }

    private function adminBillingFilters(Request $request, string $legacyPeriod): array
    {
        $filters = Validator::make($request->only(['search', 'year', 'month', 'status']), [
            'search' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'between:1000,9999'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', Rule::in([...AdminWaterBillingRecords::STATUSES, 'All Bills'])],
        ])->validate();

        if ($legacyPeriod !== '' && ! $request->hasAny(['year', 'month'])) {
            [$filters['year'], $filters['month']] = explode('-', $legacyPeriod);
        }

        return [
            'search' => trim($filters['search'] ?? ''),
            'year' => (string) ($filters['year'] ?? ''),
            'month' => isset($filters['month']) ? (string) (int) $filters['month'] : '',
            'status' => ($filters['status'] ?? '') === 'All Bills' ? '' : ($filters['status'] ?? ''),
        ];
    }

    private function billingDateFilters(Request $request, string $legacyPeriod = ''): array
    {
        $dates = $request->validate([
            'year' => ['nullable', 'integer', 'between:1000,9999'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        if ($legacyPeriod !== '' && ! $request->hasAny(['year', 'month'])) {
            [$dates['year'], $dates['month']] = explode('-', $legacyPeriod);
        }

        return [
            'year' => (string) ($dates['year'] ?? ''),
            'month' => isset($dates['month']) ? (string) (int) $dates['month'] : '',
        ];
    }

    private function billingYears(Builder $query): Collection
    {
        return $query->whereNotNull('billing_period')->toBase()->distinct()->pluck('billing_period')
            ->map(fn (string $period) => substr($period, 0, 4))->unique()->sortDesc()->values();
    }

    public function loanIndex(Request $request, LoanBillingSynchronizer $loanBillingSynchronizer): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isConsumer(), 403);

        $search = trim((string) $request->query('search', ''));
        $consumerSearchPattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $periodFilters = $this->billingDateFilters($request);
        $year = $periodFilters['year'];
        $month = $periodFilters['month'];
        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, array_merge(LoanInstallment::STATUSES, ['Unpaid', 'Paid with Balance']), true)) {
            $status = '';
        }

        // Recover saved schedules and legacy bill links, scoped to the consumer.
        $consumerId = $user->isConsumer() ? ($user->consumer?->id ?? 0) : null;
        $synchronization = $loanBillingSynchronizer->synchronize($consumerId);

        $allLoanBillsQuery = LoanInstallment::query()
            ->where(function ($query): void {
                $query->where(function ($unbilled): void {
                    $unbilled->whereNull('bill_id')->whereHas('loan', fn ($loan) => $loan->whereIn('status', ['Ready for Scheduling', 'Approved', 'Active', 'Ongoing', 'Completed']));
                })->orWhereExists(function ($linked): void {
                    $linked->selectRaw('1')->from('loans')
                        ->join('bills', 'bills.loan_id', '=', 'loans.id')
                        ->whereColumn('loans.id', 'loan_installments.loan_id')
                        ->whereColumn('bills.id', 'loan_installments.bill_id')
                        ->whereColumn('bills.consumer_id', 'loans.consumer_id')
                        ->where('bills.bill_type', 'Loan');
                });
            })
            ->when($user->isConsumer(), fn ($query) => $query->whereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery->where('user_id', $user->id)));

        $loanBillsQuery = (clone $allLoanBillsQuery)
            ->when($year !== '', fn ($query) => $query->whereYear('billing_period', $year))
            ->when($month !== '', fn ($query) => $query->whereMonth('billing_period', $month))
            ->when($status === 'Unpaid', fn ($query) => $query->where('remaining_balance', '>', 0)->where('amount_paid', '<=', 0))
            ->when($status === 'Paid with Balance', fn ($query) => $query->where('remaining_balance', '>', 0)->where('amount_paid', '>', 0))
            ->when($status === 'Paid', fn ($query) => $query->where('remaining_balance', '<=', 0))
            ->when($status !== '' && ! in_array($status, ['Unpaid', 'Paid with Balance', 'Paid'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($installmentQuery) use ($search, $consumerSearchPattern): void {
                $installmentQuery->where('installment_number', 'like', "%{$search}%")
                    ->orWhereHas('bill', fn ($billQuery) => $billQuery->where('prn', 'like', "%{$search}%"))
                    ->orWhereHas('loan.consumer', fn ($consumerQuery) => $consumerQuery
                        ->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$consumerSearchPattern])
                        ->orWhereRaw("LOWER(account_number) LIKE ? ESCAPE '!'", [$consumerSearchPattern])
                        ->orWhereRaw("LOWER(meter_number) LIKE ? ESCAPE '!'", [$consumerSearchPattern]));
            }));

        $summary = (clone $allLoanBillsQuery)
            ->selectRaw('COUNT(*) as bill_count')
            ->selectRaw('COALESCE(SUM(required_amount), 0) as total_due')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(remaining_balance), 0) as remaining_balance')
            ->first();

        $installmentsQuery = ($user->isAdmin() ? clone $allLoanBillsQuery : clone $loanBillsQuery)
            ->with(['loan' => fn ($query) => $query->with('consumer')->withExists(['bills as has_loan_bill' => fn ($bill) => $bill->where('bill_type', 'Loan')]), 'bill'])
            ->orderBy('billing_period')
            ->orderBy('installment_number');

        $billingYears = $this->billingYears(clone $allLoanBillsQuery);
        if ($user->isAdmin()) {
            // Match loans using the existing installment filters, then load their complete schedules.
            $loanBills = $loanBillsQuery->select('loan_id')
                ->groupBy('loan_id')
                ->orderByRaw('MIN(billing_period)')
                ->orderBy('loan_id')
                ->paginate(15);
            $installmentsByLoan = $installmentsQuery->whereIn('loan_id', $loanBills->getCollection()->pluck('loan_id'))
                ->get()
                ->groupBy('loan_id');
            $loanBills->setCollection($loanBills->getCollection()->pluck('loan_id')
                ->map(fn ($loanId) => $installmentsByLoan->get($loanId))
                ->filter()
                ->values());
        } else {
            $loanBills = $installmentsQuery->paginate(15);
        }
        $loanBills->withQueryString();
        $scheduleReviewCount = Loan::query()->whereIn('status', ['Ready for Scheduling', 'Approved', 'Active', 'Ongoing'])
            ->where('amount', '>', 0)
            ->when($user->isConsumer(), fn ($query) => $query->where('consumer_id', $consumerId))
            ->withCount('installments')->get()->where('installments_count', 0)->count();

        return view($this->roleViewPath($request, 'billing.loan'), [
            'loanBills' => $loanBills,
            'loanBillingSummary' => [
                'bill_count' => (int) ($summary->bill_count ?? 0),
                'total_due' => round((float) ($summary->total_due ?? 0), 2),
                'total_paid' => round((float) ($summary->total_paid ?? 0), 2),
                'remaining_balance' => round((float) ($summary->remaining_balance ?? 0), 2),
            ],
            'loanBillingFilters' => ['search' => $search, 'year' => $year, 'month' => $month, 'status' => $status],
            'loanBillingYears' => $billingYears,
            'loanSchedulesNeedReviewCount' => $scheduleReviewCount,
            'loanScheduleSynchronization' => $synchronization,
            'loanBillingStatuses' => ['Unpaid', 'Paid with Balance', 'Paid'],
        ]);
    }

    /**
     * Brings overdue standings back in line with real billing and payment data.
     *
     * Only accounts that actually have an unpaid bill are re-evaluated, and each
     * account is evaluated exactly once. Re-evaluating per bill made the cost
     * grow with the total number of bills in the system, which made this page
     * unusably slow once real billing volume existed.
     */
    private function syncOpenBillStatuses(?Consumer $onlyConsumer = null): void
    {
        $consumerIds = $onlyConsumer
            ? collect([$onlyConsumer->id])
            : Bill::query()
                ->where('bill_type', 'Water')
                ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
                ->distinct()
                ->pluck('consumer_id');

        Consumer::query()
            ->whereIn('id', $consumerIds)
            ->chunkById(100, function ($consumers): void {
                foreach ($consumers as $consumer) {
                    $consumer->syncAccountStanding();
                }
            });
    }

    public function store(Request $request, WaterBillingLedger $ledger): RedirectResponse
    {
        $validated = $request->validate([
            'consumer_id' => ['required', 'exists:consumers,id'],
            'billing_period' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:billing_period'],
            'prn' => ['required', 'string', 'max:50', 'unique:bills,prn'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:Paid,Paid with Balance,Unpaid,Overdue'],
            'months_unpaid' => ['nullable', 'integer', 'min:0', 'max:24'],
            'notice_level' => ['nullable', 'string', 'max:100'],
            'request_type' => ['nullable', 'in:New Connection,Reconnection'],
            'request_status' => ['nullable', 'in:Pending,Approved,Rejected'],
            'arrears_amount' => ['prohibited'],
            'carried_forward_from_bill_id' => ['prohibited'],
            'penalty_amount' => ['prohibited'],
        ]);

        $validated['billing_period'] = Carbon::parse($validated['billing_period'])->startOfMonth()->toDateString();
        $validated['months_unpaid'] = $validated['months_unpaid'] ?? 0;
        $billingPeriod = Carbon::parse($validated['billing_period'])->startOfMonth();

        DB::transaction(function () use ($validated, $billingPeriod, $ledger): void {
            $consumer = Consumer::query()->whereKey($validated['consumer_id'])->lockForUpdate()->firstOrFail();
            if (WaterBillingLedger::isScenarioNovember($consumer, $billingPeriod->toDateString())) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'billing_period' => 'Generate November billing from a completed November meter reading.',
                ]);
            }
            $duplicateBillExists = $ledger->bills()
                ->where('consumer_id', $validated['consumer_id'])
                ->whereDate('billing_period', $billingPeriod->toDateString())
                ->exists();

            if ($duplicateBillExists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'billing_period' => 'This consumer already has a billing record for the selected billing month.',
                ]);
            }

            if ($ledger->bills()->where('consumer_id', $validated['consumer_id'])
                ->whereDate('billing_period', '>', $billingPeriod->toDateString())->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'billing_period' => 'Cannot create an earlier water bill after a later bill exists.',
                ]);
            }

            $carryForward = $this->calculateCarryForward((int) $validated['consumer_id'], $billingPeriod, 0);
            if ($carryForward['amount'] > 0) {
                $validated['arrears_amount'] = $carryForward['amount'];
                $validated['carried_forward_from_bill_id'] = $carryForward['bills']->last()?->id;
                $validated['amount'] = round((float) $validated['amount'] + $carryForward['amount'], 2);
            }

            $validated['bill_type'] = 'Water';
            $bill = Bill::create($validated);
            $this->applyCarryForward($bill, $carryForward['bills'], $carryForward['amount']);
            $bill->refresh()->loadSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount');
            $bill->syncPaymentStatus();
        });

        return redirect()
            ->route('billing.index')
            ->with('success', 'Billing record created successfully.');
    }

    public function generate(Request $request, WaterBillingLedger $ledger): RedirectResponse
    {
        $this->syncOpenBillStatuses();

        $validated = $request->validate([
            'billing_period' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'base_charge' => ['required', 'numeric', 'min:0'],
            'member_unit_rate' => ['required', 'numeric', 'min:0'],
            'non_member_unit_rate' => ['required', 'numeric', 'min:0'],
            'loan_amounts' => ['nullable', 'array'],
            'loan_amounts.*' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $billingPeriod = Carbon::parse($validated['billing_period'])->startOfMonth();
        $dueDate = Carbon::parse($validated['due_date'])->toDateString();
        $baseCharge = (float) $validated['base_charge'];
        $memberUnitRate = (float) $validated['member_unit_rate'];
        $nonMemberUnitRate = (float) $validated['non_member_unit_rate'];

        $readings = $ledger->withoutArchivedNovemberReadings(MeterReading::query())
            ->with('consumer')
            ->where('status', 'Encoded')
            ->whereYear('reading_date', $billingPeriod->year)
            ->whereMonth('reading_date', $billingPeriod->month)
            ->get();

        $scheduledLoanExists = Loan::query()->whereIn('status', Loan::PAYMENT_STATUSES)->where('amount', '>', 0)
            ->whereDate('start_billing_period', '<=', $billingPeriod->toDateString())->exists();
        if ($readings->isEmpty() && ! $scheduledLoanExists) {
            return redirect()
                ->route('billing.index')
                ->withErrors([
                    'billing_period' => 'No encoded meter readings were found for the selected billing period.',
                ]);
        }

        $pendingLoanSetups = Schema::hasTable('loans')
            ? Loan::query()
                ->whereIn('consumer_id', $readings->pluck('consumer_id')->unique()->values())
                ->where('status', 'Pending Review')
                ->where('amount', '<=', 0)
                ->get()
            : collect();
        $loanAmounts = collect($validated['loan_amounts'] ?? []);

        foreach ($pendingLoanSetups as $pendingLoan) {
            if (! in_array((int) $pendingLoan->term_months, [3, 6, 12], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "loan_amounts.{$pendingLoan->id}" => 'Repayment term must be exactly 3, 6, or 12 months.',
                ]);
            }

            $submittedAmount = $loanAmounts->get((string) $pendingLoan->id, $loanAmounts->get($pendingLoan->id));

            if ($submittedAmount === null || $submittedAmount === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "loan_amounts.{$pendingLoan->id}" => 'Loan Amount is required during initial loan setup.',
                ]);
            }

            if ($pendingLoan->consumer?->loans()->blockingNewApplication()->whereKeyNot($pendingLoan->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "loan_amounts.{$pendingLoan->id}" => DssEligibilityService::ACTIVE_LOAN_MESSAGE,
                ]);
            }
        }

        $generatedCount = 0;

        $calculator = app(BillingComputationService::class);
        $baseline = app(MeterReadingBaselineService::class);

        DB::transaction(function () use ($readings, $pendingLoanSetups, $loanAmounts, $billingPeriod, $dueDate, $baseCharge, $memberUnitRate, $nonMemberUnitRate, $calculator, $baseline, $ledger, &$generatedCount) {
            foreach ($pendingLoanSetups as $pendingLoan) {
                $loanAmount = round((float) $loanAmounts->get((string) $pendingLoan->id, $loanAmounts->get($pendingLoan->id)), 2);
                $termMonths = (int) $pendingLoan->term_months;

                if (! in_array($termMonths, [3, 6, 12], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "loan_amounts.{$pendingLoan->id}" => 'Repayment term must be exactly 3, 6, or 12 months.',
                    ]);
                }

                $pendingLoan->forceFill([
                    'amount' => $loanAmount,
                    'monthly_installment' => round($loanAmount / $termMonths, 2),
                    'installment_schedule_snapshot' => app(LoanMaterialRateService::class)->schedule($loanAmount, $termMonths),
                    'status' => 'Approved',
                    'approved_at' => $pendingLoan->approved_at ?? now(),
                    'start_billing_period' => $billingPeriod->copy()->addMonth()->startOfMonth()->toDateString(),
                    'next_due_date' => $pendingLoan->next_due_date ?? Carbon::parse($dueDate)->addMonth()->toDateString(),
                ])->save();
            }

            foreach ($readings as $reading) {
                Consumer::query()->whereKey($reading->consumer_id)->lockForUpdate()->firstOrFail();
                $existingBill = Bill::query()->where('meter_reading_id', $reading->id)->first();
                if ($existingBill) {
                    $reading->consumer?->syncAccountStanding(notify: true);
                    continue;
                }

                if ($ledger->bills()->where('consumer_id', $reading->consumer_id)
                    ->whereDate('billing_period', '>', $billingPeriod->toDateString())->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'billing_period' => "Cannot generate an earlier water bill after a later bill exists for {$reading->consumer?->full_name}.",
                    ]);
                }

                $duplicateBill = $ledger->bills()
                    ->where('consumer_id', $reading->consumer_id)
                    ->whereDate('billing_period', $billingPeriod->toDateString())
                    ->where(function ($query) use ($reading) {
                        $query
                            ->whereNull('meter_reading_id')
                            ->orWhere('meter_reading_id', '!=', $reading->id);
                    })
                    ->exists();

                if ($duplicateBill) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'billing_period' => "A billing record already exists for {$reading->consumer?->full_name} in {$billingPeriod->format('F Y')}.",
                    ]);
                }

                $previousReading = $baseline->previousReadingFor($reading->consumer_id, $billingPeriod);
                $currentReading = (float) $reading->current_reading;

                if ($currentReading < $previousReading) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'current_reading' => 'Invalid Reading: Current Meter Reading cannot be lower than the Previous Reading.',
                    ]);
                }

                $consumption = round($currentReading - $previousReading, 2);

                $reading->forceFill([
                    'previous_reading' => $previousReading,
                    'consumption' => $consumption,
                ])->save();

                $unitRate = $reading->consumer?->hasVerifiedMembership()
                    ? $memberUnitRate
                    : $nonMemberUnitRate;

                $bill = Bill::query()->firstOrNew([
                    'meter_reading_id' => $reading->id,
                ]);

                // Re-running the same cycle must be idempotent: the carried
                // balance already moved onto this bill the first time, so it is
                // kept rather than recomputed from an already-closed source.
                $carryForward = $bill->exists
                    ? [
                        'amount' => round((float) ($bill->arrears_amount ?? 0), 2),
                        'bills' => collect(),
                    ]
                    : $this->calculateCarryForward($reading->consumer_id, $billingPeriod, $reading->id);

                $this->markOverdueLoanInstallments($reading->consumer_id, $billingPeriod);
                $charges = $calculator->calculate(
                    consumption: $consumption,
                    baseCharge: $baseCharge,
                    unitRate: $unitRate,
                    previousBalance: $carryForward['amount'],
                );

                $bill->fill([
                    'consumer_id' => $reading->consumer_id,
                    'bill_type' => 'Water',
                    'billing_period' => $billingPeriod->toDateString(),
                    'due_date' => $dueDate,
                    'prn' => $bill->exists ? $bill->prn : $this->generatePrn($billingPeriod, $reading->id),
                    'amount' => round($charges['total_due'], 2),
                    'base_charge' => $charges['base_charge'],
                    'water_charge' => $charges['current_charges'],
                    'arrears_amount' => $carryForward['amount'],
                    'carried_forward_from_bill_id' => $bill->exists ? $bill->carried_forward_from_bill_id : $carryForward['bills']->last()?->id,
                    // Penalties and minimum charges are excluded from newly
                    // generated bills; historical bills keep their own value.
                    'penalty_amount' => $bill->exists ? $bill->penalty_amount : 0,
                    'loan_id' => null,
                    'loan_installment_amount' => 0,
                    'units_consumed' => $consumption,
                    'unit_rate' => $unitRate,
                ]);

                if (! $bill->exists) {
                    $bill->status = 'Unpaid';
                    $bill->months_unpaid = 0;
                }

                $bill->save();

                // Close the periods whose balance now lives on this bill, so the
                // debt is represented once and only once.
                $this->applyCarryForward($bill, $carryForward['bills'], $carryForward['amount']);

                if (WaterBillingLedger::isScenarioNovember($reading->consumer, $billingPeriod->toDateString())) {
                    $projection = $ledger->novemberProjections()->where('consumer_id', $reading->consumer_id)
                        ->whereNull('superseded_by_bill_id')->first();
                    if ($projection) {
                        $projection->earlierIssues()->toBase()->update(['superseded_by_bill_id' => $bill->id]);
                        $projection->forceFill(['superseded_by_bill_id' => $bill->id])->save();
                        $bill->syncPaymentStatus(false);
                    }
                }

                $reading->consumer?->syncAccountStanding(true);
                $generatedCount++;
            }

            $generatedCount += $this->generateLoanInstallmentBills($billingPeriod, Carbon::parse($dueDate));
        });

        return redirect()
            ->route('billing.index', ['period' => $billingPeriod->format('Y-m')])
            ->with('success', "Generated {$generatedCount} new water and loan billing record(s) for the selected period.");
    }

    public function show(Request $request, Bill $bill): View
    {
        $data = $this->statementData($request, $bill);
        $period = $data['selectedBillingPeriod'];
        $filters = $request->user()->isAdmin()
            ? $this->adminBillingFilters($request, $period)
            : ['billing_period' => $period];
        if ($request->user()->isConsumer()) {
            if ($request->hasAny(['year', 'month'])) {
                $filters = $this->billingDateFilters($request);
            }
            $filters += $request->only(['status', 'prn']);
        }
        return view($this->roleViewPath($request, 'billing.statement'), $data + [
            'billingReturnFilters' => array_filter($filters, fn ($value) => $value !== ''),
        ]);
    }

    public function statementPdf(Request $request, Bill $bill): Response
    {
        $filename = 'billing-statement-'.(Str::slug($bill->prn) ?: $bill->id).'.pdf';

        return Pdf::loadView('shared.billing.statement-pdf', $this->statementData($request, $bill))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * Build the shared statement payload so the HTML and PDF views always use
     * the same records, balances, membership state, and payment rules.
     */
    private function statementData(Request $request, Bill $bill): array
    {
        $billingPeriodFilter = trim((string) $request->query('billing_period', ''));
        $validatedFilters = Validator::make([
            'billing_period' => $billingPeriodFilter,
        ], [
            'billing_period' => ['nullable', 'date_format:Y-m'],
        ])->validate();
        $billingPeriodFilter = $validatedFilters['billing_period'] ?? '';

        $bill->load([
            'consumer',
            'meterReading.reader',
            'carriedForwardTo',
            'supersededBy.consumer',
        ])->loadSum([
            'payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified'),
        ], 'amount');

        if ($request->user()->isConsumer() && $bill->consumer?->user_id !== $request->user()->id) {
            abort(403);
        }
        abort_if($bill->isNovemberProjection(), 404);
        if ($bill->is_billing_fixture && $request->user()->isConsumer()) {
            abort_unless(app(ConsumerBillingScenario::class)->isAvailableFor($bill->consumer), 404);
        }

        $previousBill = $bill->carriedForwardFrom ?? Bill::where('consumer_id', $bill->consumer_id)
            ->when($bill->is_billing_fixture, fn ($query) => $query->onlyBillingFixtures())
            ->where('billing_period', '<', $bill->billing_period)
            ->orderByDesc('billing_period')
            ->first();
        $consumerPaymentRuleSummary = $bill->is_billing_fixture ? [] : app(ConsumerBalanceService::class)->paymentRuleSummaryFor($bill->consumer_id);
        $loanInstallments = Schema::hasTable('loan_installments')
            ? LoanInstallment::query()
                ->whereHas('loan', fn ($query) => $query->where('consumer_id', $bill->consumer_id))
                ->whereDate('billing_period', '<=', $bill->billing_period->toDateString())
                ->where(function ($query) use ($bill) {
                    $query
                        ->where('remaining_balance', '>', 0)
                        ->orWhere('bill_id', $bill->id);
                })
                ->orderBy('billing_period')
                ->orderBy('installment_number')
                ->get()
            : collect();

        return [
            'bill' => $bill,
            'previousBill' => $previousBill,
            'consumerPaymentRuleSummary' => $consumerPaymentRuleSummary,
            'selectedBillingPeriod' => $billingPeriodFilter,
            'loanInstallments' => $bill->is_billing_fixture ? collect() : $loanInstallments,
            'generatedAt' => now(),
        ];
    }

    public function storeServiceRequest(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('service_requests'), 404);

        $user = $request->user();
        $isReconnection = $request->input('request_type') === ServiceRequest::TYPE_RECONNECTION;

        $validator = Validator::make($request->all(), [
            'consumer_id' => [$user->isAdmin() ? 'required' : 'sometimes', 'nullable', 'exists:consumers,id'],
            'request_type' => ['required', 'in:New Connection,Reconnection'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // A reconnection must be backed by proof of the organisation's
            // reconnection confirmation.
            'information' => [$isReconnection ? 'required' : 'nullable', 'nullable', 'string', 'max:1000'],
            'proof' => [
                $isReconnection ? 'required' : 'nullable',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf,webp',
                'max:5120',
            ],
        ], [
            'information.required' => 'Please provide the requested reconnection information.',
            'proof.required' => 'Please upload a screenshot of the reconnection confirmation email as proof.',
            'proof.mimes' => 'The proof must be a JPG, JPEG, PNG, WEBP or PDF file.',
            'proof.max' => 'The proof file must not exceed 5 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('billing.index')
                ->withErrors($validator)
                ->withInput()
                ->with('service_request_modal', true);
        }

        $validated = $validator->validated();

        $consumer = $user->isConsumer()
            ? $user->consumer
            : Consumer::query()->findOrFail($validated['consumer_id']);

        abort_if(! $consumer, 403);

        if (! $isReconnection
            && $consumer->serviceRequests()
                ->where('request_type', ServiceRequest::TYPE_NEW_CONNECTION)
                ->where('status', ServiceRequest::STATUS_PENDING)
                ->exists()) {
            return redirect()
                ->route('billing.index')
                ->withErrors([
                    'request_type' => 'A new connection request is already pending for this consumer. Review the existing application in the Consumers module.',
                ])
                ->with('service_request_modal', true);
        }

        $outstandingBalance = app(ConsumerBalanceService::class)->totalOutstandingFor($consumer->id);

        $proofPath = null;

        if ($isReconnection) {
            $outstandingBalance = app(\App\Services\DisconnectionNoticeService::class)->outstandingForReconnection($consumer);
            if (! in_array($consumer->status, Consumer::DISCONNECTION_ELIGIBLE_STATUSES, true)) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors([
                        'request_type' => 'Reconnection requests are only available for disconnected or for-reconnection accounts.',
                    ])
                    ->with('service_request_modal', true);
            }

            // Backend eligibility check. The UI never decides this.
            if ($outstandingBalance > 0) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors([
                        'request_type' => 'All outstanding balances must be fully settled before a reconnection request can be submitted. Remaining balance: PHP '.number_format($outstandingBalance, 2).'.',
                    ])
                    ->with('service_request_modal', true);
            }

            // Eligibility only exists once an administrator verified the payment.
            if (blank($consumer->full_payment_verified_at)) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors([
                        'request_type' => 'Your payment must be verified and approved by the administrator before you can request reconnection.',
                    ])
                    ->with('service_request_modal', true);
            }

            $existingPending = $consumer->serviceRequests()
                ->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                ->where('status', ServiceRequest::STATUS_PENDING)
                ->exists();

            if ($existingPending) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors([
                        'request_type' => 'A reconnection request is already pending for this account. Please wait for the administrator to review it.',
                    ])
                    ->with('service_request_modal', true);
            }

            if ($request->hasFile('proof')) {
                $proof = $request->file('proof');

                if (! $proof->isValid()) {
                    return redirect()
                        ->route('billing.index')
                        ->withErrors(['proof' => 'The uploaded proof could not be read. Please try again.'])
                        ->with('service_request_modal', true);
                }

                Storage::disk('local')->makeDirectory('reconnection-proofs');

                $proofPath = $proof->storeAs(
                    'reconnection-proofs',
                    Str::random(40).'.'.$proof->getClientOriginalExtension(),
                    'local',
                );

                if (! $proofPath) {
                    return redirect()
                        ->route('billing.index')
                        ->withErrors(['proof' => 'The proof could not be saved. Please try again.'])
                        ->with('service_request_modal', true);
                }
            }
        }

        ServiceRequest::create([
            'consumer_id' => $consumer->id,
            'request_type' => $validated['request_type'],
            'status' => ServiceRequest::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'information' => $validated['information'] ?? null,
            'proof_path' => $proofPath,
            'current_balance_snapshot' => $outstandingBalance,
            'requested_at' => now(),
        ]);

        return redirect()
            ->route('billing.index')
            ->with('success', 'Service request submitted successfully.');
    }

    /**
     * Serves an uploaded reconnection proof to the administrator or its owner.
     */
    public function serviceRequestProof(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();

        abort_if(blank($serviceRequest->proof_path), 404);

        // Administrators review every request; a consumer may only open their own.
        abort_if(
            ! $user->isAdmin() && $serviceRequest->consumer?->user_id !== $user->id,
            403,
        );

        abort_unless(Storage::disk('local')->exists($serviceRequest->proof_path), 404);

        return Storage::disk('local')->response($serviceRequest->proof_path);
    }

    public function updateServiceRequest(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:Pending,Approved,Rejected,Completed'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('billing.index')
                ->withErrors($validator)
                ->withInput()
                ->with('service_request_modal', true)
                ->with('service_request_review_id', $serviceRequest->id);
        }

        $validated = $validator->validated();

        $serviceRequest->loadMissing('consumer');

        // New connection applications are completed through the Consumers
        // review modal. A status change here would bypass the account, meter,
        // and membership verification transaction and desynchronise the two
        // records.
        if ($serviceRequest->request_type === ServiceRequest::TYPE_NEW_CONNECTION) {
            return redirect()
                ->route('billing.index')
                ->withErrors([
                    'status' => 'New connection applications are reviewed in the Consumers module. Use Review in Consumers so the account, meter, and membership verification stay in sync.',
                ]);
        }

        if ($validated['status'] === ServiceRequest::STATUS_APPROVED
            && $serviceRequest->request_type === ServiceRequest::TYPE_RECONNECTION) {
            $remainingBalance = app(\App\Services\DisconnectionNoticeService::class)->outstandingForReconnection($serviceRequest->consumer);

            if ($remainingBalance > 0) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors([
                        'status' => 'Reconnection cannot be approved while the consumer still has an outstanding balance.',
                    ])
                    ->with('service_request_modal', true)
                    ->with('service_request_review_id', $serviceRequest->id);
            }
        }

        DB::transaction(function () use ($validated, $serviceRequest, $request): void {
            $serviceRequest->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? null,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()->id,
            ]);

            if ($serviceRequest->request_type === ServiceRequest::TYPE_RECONNECTION
                && in_array($validated['status'], [ServiceRequest::STATUS_APPROVED, ServiceRequest::STATUS_COMPLETED], true)) {
                // The water supply is NOT restored here. The account moves to
                // "For Reconnection" so the association can restore supply
                // under its own authorised procedure.
                if ($serviceRequest->consumer->status === 'Disconnected') {
                    $serviceRequest->consumer->forceFill(['status' => 'For Reconnection'])->save();
                }

            }
        });

        $consumerUser = $serviceRequest->consumer?->user;

        if (filled($validated['status']) && $validated['status'] !== ServiceRequest::STATUS_PENDING) {
            $statusLabel = $validated['status'];
            $typeLabel = $serviceRequest->request_type;
            $adminNotes = $validated['admin_notes'] ?? null;
            $notesSuffix = filled($adminNotes) ? " Note: {$adminNotes}" : '';
            $title = "{$typeLabel} Request {$statusLabel}";
            $message = match ($statusLabel) {
                ServiceRequest::STATUS_APPROVED => "Your {$typeLabel} request has been approved."
                    .$notesSuffix
                    .' Please follow the association\'s reconnection procedure. Your water supply will only be restored once the reconnection has been carried out.',
                ServiceRequest::STATUS_COMPLETED => "Your {$typeLabel} request has been completed."
                    .$notesSuffix,
                ServiceRequest::STATUS_REJECTED => "Your {$typeLabel} request has been rejected."
                    .(filled($adminNotes) ? " Reason: {$adminNotes}" : ''),
                default => "Your {$typeLabel} request is marked as {$statusLabel}."
                    .$notesSuffix,
            };

            if ($consumerUser) {
                $consumerUser->notify(new ServiceRequestStatusNotification(
                    $serviceRequest,
                    $title,
                    $message,
                ));
            }

            // Email the customer as well, once per request and decision.
            app(DisconnectionNoticeService::class)->sendNotice(
                consumer: $serviceRequest->consumer,
                // Keyed on the request and the decision so each status change
                // emails exactly once and is never duplicated.
                event: 'service_request_'.$serviceRequest->id.'_'.strtolower($statusLabel),
                subject: $title.' - ViaFlow Water Billing',
                message: 'Hello '.$serviceRequest->consumer->full_name.', '.$message,
                force: true,
            );
        }

        return redirect()
            ->route('billing.index')
            ->with('success', 'Service request updated successfully.');
    }

    /**
     * Consumers currently carrying an active disconnection notice, with the
     * reason each one was flagged.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function disconnectionNoticeSnapshot(): \Illuminate\Support\Collection
    {
        return DisconnectionNotice::query()
            ->active()
            ->with('consumer')
            ->whereHas('consumer', fn ($query) => $query->whereIn('status', ['Active', 'For Review']))
            ->orderBy('issued_at')
            ->get()
            ->map(function (DisconnectionNotice $notice): array {
                $balances = app(ConsumerBalanceService::class);

                return [
                    'consumer' => $notice->consumer,
                    'notice' => $notice,
                    'months_unpaid' => $balances->consecutiveUnpaidMonthsFor($notice->consumer_id),
                    'months_required' => ConsumerBalanceService::DISCONNECTION_NOTICE_AFTER_MONTHS,
                    'total_outstanding' => $balances->totalOutstandingFor($notice->consumer_id),
                    'payment_deadline' => $notice->deadline_at,
                ];
            });
    }

    protected function generatePrn(Carbon $billingPeriod, int $readingId): string
    {
        return sprintf('PRN-%s-%04d', $billingPeriod->format('ym'), $readingId);
    }

    /**
     * Works out the full balance still owed from periods before $billingPeriod.
     *
     * Intermediate bills that already absorbed older balances into their own
     * arrears carry-over are subtracted, so only genuinely unpaid periods are
     * carried forward. This prevents a balance being carried more than once
     * when three or more months go unsettled.
     *
     * @return array{amount:float, source_bill_id:?int, source_bill_count:int}
     */
    protected function calculateCarryForward(int $consumerId, Carbon $billingPeriod, int $currentReadingId): array
    {
        // Every remaining unpaid month is included, never only the newest one.
        $consumer = app()->isLocal() ? Consumer::find($consumerId) : null;
        $priorBills = WaterBillingLedger::isScenarioAccount($consumer)
            ? app(WaterBillingLedger::class)->outstandingBefore($consumer, $billingPeriod->toDateString())
            : app(ConsumerBalanceService::class)->outstandingBefore($consumerId, $billingPeriod->toDateString());

        if ($priorBills->isEmpty()) {
            return ['amount' => 0.0, 'bills' => collect()];
        }

        return [
            'amount' => round((float) $priorBills->sum(fn (Bill $bill) => $bill->balance_due), 2),
            'bills' => $priorBills->values(),
        ];
    }

    /**
     * Moves the carried balances onto the newly generated bill and closes the
     * bills they came from, so the same debt is never represented twice.
     *
     * @param  Collection<int, Bill>  $sourceBills
     */
    protected function applyCarryForward(Bill $newBill, Collection $sourceBills, float $carriedAmount): void
    {
        if ($sourceBills->isEmpty() || $carriedAmount <= 0) {
            return;
        }

        $remainingToCarry = round($carriedAmount, 2);

        foreach ($sourceBills as $source) {
            if ($remainingToCarry <= 0) {
                break;
            }

            $sourceBalance = (float) $source->balance_due;
            $source->carryBalanceInto($newBill);

            $remainingToCarry = round($remainingToCarry - $sourceBalance, 2);
        }
    }

    private function generateLoanInstallmentBills(Carbon $billingPeriod, Carbon $dueDate): int
    {
        if (! Schema::hasTable('loan_installments')) {
            return 0;
        }

        $generated = 0;
        $loans = Loan::query()->whereIn('status', Loan::PAYMENT_STATUSES)->where('amount', '>', 0)
            ->whereIn('term_months', [3, 6, 12])->whereNotNull('start_billing_period')
            ->whereDate('start_billing_period', '<=', $billingPeriod->toDateString())->lockForUpdate()->get();

        foreach ($loans as $loan) {
            $start = Carbon::parse($loan->start_billing_period)->startOfMonth();
            $number = $start->diffInMonths($billingPeriod->copy()->startOfMonth()) + 1;
            if ($number < 1 || $number > (int) $loan->term_months) {
                continue;
            }

            $schedule = $loan->installment_schedule_snapshot;
            if (! is_array($schedule) || count($schedule) !== (int) $loan->term_months) {
                continue;
            }
            $scheduledAmounts = [];
            foreach (array_values($schedule) as $index => $scheduledRow) {
                if (! is_array($scheduledRow)
                    || (isset($scheduledRow['month']) && (int) $scheduledRow['month'] !== $index + 1)
                    || ! is_numeric($scheduledRow['amount'] ?? null)
                    || (float) $scheduledRow['amount'] <= 0) {
                    $scheduledAmounts = [];
                    break;
                }
                $scheduledAmounts[] = round((float) $scheduledRow['amount'], 2);
            }
            if (count($scheduledAmounts) !== (int) $loan->term_months
                || abs(round(array_sum($scheduledAmounts), 2) - round((float) $loan->amount, 2)) >= 0.01) {
                continue;
            }
            $inconsistent = $loan->installments()->with('bill')->get()->contains(function (LoanInstallment $row) use ($loan, $start, $scheduledAmounts): bool {
                $number = (int) $row->installment_number;
                $expected = $scheduledAmounts[$number - 1] ?? null;
                $required = round((float) $row->required_amount, 2);
                $paid = round((float) $row->amount_paid, 2);

                return $expected === null || $required !== $expected || $paid < 0 || $paid > $required
                    || round((float) $row->remaining_balance, 2) !== round($required - $paid, 2)
                    || ! $row->billing_period?->isSameDay($start->copy()->addMonthsNoOverflow($number - 1))
                    || ($row->bill_id && (! $row->bill || $row->bill->bill_type !== 'Loan'
                        || (int) $row->bill->consumer_id !== (int) $loan->consumer_id || (int) $row->bill->loan_id !== (int) $loan->id));
            });
            if ($inconsistent) {
                continue;
            }
            $amount = (float) $scheduledAmounts[$number - 1];
            if ($amount <= 0) {
                continue;
            }

            $installment = LoanInstallment::query()->where('loan_id', $loan->id)
                ->where('installment_number', $number)->lockForUpdate()->first();
            if ($installment) {
                // A legacy installment linked to a mixed or ambiguous bill stays
                // available for review; never silently reclassify or duplicate it.
                if ($installment->bill_id || $installment->required_amount <= 0) {
                    continue;
                }
                if ((float) $installment->remaining_balance <= 0) {
                    continue;
                }
            } else {
                $installment = LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $number,
                    'billing_period' => $billingPeriod->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'required_amount' => $amount,
                    'amount_paid' => 0,
                    'remaining_balance' => $amount,
                    'status' => 'Billed',
                ]);
            }

            $bill = Bill::query()->where('bill_type', 'Loan')->where('loan_id', $loan->id)
                ->whereDate('billing_period', $billingPeriod->toDateString())->first();
            if (! $bill) {
                $bill = Bill::create([
                    'consumer_id' => $loan->consumer_id,
                    'bill_type' => 'Loan',
                    'loan_id' => $loan->id,
                    'billing_period' => $billingPeriod->toDateString(),
                    'due_date' => $installment->due_date?->toDateString() ?? $dueDate->toDateString(),
                    'prn' => 'LN-'.$loan->id.'-'.$number,
                    'amount' => $amount,
                    'status' => 'Unpaid',
                    'loan_installment_amount' => $amount,
                ]);
                $generated++;
            }

            $installment->forceFill(['bill_id' => $bill->id, 'due_date' => $installment->due_date ?? $dueDate])->syncStatus();
            $bill->refresh()->syncPaymentStatus(false);
        }

        return $generated;
    }

    protected function loanInstallmentForBill(int $consumerId, Carbon $billingPeriod, Bill $bill): array
    {
        if (! Schema::hasTable('loans') || ! Schema::hasColumn('bills', 'loan_id')) {
            return ['loan_id' => null, 'amount' => 0.0, 'installment_number' => null];
        }

        if ($bill->exists && (float) ($bill->loan_installment_amount ?? 0) > 0) {
            $existingInstallment = Schema::hasTable('loan_installments')
                ? LoanInstallment::query()->where('bill_id', $bill->id)->first()
                : null;

            return [
                'loan_id' => $bill->loan_id,
                'amount' => round((float) $bill->loan_installment_amount, 2),
                'installment_number' => $existingInstallment?->installment_number,
            ];
        }

        $loan = Loan::query()
            ->when(
                Schema::hasTable('loan_installments'),
                fn ($query) => $query->withCount('installments'),
                fn ($query) => $query->withCount(['bills' => fn ($billQuery) => $billQuery->where('loan_installment_amount', '>', 0)])
            )
            ->withSum('bills', 'loan_installment_amount')
            ->where('consumer_id', $consumerId)
            ->where('status', 'Approved')
            ->where('amount', '>', 0)
            ->whereIn('term_months', [3, 6, 12])
            ->whereDate('start_billing_period', '<=', $billingPeriod->toDateString())
            ->orderBy('start_billing_period')
            ->orderBy('id')
            ->get()
            ->first(fn (Loan $candidate) => $candidate->installments_billed < (int) $candidate->term_months);

        if (! $loan) {
            return ['loan_id' => null, 'amount' => 0.0, 'installment_number' => null];
        }

        $duplicateInstallmentExists = Schema::hasTable('loan_installments')
            ? LoanInstallment::query()
                ->where('loan_id', $loan->id)
                ->whereDate('billing_period', $billingPeriod->toDateString())
                ->exists()
            : Bill::query()
                ->where('loan_id', $loan->id)
                ->whereDate('billing_period', $billingPeriod->toDateString())
                ->when($bill->exists, fn ($query) => $query->whereKeyNot($bill->id))
                ->exists();

        if ($duplicateInstallmentExists) {
            return ['loan_id' => null, 'amount' => 0.0, 'installment_number' => null];
        }

        $installmentNumber = $loan->installments_billed + 1;

        return [
            'loan_id' => $loan->id,
            'amount' => $loan->installmentAmountForNumber($installmentNumber),
            'installment_number' => $installmentNumber,
        ];
    }

    protected function markOverdueLoanInstallments(int $consumerId, Carbon $billingPeriod): void
    {
        if (! Schema::hasTable('loan_installments')) {
            return;
        }

        LoanInstallment::query()
            ->whereHas('loan', fn ($query) => $query->where('consumer_id', $consumerId))
            ->where('remaining_balance', '>', 0)
            ->whereDate('billing_period', '<', $billingPeriod->toDateString())
            ->whereDate('due_date', '<', today()->toDateString())
            ->whereIn('status', ['Billed', 'Partially Paid'])
            ->update(['status' => 'Overdue']);
    }

    protected function syncLoanInstallmentForBill(Bill $bill, array $loanInstallment, Carbon $billingPeriod, Carbon $dueDate): void
    {
        if (! Schema::hasTable('loan_installments') || ! $loanInstallment['loan_id'] || $loanInstallment['amount'] <= 0) {
            return;
        }

        $installmentNumber = $loanInstallment['installment_number'];

        if (! $installmentNumber) {
            $loan = Loan::query()->find($loanInstallment['loan_id']);
            $installmentNumber = $loan ? $loan->installments_billed + 1 : null;
        }

        if (! $installmentNumber) {
            return;
        }

        LoanInstallment::query()->firstOrCreate(
            [
                'loan_id' => $loanInstallment['loan_id'],
                'installment_number' => $installmentNumber,
            ],
            [
                'bill_id' => $bill->id,
                'billing_period' => $billingPeriod->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'required_amount' => round((float) $loanInstallment['amount'], 2),
                'amount_paid' => 0,
                'remaining_balance' => round((float) $loanInstallment['amount'], 2),
                'status' => 'Billed',
            ],
        );
    }
}
