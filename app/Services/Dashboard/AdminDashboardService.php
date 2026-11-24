<?php

namespace App\Services\Dashboard;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Incident;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Services\ConsumerBalanceService;
use App\Services\OutstandingBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    private const ACCOUNT_STATUSES = [
        'Active',
        'Pending Verification',
        'For Review',
        'Disconnected',
        'For Reconnection',
        'Rejected',
    ];

    private const LOAN_STATUSES = [
        'Pending Review',
        'Approved',
        'Active',
        'Ongoing',
        'Completed',
        'Rejected',
    ];

    private const INCIDENT_STATUSES = [
        'Pending',
        'Ongoing',
        'For Consumer Confirmation',
        'Resolved',
    ];

    public function __construct(
        private readonly OutstandingBalanceService $outstandingBalances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(DashboardPeriod $period): array
    {
        $financial = $this->financial($period);
        $previousFinancial = $period->allAvailable
            ? ['bills' => 0, 'gross_billed' => 0.0, 'new_charges' => 0.0, 'carry_forward' => 0.0, 'payments' => 0, 'collected' => 0.0, 'collection_rate' => 0.0]
            : $this->financial($period->previous());
        $outstanding = $this->outstandingBalances->summarizeAsOf($period->end());
        $previousOutstanding = $period->allAvailable
            ? ['total' => 0.0, 'accounts' => 0, 'notices' => 0]
            : $this->outstandingBalances->summarizeAsOf($period->previous()->end());
        $monthly = $this->monthlyFinancial($period);
        $consumption = $this->consumption($period);
        $loans = $this->loans($period);
        $incidents = $this->incidents($period);
        $accounts = $this->accounts($period);
        $queues = $this->queues();

        $cards = $this->cards(
            $financial,
            $previousFinancial,
            $outstanding,
            $previousOutstanding,
            $consumption,
            $loans,
            $incidents,
            $accounts,
        );

        return [
            'period' => $period,
            'financial' => $financial,
            'previous_financial' => $previousFinancial,
            'outstanding' => $outstanding,
            'previous_outstanding' => $previousOutstanding,
            'consumption' => $consumption,
            'loans' => $loans,
            'incidents' => $incidents,
            'accounts' => $accounts,
            'queues' => $queues,
            'cards' => $cards,
            'insights' => $this->insights(
                $financial,
                $previousFinancial,
                $outstanding,
                $consumption,
                $loans,
                $incidents,
                $queues,
                $period,
            ),
            'charts' => [
                'labels' => collect($period->months())->map(fn (Carbon $month): string => $month->format('M'))->all(),
                'financial' => [
                    'billed' => $monthly['billed'],
                    'collected' => $monthly['collected'],
                    'collection_rate' => $monthly['collection_rate'],
                    'selected_index' => $period->selectedMonthIndex(),
                    'has_data' => $monthly['has_data'],
                ],
                'outstanding' => [
                    'labels' => collect($outstanding['buckets'])->pluck('label')->all(),
                    'amounts' => collect($outstanding['buckets'])->pluck('amount')->all(),
                    'accounts' => collect($outstanding['buckets'])->pluck('accounts')->all(),
                    'has_data' => $outstanding['total'] > 0,
                ],
                'account_status' => [
                    'labels' => $accounts['labels'],
                    'counts' => $accounts['counts'],
                    'has_data' => $accounts['total'] > 0,
                ],
                'consumption' => [
                    'totals' => $consumption['monthly_totals'],
                    'averages' => $consumption['monthly_averages'],
                    'selected_index' => $period->selectedMonthIndex(),
                    'has_data' => $consumption['has_data'],
                ],
                'loans' => [
                    'applications' => $loans['monthly_applications'],
                    'repayments' => $loans['monthly_repayments'],
                    'selected_index' => $period->selectedMonthIndex(),
                    'has_data' => $loans['has_data'],
                ],
                'incidents' => [
                    'status_labels' => $incidents['status_labels'],
                    'status_counts' => $incidents['status_counts'],
                    'category_labels' => collect($incidents['categories'])->pluck('label')->all(),
                    'category_counts' => collect($incidents['categories'])->pluck('total')->all(),
                    'has_data' => $incidents['total'] > 0,
                ],
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $dates = collect([
            Bill::query()->min('billing_period'),
            Payment::query()->min('payment_date'),
            MeterReading::query()->min('reading_date'),
            Incident::query()->min('reported_at'),
            Loan::query()->min('applied_at'),
            LoanRepayment::query()->min('payment_date'),
            ServiceRequest::query()->min('requested_at'),
            Consumer::query()->min('created_at'),
        ])->filter();

        $currentYear = (int) now()->year;
        $earliest = $dates
            ->map(fn (mixed $date): int => Carbon::parse($date)->year)
            ->min() ?? $currentYear;
        $latest = max(
            $currentYear,
            $dates->map(fn (mixed $date): int => Carbon::parse($date)->year)->max() ?? $currentYear,
        );

        return array_reverse(range(max(2000, $earliest), $latest));
    }

    /**
     * @return array<string, int|float>
     */
    private function financial(DashboardPeriod $period): array
    {
        $billing = Bill::query()
            ->where('bill_type', 'Water')
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('billing_period', [$period->start()->toDateString(), $period->end()->toDateString()]))
            ->selectRaw('COUNT(*) as bills')
            ->selectRaw('COALESCE(SUM(amount), 0) as gross_billed')
            ->selectRaw('COALESCE(SUM(amount - COALESCE(arrears_amount, 0)), 0) as new_charges')
            ->first();

        $payments = Payment::query()
            ->where('payment_type', 'Water')
            ->where('status', 'Verified')
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('payment_date', [$period->start()->toDateString(), $period->end()->toDateString()]))
            ->selectRaw('COUNT(*) as payments')
            ->selectRaw('COALESCE(SUM(amount), 0) as collected')
            ->first();

        $newCharges = round((float) ($billing?->new_charges ?? 0), 2);
        $collected = round((float) ($payments?->collected ?? 0), 2);

        return [
            'bills' => (int) ($billing?->bills ?? 0),
            'gross_billed' => round((float) ($billing?->gross_billed ?? 0), 2),
            'new_charges' => $newCharges,
            'carry_forward' => round(max(0, (float) ($billing?->gross_billed ?? 0) - $newCharges), 2),
            'payments' => (int) ($payments?->payments ?? 0),
            'collected' => $collected,
            'collection_rate' => $newCharges > 0 ? round(($collected / $newCharges) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array{
     *     billed: list<float>,
     *     collected: list<float>,
     *     collection_rate: list<float|null>,
     *     has_data: bool
     * }
     */
    private function monthlyFinancial(DashboardPeriod $period): array
    {
        $billingExpression = $this->monthExpression('billing_period');
        $paymentExpression = $this->monthExpression('payment_date');
        $yearStart = Carbon::create($period->year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($period->year, 12, 31)->endOfDay();

        $billedRows = Bill::query()
            ->where('bill_type', 'Water')
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('billing_period', [$yearStart->toDateString(), $yearEnd->toDateString()]))
            ->selectRaw($billingExpression.' as month_number')
            ->selectRaw('COALESCE(SUM(amount - COALESCE(arrears_amount, 0)), 0) as total')
            ->groupByRaw($billingExpression)
            ->pluck('total', 'month_number');

        $collectedRows = Payment::query()
            ->where('payment_type', 'Water')
            ->where('status', 'Verified')
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('payment_date', [$yearStart->toDateString(), $yearEnd->toDateString()]))
            ->selectRaw($paymentExpression.' as month_number')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupByRaw($paymentExpression)
            ->pluck('total', 'month_number');

        $billed = [];
        $collected = [];
        $rates = [];

        foreach (range(1, 12) as $month) {
            $billedValue = round((float) ($billedRows[$month] ?? 0), 2);
            $collectedValue = round((float) ($collectedRows[$month] ?? 0), 2);

            $billed[] = $billedValue;
            $collected[] = $collectedValue;
            $rates[] = $billedValue > 0 ? round(($collectedValue / $billedValue) * 100, 1) : null;
        }

        return [
            'billed' => $billed,
            'collected' => $collected,
            'collection_rate' => $rates,
            'has_data' => array_sum($billed) > 0 || array_sum($collected) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consumption(DashboardPeriod $period): array
    {
        $totals = MeterReading::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('reading_date', [$period->start()->toDateString(), $period->end()->toDateString()]))
            ->selectRaw('COUNT(*) as readings')
            ->selectRaw('COUNT(DISTINCT consumer_id) as accounts')
            ->selectRaw('COALESCE(SUM(consumption), 0) as total')
            ->selectRaw('COALESCE(AVG(consumption), 0) as average')
            ->first();

        $previousTotal = $period->allAvailable ? 0.0 : (float) MeterReading::query()
            ->whereBetween('reading_date', [
                $period->previous()->start()->toDateString(),
                $period->previous()->end()->toDateString(),
            ])
            ->sum('consumption');

        $expression = $this->monthExpression('reading_date');
        $yearStart = Carbon::create($period->year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($period->year, 12, 31)->endOfDay();
        $monthlyRows = MeterReading::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('reading_date', [$yearStart->toDateString(), $yearEnd->toDateString()]))
            ->selectRaw($expression.' as month_number')
            ->selectRaw('COALESCE(SUM(consumption), 0) as total')
            ->selectRaw('COALESCE(AVG(consumption), 0) as average')
            ->groupByRaw($expression)
            ->get()
            ->keyBy('month_number');

        $monthlyTotals = [];
        $monthlyAverages = [];

        foreach (range(1, 12) as $month) {
            $monthlyTotals[] = round((float) ($monthlyRows[$month]->total ?? 0), 2);
            $monthlyAverages[] = round((float) ($monthlyRows[$month]->average ?? 0), 2);
        }

        $total = round((float) ($totals?->total ?? 0), 2);
        $readings = (int) ($totals?->readings ?? 0);

        return [
            'total' => $total,
            'readings' => $readings,
            'accounts' => (int) ($totals?->accounts ?? 0),
            'average' => $readings > 0 ? round($total / $readings, 2) : 0.0,
            'previous_total' => round($previousTotal, 2),
            'change' => $this->percentChange($total, $previousTotal),
            'monthly_totals' => $monthlyTotals,
            'monthly_averages' => $monthlyAverages,
            'has_data' => array_sum($monthlyTotals) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loans(DashboardPeriod $period): array
    {
        $applicationsQuery = Loan::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('applied_at', [$period->start(), $period->end()]));

        $applications = (clone $applicationsQuery)->count();
        $previousApplications = $period->allAvailable ? 0 : Loan::query()
            ->whereBetween('applied_at', [$period->previous()->start(), $period->previous()->end()])
            ->count();

        $repaymentQuery = LoanRepayment::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('payment_date', [$period->start()->toDateString(), $period->end()->toDateString()]));

        $repayments = round((float) (clone $repaymentQuery)->sum('amount'), 2);
        $repaymentCount = (clone $repaymentQuery)->count();
        $previousRepayments = $period->allAvailable ? 0.0 : round((float) LoanRepayment::query()
            ->whereBetween('payment_date', [
                $period->previous()->start()->toDateString(),
                $period->previous()->end()->toDateString(),
            ])
            ->sum('amount'), 2);

        $statusRows = (clone $applicationsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusLabels = $this->labelsWithExtras(self::LOAN_STATUSES, $statusRows->keys()->all());
        $statusCounts = collect($statusLabels)
            ->map(fn (string $status): int => (int) ($statusRows[$status] ?? 0))
            ->all();

        $applicationExpression = $this->monthExpression('applied_at');
        $repaymentExpression = $this->monthExpression('payment_date');
        $yearStart = Carbon::create($period->year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($period->year, 12, 31)->endOfDay();

        $applicationRows = Loan::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('applied_at', [$yearStart, $yearEnd]))
            ->selectRaw($applicationExpression.' as month_number')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($applicationExpression)
            ->pluck('total', 'month_number');

        $repaymentRows = LoanRepayment::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('payment_date', [$yearStart->toDateString(), $yearEnd->toDateString()]))
            ->selectRaw($repaymentExpression.' as month_number')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupByRaw($repaymentExpression)
            ->pluck('total', 'month_number');

        $monthlyApplications = [];
        $monthlyRepayments = [];

        foreach (range(1, 12) as $month) {
            $monthlyApplications[] = (int) ($applicationRows[$month] ?? 0);
            $monthlyRepayments[] = round((float) ($repaymentRows[$month] ?? 0), 2);
        }

        $portfolioLoans = Loan::query()
            ->whereIn('status', ['Approved', 'Active', 'Ongoing'])
            ->with('installments:id,loan_id,amount_paid')
            ->withSum('repayments as repayments_sum_amount', 'amount')
            ->get(['id', 'amount']);

        $portfolioOutstanding = round((float) $portfolioLoans
            ->sum(fn (Loan $loan): float => $loan->balance_remaining), 2);

        return [
            'applications' => $applications,
            'previous_applications' => $previousApplications,
            'pending_review' => (int) ($statusRows['Pending Review'] ?? 0),
            'approved' => (int) ($statusRows['Approved'] ?? 0),
            'active' => (int) (($statusRows['Active'] ?? 0) + ($statusRows['Ongoing'] ?? 0)),
            'completed' => (int) ($statusRows['Completed'] ?? 0),
            'rejected' => (int) ($statusRows['Rejected'] ?? 0),
            'repayments' => $repayments,
            'repayment_count' => $repaymentCount,
            'previous_repayments' => $previousRepayments,
            'portfolio_outstanding' => $portfolioOutstanding,
            'status_labels' => $statusLabels,
            'status_counts' => $statusCounts,
            'monthly_applications' => $monthlyApplications,
            'monthly_repayments' => $monthlyRepayments,
            'change' => $this->percentChange((float) $applications, (float) $previousApplications),
            'repayment_change' => $this->percentChange($repayments, $previousRepayments),
            'has_data' => array_sum($monthlyApplications) > 0 || array_sum($monthlyRepayments) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function incidents(DashboardPeriod $period): array
    {
        $query = Incident::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('reported_at', [$period->start(), $period->end()]));

        $total = (clone $query)->count();
        $previousTotal = $period->allAvailable ? 0 : Incident::query()
            ->whereBetween('reported_at', [$period->previous()->start(), $period->previous()->end()])
            ->count();

        $statusRows = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusLabels = $this->labelsWithExtras(self::INCIDENT_STATUSES, $statusRows->keys()->all());
        $statusCounts = collect($statusLabels)
            ->map(fn (string $status): int => (int) ($statusRows[$status] ?? 0))
            ->all();

        $open = (int) collect(Incident::OPEN_STATUSES)
            ->sum(fn (string $status): int => (int) ($statusRows[$status] ?? 0));
        $resolved = (int) ($statusRows[Incident::STATUS_RESOLVED] ?? 0);

        $resolvedIncidents = (clone $query)
            ->where('status', Incident::STATUS_RESOLVED)
            ->get(['reported_at', 'resolved_at', 'confirmed_at']);

        $resolutionDays = $resolvedIncidents
            ->map(function (Incident $incident): ?float {
                $resolvedAt = $incident->resolved_at ?? $incident->confirmed_at;

                if ($incident->reported_at === null || $resolvedAt === null) {
                    return null;
                }

                return max(0, round($incident->reported_at->diffInHours($resolvedAt) / 24, 1));
            })
            ->filter(fn (?float $days): bool => $days !== null);

        $categoryRows = (clone $query)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->orderBy('category')
            ->get();

        $categories = $categoryRows
            ->map(fn (Incident $row): array => [
                'label' => $row->category ?: 'Uncategorized',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'previous_total' => $previousTotal,
            'open' => $open,
            'resolved' => $resolved,
            'resolution_rate' => $total > 0 ? round(($resolved / $total) * 100, 1) : 0.0,
            'average_resolution_days' => $resolutionDays->isEmpty()
                ? null
                : round((float) $resolutionDays->avg(), 1),
            'top_category' => $categories[0]['label'] ?? null,
            'status_labels' => $statusLabels,
            'status_counts' => $statusCounts,
            'categories' => $categories,
            'change' => $this->percentChange((float) $total, (float) $previousTotal),
            'has_data' => $total > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accounts(DashboardPeriod $period): array
    {
        $statusRows = Consumer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusLabels = $this->labelsWithExtras(self::ACCOUNT_STATUSES, $statusRows->keys()->all());
        $statusCounts = collect($statusLabels)
            ->map(fn (string $status): int => (int) ($statusRows[$status] ?? 0))
            ->all();

        $newAccounts = Consumer::query()
            ->when(! $period->allAvailable, fn ($query) => $query->whereBetween('created_at', [$period->start(), $period->end()]))
            ->count();

        $previousNewAccounts = $period->allAvailable ? 0 : Consumer::query()
            ->whereBetween('created_at', [$period->previous()->start(), $period->previous()->end()])
            ->count();

        $pendingMembership = Consumer::query()
            ->where('requested_membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
            ->where('membership_verification_status', Consumer::MEMBERSHIP_VERIFICATION_PENDING)
            ->count();

        return [
            'labels' => $statusLabels,
            'counts' => $statusCounts,
            'total' => array_sum($statusCounts),
            'new_accounts' => $newAccounts,
            'previous_new_accounts' => $previousNewAccounts,
            'pending_membership' => $pendingMembership,
            'members' => Consumer::query()
                ->where('membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                ->count(),
            'verified_accounts' => Consumer::query()->verified()->count(),
            'change' => $this->percentChange((float) $newAccounts, (float) $previousNewAccounts),
            'has_data' => array_sum($statusCounts) > 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queues(): array
    {
        $pendingPayments = Payment::query()->where('status', 'Pending');
        $pendingPaymentCount = (clone $pendingPayments)->count();
        $pendingPaymentAmount = round((float) (clone $pendingPayments)->sum('amount'), 2);

        $serviceRequests = ServiceRequest::query()->where('status', ServiceRequest::STATUS_PENDING)->count();
        $openIncidents = Incident::query()->whereIn('status', Incident::OPEN_STATUSES)->count();
        $notices = Bill::query()
            ->where('bill_type', 'Water')
            ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->whereNull('carried_forward_to_bill_id')
            ->distinct('consumer_id')
            ->count('consumer_id');

        $items = [
            [
                'key' => 'disconnection-notices',
                'label' => 'Disconnection notices',
                'count' => $notices,
                'description' => 'Accounts with an active notice require full payment or follow-up.',
                'url' => route('billing.index'),
                'action' => 'Review notices',
                'icon' => 'bi-exclamation-octagon',
                'tone' => 'danger',
            ],
            [
                'key' => 'pending-payments',
                'label' => 'Payments awaiting verification',
                'count' => $pendingPaymentCount,
                'description' => '₱'.number_format($pendingPaymentAmount, 2).' in submitted payments is waiting for admin review.',
                'url' => route('payments.index', ['status' => 'Pending']),
                'action' => 'Verify payments',
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
            ],
            [
                'key' => 'encoded-readings',
                'label' => 'Readings ready for billing',
                'count' => MeterReading::query()
                    ->where('status', 'Encoded')
                    ->whereDoesntHave('bill')
                    ->count(),
                'description' => 'Encoded meter readings that have not yet been turned into bills.',
                'url' => route('meter-readings.index', ['status' => 'Encoded']),
                'action' => 'Open readings',
                'icon' => 'bi-speedometer2',
                'tone' => 'info',
            ],
            [
                'key' => 'service-requests',
                'label' => 'Service requests',
                'count' => $serviceRequests,
                'description' => 'New connection and reconnection requests waiting for a decision.',
                'url' => route('billing.index'),
                'action' => 'Review requests',
                'icon' => 'bi-tools',
                'tone' => 'info',
            ],
            [
                'key' => 'pending-loans',
                'label' => 'Loan applications to review',
                'count' => Loan::query()->where('status', 'Pending Review')->count(),
                'description' => 'Applications waiting for eligibility review or approval.',
                'url' => route('loans.index', ['status' => 'Pending Review']),
                'action' => 'Open loans',
                'icon' => 'bi-bank',
                'tone' => 'warning',
            ],
            [
                'key' => 'open-incidents',
                'label' => 'Open incidents',
                'count' => $openIncidents,
                'description' => 'Reported service issues that are not yet resolved.',
                'url' => route('incidents.index', ['status' => 'Pending']),
                'action' => 'Open incidents',
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'danger',
            ],
            [
                'key' => 'membership-verification',
                'label' => 'Membership verification',
                'count' => Consumer::query()
                    ->where('requested_membership_type', Consumer::MEMBERSHIP_REQUEST_MEMBER)
                    ->where('membership_verification_status', Consumer::MEMBERSHIP_VERIFICATION_PENDING)
                    ->count(),
                'description' => 'Member claims that still need supporting-document review.',
                'url' => route('consumers.index', ['membership' => 'pending_member_verification']),
                'action' => 'Review membership',
                'icon' => 'bi-person-check',
                'tone' => 'warning',
            ],
        ];

        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cards(
        array $financial,
        array $previousFinancial,
        array $outstanding,
        array $previousOutstanding,
        array $consumption,
        array $loans,
        array $incidents,
        array $accounts,
    ): array {
        return [
            [
                'key' => 'billed',
                'label' => 'New Charges Billed',
                'value' => $financial['new_charges'],
                'type' => 'money',
                'icon' => 'bi-receipt',
                'tone' => 'primary',
                'note' => $financial['bills'] > 0
                    ? number_format($financial['bills']).' bills · gross ₱'.number_format($financial['gross_billed'], 2)
                        .' includes ₱'.number_format($financial['carry_forward'], 2).' carry-forward'
                    : 'No bills generated in the available records.',
                'change' => $this->change(
                    $this->percentChange($financial['new_charges'], $previousFinancial['new_charges']),
                    'percent',
                ),
            ],
            [
                'key' => 'collected',
                'label' => 'Verified Collections',
                'value' => $financial['collected'],
                'type' => 'money',
                'icon' => 'bi-cash-stack',
                'tone' => 'success',
                'note' => $financial['payments'] > 0
                    ? number_format($financial['payments']).' verified payments in the available records'
                    : 'No verified payments in the available records.',
                'change' => $this->change(
                    $this->percentChange($financial['collected'], $previousFinancial['collected']),
                    'percent',
                ),
            ],
            [
                'key' => 'collection-rate',
                'label' => 'Collection Rate',
                'value' => $financial['collection_rate'],
                'type' => 'percent',
                'icon' => 'bi-graph-up-arrow',
                'tone' => $financial['new_charges'] <= 0
                    ? 'primary'
                    : ($financial['collection_rate'] >= 90 ? 'success' : ($financial['collection_rate'] >= 70 ? 'warning' : 'danger')),
                'note' => $financial['new_charges'] > 0
                    ? 'Verified collections ÷ new charges (excludes carried arrears)'
                    : 'No new charges to measure collections against.',
                'change' => $this->change(
                    round($financial['collection_rate'] - $previousFinancial['collection_rate'], 1),
                    'points',
                ),
            ],
            [
                'key' => 'outstanding',
                'label' => 'Outstanding Balance',
                'value' => $outstanding['total'],
                'type' => 'money',
                'icon' => 'bi-wallet2',
                'tone' => $outstanding['total'] <= 0
                    ? 'success'
                    : ($outstanding['notices'] > 0 ? 'danger' : 'warning'),
                'note' => 'As of '.$this->periodEndLabel($outstanding)
                    .' · '.number_format($outstanding['accounts']).' accounts · '
                    .number_format($outstanding['notices']).' notices',
                'change' => $this->change(
                    $this->percentChange($outstanding['total'], $previousOutstanding['total']),
                    'percent',
                    true,
                ),
            ],
            [
                'key' => 'consumption',
                'label' => 'Water Consumption',
                'value' => $consumption['total'],
                'type' => 'volume',
                'icon' => 'bi-droplet',
                'tone' => 'info',
                'note' => $consumption['readings'] > 0
                    ? number_format($consumption['readings']).' readings · avg '
                        .number_format($consumption['average'], 2).' m³ per reading'
                    : 'No meter readings in the available records.',
                'change' => $this->change($consumption['change'], 'percent'),
            ],
            [
                'key' => 'accounts',
                'label' => 'New Accounts',
                'value' => $accounts['new_accounts'],
                'type' => 'number',
                'icon' => 'bi-person-plus',
                'tone' => 'primary',
                'note' => number_format($accounts['pending_membership']).' pending membership verification'
                    .($accounts['pending_membership'] === 1 ? '' : 's'),
                'change' => $this->change($accounts['change'], 'percent'),
            ],
            [
                'key' => 'incidents',
                'label' => 'Incidents Reported',
                'value' => $incidents['total'],
                'type' => 'number',
                'icon' => 'bi-exclamation-triangle',
                'tone' => $incidents['open'] > 0 ? 'warning' : 'success',
                'note' => $incidents['total'] > 0
                    ? number_format($incidents['open']).' open · '
                        .number_format($incidents['resolved']).' resolved'
                        .($incidents['average_resolution_days'] !== null
                            ? ' · avg '.number_format($incidents['average_resolution_days'], 1).' days'
                            : '')
                    : 'No incidents reported in the available records.',
                'change' => $this->change($incidents['change'], 'percent', true),
            ],
            [
                'key' => 'loans',
                'label' => 'Loan Applications',
                'value' => $loans['applications'],
                'type' => 'number',
                'icon' => 'bi-bank',
                'tone' => 'warning',
                'note' => ($loans['applications'] > 0 || $loans['repayment_count'] > 0)
                    ? number_format($loans['pending_review']).' pending review · ₱'
                        .number_format($loans['repayments'], 2).' repayments posted'
                    : 'No applications or posted repayments in the available records.',
                'change' => $this->change($loans['change'], 'percent'),
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string, tone: string, icon: string}>
     */
    private function insights(
        array $financial,
        array $previousFinancial,
        array $outstanding,
        array $consumption,
        array $loans,
        array $incidents,
        array $queues,
        DashboardPeriod $period,
    ): array {
        $insights = [];

        $rate = $financial['collection_rate'];
        $rateComparison = $previousFinancial['collection_rate'] > 0
            ? ' That is '.number_format(abs($rate - $previousFinancial['collection_rate']), 1).' points '
                .($rate >= $previousFinancial['collection_rate'] ? 'higher' : 'lower')
                .' than '.$period->previousLabel().'.'
            : '';

        $collectionBody = $financial['new_charges'] > 0
            ? '₱'.number_format($financial['collected'], 2).' was collected against ₱'
                .number_format($financial['new_charges'], 2).' in new charges ('.number_format($rate, 1).'%).'
                .$rateComparison
            : 'No new charges were recorded for '.$period->label().'. Verified collections were ₱'
                .number_format($financial['collected'], 2).'.';

        $insights[] = [
            'title' => 'Collection performance',
            'body' => $collectionBody,
            'tone' => $financial['new_charges'] <= 0 ? 'info' : ($rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger')),
            'icon' => 'bi-cash-stack',
        ];

        $insights[] = [
            'title' => 'Receivables snapshot',
            'body' => '₱'.number_format($outstanding['total'], 2).' is outstanding as of '
                .$this->periodEndLabel($outstanding).' across '.number_format($outstanding['accounts'])
                .' accounts. '.number_format($outstanding['notices'])
                .' accounts carry a disconnection notice.',
            'tone' => $outstanding['notices'] > 0 ? 'danger' : 'warning',
            'icon' => 'bi-wallet2',
        ];

        $consumptionBody = $consumption['readings'] > 0
            ? number_format($consumption['total'], 2).' m³ was recorded across '
                .number_format($consumption['readings']).' readings, averaging '
                .number_format($consumption['average'], 2).' m³ per reading.'
            : 'No meter readings were recorded for '.$period->label().'.';

        $insights[] = [
            'title' => 'Consumption',
            'body' => $consumptionBody,
            'tone' => 'info',
            'icon' => 'bi-droplet',
        ];

        $incidentBody = $incidents['total'] > 0
            ? number_format($incidents['total']).' incidents were reported. '
                .number_format($incidents['open']).' remain open and '
                .number_format($incidents['resolved']).' are resolved.'
                .($incidents['top_category'] !== null
                    ? ' The most reported category is '.$incidents['top_category'].'.'
                    : '')
            : 'No incidents were reported for '.$period->label().'.';

        $insights[] = [
            'title' => 'Incident response',
            'body' => $incidentBody,
            'tone' => $incidents['open'] > 0 ? 'warning' : 'success',
            'icon' => 'bi-exclamation-triangle',
        ];

        $loanBody = ($loans['applications'] > 0 || $loans['repayment_count'] > 0)
            ? number_format($loans['applications']).' applications and ₱'
                .number_format($loans['repayments'], 2).' in posted repayments across available records. As of today, ₱'
                .number_format($loans['portfolio_outstanding'], 2).' remains on active loan balances.'
                .($loans['repayment_count'] === 0
                    ? ' No repayment entries were posted in the available records.'
                    : '')
            : 'No loan applications or posted repayments in the available records. As of today, ₱'
                .number_format($loans['portfolio_outstanding'], 2).' remains on active loan balances.';

        $insights[] = [
            'title' => 'Loan portfolio',
            'body' => $loanBody,
            'tone' => $loans['repayment_count'] === 0 ? 'warning' : 'info',
            'icon' => 'bi-bank',
        ];

        $queueCount = collect($queues)->sum('count');
        $insights[] = [
            'title' => 'Operational queue',
            'body' => $queueCount > 0
                ? number_format($queueCount).' items need review across the current operational queues.'
                : 'No pending operational items need review right now.',
            'tone' => $queueCount > 0 ? 'warning' : 'success',
            'icon' => 'bi-list-check',
        ];

        return $insights;
    }

    /**
     * @return array{value: float|null, unit: string, tone: string, label: string}
     */
    private function change(?float $value, string $unit, bool $inverse = false): array
    {
        if ($value === null) {
            return [
                'value' => null,
                'unit' => $unit,
                'tone' => 'neutral',
                'label' => 'No prior comparison',
            ];
        }

        $tone = abs($value) < 0.05
            ? 'neutral'
            : (($inverse ? $value < 0 : $value > 0) ? 'success' : 'danger');

        return [
            'value' => $value,
            'unit' => $unit,
            'tone' => $tone,
            'label' => ($value > 0 ? '+' : '').number_format($value, 1)
                .($unit === 'points' ? ' pts' : '%'),
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @param  list<string>  $preferred
     * @param  list<string>  $actual
     * @return list<string>
     */
    private function labelsWithExtras(array $preferred, array $actual): array
    {
        $extras = collect($actual)
            ->filter(fn (mixed $status): bool => filled($status) && ! in_array($status, $preferred, true))
            ->map(fn (mixed $status): string => (string) $status)
            ->sort()
            ->values()
            ->all();

        return array_values(array_unique([...$preferred, ...$extras]));
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(MONTH FROM {$column})::int",
            default => "MONTH({$column})",
        };
    }

    private function periodEndLabel(array $outstanding): string
    {
        return isset($outstanding['as_of'])
            ? Carbon::parse($outstanding['as_of'])->format('M d, Y')
            : now()->format('M d, Y');
    }
}
