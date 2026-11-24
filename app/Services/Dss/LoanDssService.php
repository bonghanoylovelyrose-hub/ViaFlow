<?php

namespace App\Services\Dss;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Support\FilterYears;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LoanDssService
{
    public const UNSPECIFIED = '__unspecified__';

    private const REJECTED_STATUS = 'Rejected';

    private const APPROVED_STATUSES = ['Ready for Scheduling', 'Approved', 'Active', 'Ongoing', 'Completed'];

    private const PORTFOLIO_STATUSES = ['Approved', 'Active', 'Ongoing', 'Completed'];

    private const ACTIVE_PRINCIPAL_STATUSES = ['Approved', 'Active', 'Ongoing'];

    private const STATUSES = [
        'Pending Review',
        'Needs Review',
        'Ready for Scheduling',
        'Approved',
        'Active',
        'Ongoing',
        'Completed',
        'Rejected',
    ];

    private const RISKS = ['Low Risk', 'Medium Risk', 'High Risk'];

    private const STANDARD_TERMS = [3, 6, 12];

    private const BILLED_INSTALLMENT_STATUSES = ['Billed', 'Partially Paid', 'Paid', 'Overdue'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $allLoans = Loan::query()
            ->with(['consumer', 'repayments' => fn ($query) => $query->recordedOrVerified()->with('payment'), 'installments.repaymentAllocations'])
            ->orderBy('applied_at')
            ->orderBy('id')
            ->get();

        $repaymentLoans = $allLoans->whereIn('status', [...Loan::PAYMENT_STATUSES, 'Completed']);
        $repayments = $this->repaymentEntries($repaymentLoans);
        $options = $this->options($allLoans, $repayments);
        $year = filter_var($filters['year'] ?? null, FILTER_VALIDATE_INT);
        $chartYear = in_array($year, $options['years'], true) ? $year : null;
        $chartMonth = filter_var($filters['month'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12],
        ]) ?: null;
        $normalizedFilters = [
            'year' => $chartYear ?? 'all',
            'month' => $chartMonth,
            'status' => null,
            'risk' => null,
            'term' => null,
        ];
        $filteredLoans = $allLoans;

        $total = $filteredLoans->count();
        $pendingLoans = $filteredLoans->whereIn('status', Loan::PENDING_STATUSES);
        $approvedLoans = $filteredLoans->filter(fn (Loan $loan): bool => in_array($loan->status, self::APPROVED_STATUSES, true));
        $rejectedLoans = $filteredLoans->where('status', self::REJECTED_STATUS);
        $completedLoans = $filteredLoans->where('status', 'Completed');
        $pending = $pendingLoans->count();
        $approved = $approvedLoans->count();
        $rejected = $rejectedLoans->count();
        $completed = $completedLoans->count();
        $decided = $approved + $rejected;

        $pendingAmount = $pendingLoans->sum(fn (Loan $loan): float => (float) $loan->amount);
        $rejectedAmount = $rejectedLoans->sum(fn (Loan $loan): float => (float) $loan->amount);
        $approvedPrincipal = $filteredLoans
            ->filter(fn (Loan $loan): bool => in_array($loan->status, self::ACTIVE_PRINCIPAL_STATUSES, true))
            ->sum(fn (Loan $loan): float => (float) $loan->amount);

        $releasedLoans = $repaymentLoans->filter(fn (Loan $loan): bool => in_array($loan->status, self::PORTFOLIO_STATUSES, true)
            || $loan->released_at !== null || $loan->installation_status === 'Completed');
        $repaymentTotals = $repayments->groupBy('loan_id')->map(fn (Collection $items): float => (float) $items->sum('amount'));
        $this->applyUnallocatedCredits($repaymentLoans, $repaymentTotals);
        $outstandingByLoan = $repaymentLoans->mapWithKeys(fn (Loan $loan): array => [
            $loan->id => round(max(0, (float) $loan->amount - max((float) ($repaymentTotals[$loan->id] ?? 0), (float) $loan->installments->sum('amount_paid'))), 2),
        ]);
        $portfolioLoans = $repaymentLoans->filter(fn (Loan $loan): bool => (float) $outstandingByLoan[$loan->id] > 0);

        $outstandingBalance = $portfolioLoans->sum(fn (Loan $loan): float => $outstandingByLoan[$loan->id]);
        $totalReleased = $releasedLoans->sum(fn (Loan $loan): float => (float) $loan->amount);
        $totalAmount = $filteredLoans->sum(fn (Loan $loan): float => (float) $loan->amount);
        $repaymentLoanAmount = $repaymentLoans->sum(fn (Loan $loan): float => (float) $loan->amount);
        $totalRepaid = $repayments->sum('amount');

        $installments = $repaymentLoans
            ->flatMap(fn (Loan $loan): Collection => $loan->installments)
            ->values();

        $billedInstallments = $installments
            ->filter(fn (LoanInstallment $installment): bool => in_array($installment->status, self::BILLED_INSTALLMENT_STATUSES, true) && $installment->countsAsBilled())
            ->values();
        $paidInstallments = $installments
            ->filter(fn (LoanInstallment $installment): bool => $this->installmentRemaining($installment) <= 0)
            ->values();

        $billedRequired = $billedInstallments->sum(fn (LoanInstallment $installment): float => (float) $installment->required_amount);
        $billedPaid = $billedInstallments->sum(fn (LoanInstallment $installment): float => (float) $installment->amount_paid);

        $collectionRate = null;

        if ($billedRequired > 0) {
            $collectionRate = round(($billedPaid / $billedRequired) * 100, 1);
        } elseif ($repaymentLoans->isNotEmpty() && $repaymentLoanAmount > 0 && $repayments->isNotEmpty()) {
            $collectionRate = round(($totalRepaid / $repaymentLoanAmount) * 100, 1);
        }

        $onTimeInstallments = $paidInstallments->filter(
            fn (LoanInstallment $installment): bool => $installment->payment_date !== null
                && $installment->due_date !== null
                && ! $installment->payment_date->gt($installment->due_date)
        );
        $lateInstallments = $paidInstallments->filter(
            fn (LoanInstallment $installment): bool => $installment->payment_date !== null
                && $installment->due_date !== null
                && $installment->payment_date->gt($installment->due_date)
        );

        $paidWithDueDate = $paidInstallments->filter(fn (LoanInstallment $installment): bool => $installment->payment_date !== null && $installment->due_date !== null);
        $onTimeRate = $paidWithDueDate->isNotEmpty()
            ? round(($onTimeInstallments->count() / $paidWithDueDate->count()) * 100, 1)
            : null;

        $averageDaysLate = $lateInstallments->isNotEmpty()
            ? round((float) $lateInstallments->avg(fn (LoanInstallment $installment): float => (float) $installment->due_date->diffInDays($installment->payment_date, false)), 1)
            : null;

        $overdueDetails = [];
        $agingAmounts = [
            'Current' => 0.0,
            '1-30 days' => 0.0,
            '31-60 days' => 0.0,
            '61-90 days' => 0.0,
            '90+ days' => 0.0,
        ];

        foreach ($portfolioLoans as $loan) {
            $detail = $this->overdueDetail($loan, (float) $outstandingByLoan[$loan->id]);
            $overdueDetails[$loan->id] = $detail;

            if (! $detail['is_overdue']) {
                continue;
            }

            if ($loan->installments->isNotEmpty()) {
                foreach ($loan->installments as $installment) {
                    if (! $this->installmentIsOverdue($installment)) {
                        continue;
                    }

                    $days = (int) max(0, $installment->due_date->copy()->startOfDay()->diffInDays(today(), false));
                    $agingAmounts[$this->agingBucket($days)] += $this->installmentRemaining($installment);
                }
            } else {
                $agingAmounts[$this->agingBucket($detail['days'])] += (float) $detail['amount'];
            }
        }

        $overdueLoans = collect($overdueDetails)
            ->filter(fn (array $detail): bool => $detail['is_overdue']);
        $overdueCount = $overdueLoans->count();
        $overdueAmount = $overdueLoans->sum(fn (array $detail): float => (float) $detail['amount']);
        $overdueInstallmentCount = $overdueLoans->sum(fn (array $detail): int => (int) $detail['installment_count']);
        $overdueAgingAmount = array_sum($agingAmounts) - $agingAmounts['Current'];
        $agingAmounts['Current'] = round(max(0, $outstandingBalance - $overdueAgingAmount), 2);
        $agingAmounts = array_map(fn (float $amount): float => round($amount, 2), $agingAmounts);

        $oldestPendingDays = $pendingLoans
            ->filter(fn (Loan $loan): bool => $loan->applied_at !== null)
            ->map(fn (Loan $loan): int => (int) max(0, $loan->applied_at->copy()->startOfDay()->diffInDays(today(), false)))
            ->max() ?? 0;

        $approvalDurations = $approvedLoans
            ->filter(fn (Loan $loan): bool => $loan->applied_at !== null && $loan->approved_at !== null && ! $loan->approved_at->lt($loan->applied_at))
            ->map(fn (Loan $loan): float => round((float) $loan->applied_at->diffInHours($loan->approved_at, false) / 24, 1));

        $invalidApprovalTimestamps = $approvedLoans
            ->filter(fn (Loan $loan): bool => $loan->applied_at !== null && $loan->approved_at !== null && $loan->approved_at->lt($loan->applied_at))
            ->count();

        $highRisk = $filteredLoans->where('risk_level', 'High Risk')->count();
        $unassessedRisk = $filteredLoans->filter(fn (Loan $loan): bool => blank($loan->risk_level))->count();
        $zeroAmountApproved = $approvedLoans->filter(fn (Loan $loan): bool => (float) $loan->amount <= 0)->count();

        $summary = [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'completed' => $completed,
            'decided' => $decided,
            'approval_rate' => $decided > 0 ? round(($approved / $decided) * 100, 1) : null,
            'rejection_rate' => $decided > 0 ? round(($rejected / $decided) * 100, 1) : null,
            'decision_coverage' => $total > 0 ? round(($decided / $total) * 100, 1) : null,
            'total_amount' => round($totalAmount, 2),
            'total_released' => round((float) $totalReleased, 2),
            'pending_requested_amount' => round($pendingAmount, 2),
            'rejected_requested_amount' => round($rejectedAmount, 2),
            'approved_principal' => round($approvedPrincipal, 2),
            'outstanding_balance' => round($outstandingBalance, 2),
            'total_repaid' => round($totalRepaid, 2),
            'portfolio_count' => $portfolioLoans->count(),
            'overdue_count' => $overdueCount,
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_installments' => $overdueInstallmentCount,
            'delinquency_rate' => $portfolioLoans->isNotEmpty()
                ? round(($overdueCount / $portfolioLoans->count()) * 100, 1)
                : null,
            'collection_rate' => $collectionRate,
            'on_time_rate' => $onTimeRate,
            'average_days_late' => $averageDaysLate,
            'installments_billed' => $billedInstallments->count(),
            'installments_paid' => $paidInstallments->count(),
            'installments_overdue' => $overdueInstallmentCount,
            'has_installments' => $installments->isNotEmpty(),
            'has_repayments' => $repayments->isNotEmpty(),
            'oldest_pending_days' => $oldestPendingDays,
            'average_approval_days' => $approvalDurations->isEmpty()
                ? null
                : round((float) $approvalDurations->avg(), 1),
            'invalid_approval_timestamps' => $invalidApprovalTimestamps,
            'high_risk' => $highRisk,
            'unassessed_risk' => $unassessedRisk,
            'zero_amount_approved' => $zeroAmountApproved,
        ];

        $hasLoanRecords = $allLoans->isNotEmpty();
        $hasRepaymentLoans = $repaymentLoans->isNotEmpty();
        $hasNoRecordedPaymentsOnSchedules = $installments->isNotEmpty()
            && $installments->every(fn (LoanInstallment $installment): bool => (float) $installment->amount_paid <= 0);
        $hasRepaymentEvidence = $repayments->isNotEmpty() || $hasNoRecordedPaymentsOnSchedules;
        $hasDueDateEvidence = $installments->contains(fn (LoanInstallment $installment): bool => $installment->due_date !== null)
            || $repaymentLoans->contains(fn (Loan $loan): bool => $loan->next_due_date !== null && (float) $loan->monthly_installment > 0);

        $statusBreakdown = $this->breakdown($filteredLoans->pluck('status'), self::STATUSES, $total);
        $riskBreakdown = $this->riskBreakdown($filteredLoans, $total);
        $termBreakdown = $this->termBreakdown($filteredLoans, $total);
        $monthly = $this->monthly($allLoans, $releasedLoans, $repayments, $chartYear, $chartMonth);
        $applications = $this->applications($filteredLoans, $overdueDetails, $outstandingByLoan, $repaymentTotals);
        $overdueLocations = $this->overdueByLocation($portfolioLoans, $overdueDetails);
        $repaymentStatus = $this->repaymentStatus($repaymentLoans, $outstandingByLoan->all());

        return [
            'filters' => $normalizedFilters,
            'options' => $options,
            'period_label' => $this->periodLabel($normalizedFilters),
            'summary' => $summary,
            'metric_availability' => [
                'total_released' => $hasLoanRecords,
                'total_repaid' => $hasLoanRecords && (! $hasRepaymentLoans || $hasRepaymentEvidence),
                'outstanding_balance' => $hasLoanRecords && (! $hasRepaymentLoans || $hasRepaymentEvidence),
                'overdue_amount' => $hasLoanRecords && (! $hasRepaymentLoans || $hasDueDateEvidence),
            ],
            'status_breakdown' => $statusBreakdown,
            'risk_breakdown' => $riskBreakdown,
            'term_breakdown' => $termBreakdown,
            'aging' => [
                'labels' => array_keys($agingAmounts),
                'amounts' => array_values($agingAmounts),
            ],
            'monthly' => $monthly,
            'overdue_locations' => $overdueLocations,
            'repayment_status' => $repaymentStatus,
            'applications' => $applications,
            'recommendations' => $this->collectionRecommendations($overdueLocations, $repaymentStatus['upcoming_due']),
            'data_notes' => $this->dataNotes($summary),
            'charts' => [
                'has_data' => $total > 0,
                'status' => [
                    'labels' => $filteredLoans->pluck('status')->filter(fn (mixed $status): bool => filled($status))->countBy()->sortKeys()->keys()->values()->all(),
                    'counts' => $filteredLoans->pluck('status')->filter(fn (mixed $status): bool => filled($status))->countBy()->sortKeys()->values()->all(),
                ],
                'risk' => [
                    'labels' => collect($riskBreakdown)->pluck('label')->all(),
                    'counts' => collect($riskBreakdown)->pluck('total')->all(),
                ],
                'aging' => [
                    'labels' => array_keys($agingAmounts),
                    'amounts' => array_values($agingAmounts),
                ],
                'monthly' => $monthly,
                'overdue_locations' => [
                    'labels' => collect($overdueLocations)->pluck('location')->all(),
                    'amounts' => collect($overdueLocations)->pluck('amount')->all(),
                    'has_schedule_data' => $hasDueDateEvidence,
                ],
                'repayment_status' => $repaymentStatus,
            ],
        ];
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return array<string, mixed>
     */
    private function options(Collection $loans, Collection $repayments): array
    {
        $years = $loans
            ->pluck('applied_at')
            ->concat($loans->pluck('approved_at'))
            ->concat($loans->map(fn (Loan $loan) => $this->releaseDate($loan)))
            ->concat($repayments->pluck('date'))
            ->filter()
            ->map(fn (Carbon $date): int => (int) $date->year);

        $terms = $loans
            ->pluck('term_months')
            ->filter()
            ->map(fn (mixed $term): int => (int) $term)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $terms = collect([...self::STANDARD_TERMS, ...$terms])->unique()->sort()->values()->all();

        return [
            'years' => FilterYears::available($years),
            'months' => collect(range(1, 12))
                ->map(fn (int $month): array => [
                    'value' => $month,
                    'label' => Carbon::create(2000, $month, 1)->format('F'),
                ])
                ->all(),
            'statuses' => $this->labelsWithExtras(self::STATUSES, $loans->pluck('status')),
            'risks' => [
                ...$this->labelsWithExtras(self::RISKS, $loans->pluck('risk_level')),
                self::UNSPECIFIED,
            ],
            'terms' => $terms,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $options
     * @return array{year: int, month: ?int, status: ?string, risk: ?string, term: ?int}
     */
    private function normalizeFilters(array $filters, array $options): array
    {
        $year = filter_var($filters['year'] ?? null, FILTER_VALIDATE_INT);

        if ($year === false || ! in_array($year, $options['years'], true)) {
            $year = now()->year;
        }

        $month = filter_var($filters['month'] ?? null, FILTER_VALIDATE_INT);

        if ($month === false || $month < 1 || $month > 12) {
            $month = null;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        $risk = trim((string) ($filters['risk'] ?? ''));
        $term = filter_var($filters['term'] ?? null, FILTER_VALIDATE_INT);

        return [
            'year' => $year,
            'month' => $month,
            'status' => in_array($status, $options['statuses'], true) ? $status : null,
            'risk' => in_array($risk, $options['risks'], true) ? $risk : null,
            'term' => $term === false || $term === null || ! in_array($term, $options['terms'], true) ? null : $term,
        ];
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @param  array{year: int, month: ?int, status: ?string, risk: ?string, term: ?int}  $filters
     * @return Collection<int, Loan>
     */
    private function applyFilters(Collection $loans, array $filters, bool $includeMonth, bool $includeYear = true): Collection
    {
        return $loans->filter(function (Loan $loan) use ($filters, $includeMonth, $includeYear): bool {
            if ($includeYear && ($loan->applied_at === null || (int) $loan->applied_at->year !== $filters['year'])) {
                return false;
            }

            if ($includeMonth && $filters['month'] !== null && (int) $loan->applied_at?->month !== $filters['month']) {
                return false;
            }

            if ($filters['status'] !== null && $loan->status !== $filters['status']) {
                return false;
            }

            if ($filters['risk'] !== null && ! $this->matchesOptionalFilter($loan->risk_level, $filters['risk'])) {
                return false;
            }

            if ($filters['term'] !== null && (int) $loan->term_months !== $filters['term']) {
                return false;
            }

            return true;
        })->values();
    }

    private function matchesOptionalFilter(?string $value, string $filter): bool
    {
        return $filter === self::UNSPECIFIED
            ? blank($value)
            : $value === $filter;
    }

    /**
     * @param  Collection<int, string|null>  $values
     * @param  list<string>  $preferred
     * @return list<array{label: string, total: int, share: float}>
     */
    private function breakdown(Collection $values, array $preferred, int $total): array
    {
        $counts = $values
            ->filter(fn (mixed $value): bool => filled($value))
            ->countBy();

        $labels = $this->labelsWithExtras($preferred, $counts->keys());

        return collect($labels)
            ->map(fn (string $label): array => [
                'label' => $label,
                'total' => (int) ($counts[$label] ?? 0),
                'share' => $total > 0 ? round((((int) ($counts[$label] ?? 0)) / $total) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return list<array{label: string, total: int, share: float}>
     */
    private function riskBreakdown(Collection $loans, int $total): array
    {
        $counts = $loans
            ->groupBy(fn (Loan $loan): string => $loan->risk_level ?: 'Not assessed')
            ->map(fn (Collection $group): int => $group->count());

        $labels = $this->labelsWithExtras([...self::RISKS, 'Not assessed'], $counts->keys());

        return collect($labels)
            ->map(fn (string $label): array => [
                'label' => $label,
                'total' => (int) ($counts[$label] ?? 0),
                'share' => $total > 0 ? round((((int) ($counts[$label] ?? 0)) / $total) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return list<array{label: string, total: int, share: float}>
     */
    private function termBreakdown(Collection $loans, int $total): array
    {
        $counts = $loans
            ->filter(fn (Loan $loan): bool => (int) $loan->term_months > 0)
            ->groupBy(fn (Loan $loan): int => (int) $loan->term_months)
            ->map(fn (Collection $group): int => $group->count())
            ->sortKeys();

        $labels = $counts->keys()->map(fn (int $term): string => $term.' month'.($term === 1 ? '' : 's'))->all();

        return collect($labels)
            ->map(function (string $label) use ($counts, $total): array {
                $term = (int) explode(' ', $label)[0];
                $count = (int) ($counts[$term] ?? 0);

                return [
                    'label' => $label,
                    'total' => $count,
                    'share' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return array<string, mixed>
     */
    private function monthly(Collection $loans, Collection $releasedLoans, Collection $repayments, ?int $year, ?int $month): array
    {
        $months = $month === null ? range(1, 12) : [$month];
        $labels = collect($months)
            ->map(fn (int $month): string => Carbon::create(2000, $month, 1)->format('M'))
            ->all();
        $matchesPeriod = fn (?Carbon $date): bool => $date !== null
            && ($year === null || (int) $date->year === $year)
            && ($month === null || (int) $date->month === $month);

        $applications = array_fill_keys($months, 0);
        $approvals = array_fill_keys($months, 0);
        $collections = array_fill_keys($months, 0.0);
        $releases = array_fill_keys($months, 0.0);

        foreach ($releasedLoans as $loan) {
            $date = $this->releaseDate($loan);
            if ($matchesPeriod($date) && (! $loan->applied_at || ! $date->lt($loan->applied_at))) {
                $releases[$date->month] += (float) $loan->amount;
            }
        }

        foreach ($loans as $loan) {
            if ($matchesPeriod($loan->applied_at)) {
                $applications[$loan->applied_at->month]++;
            }
            if ($matchesPeriod($loan->approved_at)) {
                $approvals[$loan->approved_at->month]++;
            }
        }

        foreach ($repayments as $repayment) {
            $date = $repayment['date'];
            if ($matchesPeriod($date)) {
                $collections[$date->month] += $repayment['amount'];
            }
        }

        $applications = array_values($applications);
        $approvals = array_values($approvals);
        $collections = array_values(array_map(fn (float $amount): float => round($amount, 2), $collections));
        $releases = array_values(array_map(fn (float $amount): float => round($amount, 2), $releases));

        return [
            'labels' => $labels,
            'applications' => $applications,
            'approvals' => $approvals,
            'collections' => $collections,
            'releases' => $releases,
            'repayments' => $collections,
            'has_data' => array_sum($releases) > 0 || array_sum($collections) > 0,
        ];
    }

    private function releaseDate(Loan $loan): ?Carbon
    {
        return $loan->released_at
            ?? ($loan->installation_status === 'Completed' ? $loan->installation_date : null)
            ?? $loan->approved_at;
    }

    private function repaymentEntries(Collection $loans): Collection
    {
        $entries = $loans->flatMap(fn (Loan $loan) => $loan->repayments->map(fn ($repayment) => [
            'loan_id' => $loan->id,
            'amount' => (float) $repayment->amount,
            'date' => $repayment->payment_date ?? $repayment->payment?->payment_date ?? $repayment->payment?->verified_at,
        ]))->values();
        if ($loans->isEmpty()) {
            return $entries;
        }

        // Older verified receipts can exist before they are linked to a repayment.
        $loanIds = $loans->modelKeys();
        $byId = $loans->keyBy('id');
        $payments = Payment::query()->where('payment_type', 'Loan')->where('status', 'Verified')
            ->whereDoesntHave('loanRepayment')->with('bill.loanInstallment')
            ->where(fn ($query) => $query->whereIn('loan_id', $loanIds)
                ->orWhereHas('bill', fn ($bill) => $bill->where('bill_type', 'Loan')
                    ->where(fn ($linked) => $linked->whereIn('loan_id', $loanIds)
                        ->orWhereHas('loanInstallment', fn ($installment) => $installment->whereIn('loan_id', $loanIds)))))
            ->get();

        foreach ($payments as $payment) {
            $bill = $payment->bill;
            $billLoanId = $bill?->loan_id ?? $bill?->loanInstallment?->loan_id;
            $loan = $byId->get($payment->loan_id ?? $billLoanId);
            if (! $loan || (int) $loan->consumer_id !== (int) $payment->consumer_id
                || ($payment->bill_id && (! $bill || $bill->bill_type !== 'Loan'
                    || (int) $bill->consumer_id !== (int) $loan->consumer_id
                    || ($billLoanId && (int) $billLoanId !== (int) $loan->id)))) {
                continue;
            }
            $entries->push([
                'loan_id' => $loan->id,
                'amount' => (float) $payment->amount,
                'date' => $payment->payment_date ?? $payment->verified_at,
            ]);
        }

        return $entries;
    }

    private function applyUnallocatedCredits(Collection $loans, Collection $totals): void
    {
        foreach ($loans as $loan) {
            $available = round(($totals[$loan->id] ?? 0) - $loan->installments->sum('amount_paid'), 2);
            if ($available <= 0 || $loan->installments->isEmpty()) {
                continue;
            }
            // A report projects only the unallocated credit. Viewing it never
            // posts transactions or rewrites the historical installment rows.
            foreach ($loan->installments->sortBy('installment_number') as $installment) {
                $credit = min($available, $this->installmentRemaining($installment));
                if ($credit > 0) {
                    $installment->amount_paid = round((float) $installment->amount_paid + $credit, 2);
                    $available = round($available - $credit, 2);
                }
                if ($available <= 0) {
                    break;
                }
            }
        }
    }

    /** @return list<array{location: string, amount: float, loan_count: int, installment_count: int, earliest_due_date: string, cases: list<array<string, mixed>>}> */
    private function overdueByLocation(Collection $loans, array $overdueDetails): array
    {
        $groups = [];

        foreach ($loans as $loan) {
            $detail = $overdueDetails[$loan->id] ?? null;
            if (! $detail || ! $detail['is_overdue']) {
                continue;
            }

            $purok = trim((string) $loan->consumer?->service_purok) ?: null;
            $block = trim((string) $loan->consumer?->address_block) ?: null;
            $key = ($purok ?? self::UNSPECIFIED).'|'.($block ?? self::UNSPECIFIED);
            $overdueInstallments = $loan->installments
                ->filter(fn (LoanInstallment $installment): bool => $this->installmentIsOverdue($installment));
            $dueDates = $overdueInstallments->pluck('due_date')->filter();

            if ($overdueInstallments->isEmpty() && $loan->next_due_date?->lt(today())) {
                $dueDates = collect([$loan->next_due_date]);
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'location' => $this->loanLocationLabel($purok, $block),
                    'amount' => 0.0,
                    'loan_ids' => [],
                    'installment_count' => 0,
                    'earliest_due' => null,
                    'cases' => [],
                ];
            }

            $groups[$key]['amount'] += (float) $detail['amount'];
            $groups[$key]['loan_ids'][$loan->id] = true;
            $groups[$key]['installment_count'] += max(1, (int) $detail['installment_count']);
            $earliest = $dueDates->sort()->first();
            if ($earliest && (! $groups[$key]['earliest_due'] || $earliest->lt($groups[$key]['earliest_due']))) {
                $groups[$key]['earliest_due'] = $earliest;
            }
            $groups[$key]['cases'][] = [
                'consumer' => $loan->consumer?->full_name ?: 'Consumer not recorded',
                'account_number' => $loan->consumer?->account_number,
                'amount' => round((float) $detail['amount'], 2),
                'installment_count' => max(1, (int) $detail['installment_count']),
                'earliest_due_date' => $earliest?->format('M j, Y') ?? 'Date unavailable',
            ];
        }

        $locations = collect($groups)->map(fn (array $group): array => [
            'location' => $group['location'],
            'amount' => round($group['amount'], 2),
            'loan_count' => count($group['loan_ids']),
            'installment_count' => $group['installment_count'],
            'earliest_due_date' => $group['earliest_due']?->format('M j, Y') ?? 'Date unavailable',
            'cases' => $group['cases'],
        ])->values()->all();

        usort($locations, fn (array $left, array $right): int => $right['amount'] <=> $left['amount']
            ?: $right['installment_count'] <=> $left['installment_count']
            ?: strcmp($left['location'], $right['location']));

        return $locations;
    }

    /** @return array{labels: list<string>, counts: list<int>, amounts: list<float>, has_data: bool, upcoming_due: ?array{date: string, count: int, amount: float}} */
    private function repaymentStatus(Collection $loans, array $outstandingByLoan): array
    {
        $labels = ['Paid', 'Partially Paid', 'Overdue', 'Upcoming'];
        $hasScheduleData = $loans->contains(fn (Loan $loan): bool => $loan->installments->isNotEmpty()
            || ($loan->next_due_date !== null && (float) $loan->monthly_installment > 0));
        $counts = array_fill_keys($labels, 0);
        $amounts = array_fill_keys($labels, 0.0);
        $upcomingSchedules = [];

        foreach ($loans as $loan) {
            if ($loan->installments->isNotEmpty()) {
                foreach ($loan->installments as $installment) {
                    $required = (float) $installment->required_amount;
                    if ($required <= 0) {
                        continue;
                    }

                    $paid = min($required, max(0, (float) $installment->amount_paid));
                    $remaining = round(max(0, $required - $paid), 2);
                    $status = match (true) {
                        $remaining <= 0 => 'Paid',
                        $installment->due_date !== null && $installment->due_date->lt(today()) => 'Overdue',
                        $paid > 0 => 'Partially Paid',
                        $installment->due_date !== null && $installment->due_date->gte(today()) => 'Upcoming',
                        $installment->status === 'Upcoming' => 'Upcoming',
                        default => null,
                    };

                    if ($status === null) {
                        continue;
                    }

                    $counts[$status]++;
                    $amounts[$status] += $status === 'Paid' ? $required : $remaining;

                    if ($status === 'Upcoming' && $installment->due_date !== null) {
                        $upcomingSchedules[] = [
                            'date' => $installment->due_date,
                            'amount' => $remaining,
                        ];
                    }
                }

                continue;
            }

            $loanOutstanding = (float) ($outstandingByLoan[$loan->id] ?? 0);
            if ($loanOutstanding <= 0 || $loan->next_due_date === null) {
                continue;
            }

            if ((float) $loan->monthly_installment <= 0) {
                continue;
            }

            $scheduledAmount = min($loanOutstanding, (float) $loan->monthly_installment);
            $status = $loan->next_due_date->lt(today()) ? 'Overdue' : 'Upcoming';
            $counts[$status]++;
            $amounts[$status] += $scheduledAmount;
            if ($status === 'Upcoming') {
                $upcomingSchedules[] = ['date' => $loan->next_due_date, 'amount' => $scheduledAmount];
            }
        }

        $upcomingDue = null;
        if ($upcomingSchedules !== []) {
            usort($upcomingSchedules, fn (array $left, array $right): int => $left['date']->getTimestamp() <=> $right['date']->getTimestamp());
            $nearestDate = $upcomingSchedules[0]['date'];
            $sameDate = collect($upcomingSchedules)->filter(fn (array $item): bool => $item['date']->isSameDay($nearestDate));
            $upcomingDue = [
                'date' => $nearestDate->format('M j, Y'),
                'count' => $sameDate->count(),
                'amount' => round((float) $sameDate->sum('amount'), 2),
            ];
        }

        $amounts = array_map(fn (float $amount): float => round($amount, 2), array_values($amounts));
        $counts = array_values($counts);

        return [
            'labels' => $labels,
            'counts' => $counts,
            'amounts' => $amounts,
            'has_data' => array_sum($counts) > 0,
            'has_schedule_data' => $hasScheduleData,
            'upcoming_due' => $upcomingDue,
        ];
    }

    /** @return list<array{severity: string, title: string, message: string, action: string}> */
    private function collectionRecommendations(array $overdueLocations, ?array $upcomingDue): array
    {
        $recommendations = collect($overdueLocations)->map(fn (array $location): array => [
            'severity' => 'critical',
            'title' => 'Follow up overdue installments - '.$location['location'],
            'cases' => $location['cases'],
            'message' => sprintf(
                '%d missed installment(s) across %d released loan(s), totaling PHP %s. Earliest due date: %s.',
                $location['installment_count'],
                $location['loan_count'],
                number_format($location['amount'], 2),
                $location['earliest_due_date'],
            ),
            'action' => 'Contact the affected borrowers and record collection against the outstanding scheduled amounts.',
        ])->all();

        if ($upcomingDue !== null) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'Upcoming scheduled collection',
                'message' => sprintf(
                    '%d installment(s) totaling PHP %s are next due on %s.',
                    $upcomingDue['count'],
                    number_format($upcomingDue['amount'], 2),
                    $upcomingDue['date'],
                ),
                'action' => 'Review the upcoming schedules and send the standard payment reminder if needed.',
            ];
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'severity' => 'success',
                'title' => 'No overdue loan installments',
                'message' => 'No released loan has an unpaid installment with a due date before today in the selected records.',
                'action' => 'Continue reviewing the recorded repayment schedules.',
            ];
        }

        return $recommendations;
    }

    private function loanLocationLabel(?string $purok, ?string $block): string
    {
        if ($purok === null && $block === null) {
            return 'Location not specified';
        }

        $blockLabel = $block === null
            ? 'Block not recorded'
            : (preg_match('/^block\b/i', $block) === 1 ? $block : 'Block '.$block);

        return ($purok ?? 'Purok not recorded').' / '.$blockLabel;
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @param  array<int, array{is_overdue: bool, amount: float, installment_count: int, days: int}>  $overdueDetails
     * @return list<array<string, mixed>>
     */
    private function applications(Collection $loans, array $overdueDetails, Collection $outstanding, Collection $repaid): array
    {
        return $loans
            ->sortByDesc(fn (Loan $loan): int => $loan->applied_at?->timestamp ?? 0)
            ->take(15)
            ->values()
            ->map(function (Loan $loan) use ($overdueDetails, $outstanding, $repaid): array {
                $detail = $overdueDetails[$loan->id] ?? [
                    'is_overdue' => false,
                    'amount' => 0.0,
                    'installment_count' => 0,
                    'days' => 0,
                ];
                $isPortfolio = $outstanding->has($loan->id);

                return [
                    'id' => $loan->id,
                    'consumer' => $loan->consumer?->full_name ?? 'Unknown consumer',
                    'account_number' => $loan->consumer?->account_number,
                    'amount' => round((float) $loan->amount, 2),
                    'term_months' => (int) $loan->term_months,
                    'status' => $loan->status,
                    'risk_level' => $loan->risk_level,
                    'applied_at' => $loan->applied_at?->format('M d, Y'),
                    'approved_at' => $loan->approved_at?->format('M d, Y'),
                    'is_portfolio' => $isPortfolio,
                    'outstanding' => $isPortfolio ? $outstanding[$loan->id] : null,
                    'total_repaid' => $isPortfolio ? round((float) ($repaid[$loan->id] ?? 0), 2) : null,
                    'is_overdue' => $isPortfolio && $detail['is_overdue'],
                    'overdue_amount' => $isPortfolio ? round((float) $detail['amount'], 2) : null,
                    'overdue_days' => $isPortfolio ? (int) $detail['days'] : 0,
                ];
            })
            ->all();
    }

    /**
     * @return array{is_overdue: bool, amount: float, installment_count: int, days: int}
     */
    private function overdueDetail(Loan $loan, ?float $outstandingBalance = null): array
    {
        $outstandingBalance ??= (float) $loan->balance_remaining;

        if ($outstandingBalance <= 0) {
            return [
                'is_overdue' => false,
                'amount' => 0.0,
                'installment_count' => 0,
                'days' => 0,
            ];
        }

        if ($loan->installments->isNotEmpty()) {
            $overdueInstallments = $loan->installments
                ->filter(fn (LoanInstallment $installment): bool => $this->installmentIsOverdue($installment))
                ->values();

            if ($overdueInstallments->isNotEmpty()) {
                $days = $overdueInstallments
                    ->map(fn (LoanInstallment $installment): int => (int) max(0, $installment->due_date->copy()->startOfDay()->diffInDays(today(), false)))
                    ->max() ?? 0;

                return [
                    'is_overdue' => true,
                    'amount' => round((float) $overdueInstallments->sum(fn (LoanInstallment $installment): float => $this->installmentRemaining($installment)), 2),
                    'installment_count' => $overdueInstallments->count(),
                    'days' => $days,
                ];
            }

            return [
                'is_overdue' => false,
                'amount' => 0.0,
                'installment_count' => 0,
                'days' => 0,
            ];
        }

        if ($loan->next_due_date !== null
            && $loan->next_due_date->lt(today())
            && (float) $loan->monthly_installment > 0) {
            return [
                'is_overdue' => true,
                'amount' => round(min($outstandingBalance, (float) $loan->monthly_installment), 2),
                'installment_count' => 1,
                'days' => (int) max(0, $loan->next_due_date->copy()->startOfDay()->diffInDays(today(), false)),
            ];
        }

        return [
            'is_overdue' => false,
            'amount' => 0.0,
            'installment_count' => 0,
            'days' => 0,
        ];
    }

    private function installmentIsOverdue(LoanInstallment $installment): bool
    {
        return $this->installmentRemaining($installment) > 0
            && $installment->due_date !== null
            && $installment->due_date->lt(today());
    }

    private function installmentRemaining(LoanInstallment $installment): float
    {
        return round(max(0, (float) $installment->required_amount - (float) $installment->amount_paid), 2);
    }

    private function agingBucket(int $days): string
    {
        return match (true) {
            $days <= 30 => '1-30 days',
            $days <= 60 => '31-60 days',
            $days <= 90 => '61-90 days',
            default => '90+ days',
        };
    }

    private function periodLabel(array $filters): string
    {
        if ($filters['year'] === 'all') {
            return $filters['month'] === null
                ? 'All Years'
                : Carbon::create(2000, $filters['month'], 1)->format('F').' (All Years)';
        }

        if ($filters['month'] !== null) {
            return Carbon::create($filters['year'], $filters['month'], 1)->format('F Y');
        }

        return $filters['year'].' (All Months)';
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array{severity: string, title: string, message: string, action: string}>
     */
    private function recommendations(array $summary): array
    {
        $recommendations = [];
        $total = (int) $summary['total'];

        if ($summary['pending'] > 0) {
            $severity = $summary['pending'] >= 5 || ($total > 0 && ($summary['pending'] / $total) >= 0.5)
                ? 'critical'
                : 'warning';

            $recommendations[] = [
                'severity' => $severity,
                'title' => 'Clear the pending loan review queue',
                'message' => sprintf(
                    '%d application(s) are still pending review, representing %s in requested amount. The oldest has waited %d day(s).',
                    $summary['pending'],
                    '₱'.number_format((float) $summary['pending_requested_amount'], 2),
                    $summary['oldest_pending_days'],
                ),
                'action' => 'Assign reviewers and record a decision or review note for each pending application.',
            ];
        }

        if ($summary['overdue_count'] > 0) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'Follow up overdue loan accounts',
                'message' => sprintf(
                    '%d loan(s) are overdue, with %s outstanding and %d overdue installment(s).',
                    $summary['overdue_count'],
                    '₱'.number_format((float) $summary['overdue_amount'], 2),
                    $summary['overdue_installments'],
                ),
                'action' => 'Prioritize collection follow-up and confirm the next due date for each overdue account.',
            ];
        }

        if ($summary['portfolio_count'] > 0 && ! $summary['has_installments'] && ! $summary['has_repayments']) {
            $recommendations[] = [
                'severity' => 'warning',
                'title' => 'Repayment data is missing for active loans',
                'message' => sprintf(
                    '%d loan(s) carry %s in outstanding balance, but no installments or repayments are recorded.',
                    $summary['portfolio_count'],
                    '₱'.number_format((float) $summary['outstanding_balance'], 2),
                ),
                'action' => 'Generate the installment schedule and record repayments so collection performance can be monitored.',
            ];
        }

        if ($summary['collection_rate'] !== null && $summary['collection_rate'] < 80) {
            $recommendations[] = [
                'severity' => 'warning',
                'title' => 'Collection performance is below target',
                'message' => sprintf(
                    'The collection rate is %s%% based on recorded billed installments.',
                    number_format((float) $summary['collection_rate'], 1),
                ),
                'action' => 'Review unpaid installments and strengthen collection follow-up.',
            ];
        }

        if ($summary['high_risk'] > 0) {
            $recommendations[] = [
                'severity' => 'warning',
                'title' => 'High-risk applications need closer review',
                'message' => sprintf(
                    '%d application(s) are marked High risk.',
                    $summary['high_risk'],
                ),
                'action' => 'Validate income, existing obligations, and repayment capacity before approval.',
            ];
        }

        if ($summary['zero_amount_approved'] > 0) {
            $recommendations[] = [
                'severity' => 'warning',
                'title' => 'Approved loans have no amount',
                'message' => sprintf(
                    '%d approved application(s) have an amount of zero or less.',
                    $summary['zero_amount_approved'],
                ),
                'action' => 'Correct the loan amount and installment schedule before releasing funds or billing.',
            ];
        }

        if ($summary['invalid_approval_timestamps'] > 0) {
            $recommendations[] = [
                'severity' => 'warning',
                'title' => 'Approval timestamps are inconsistent',
                'message' => sprintf(
                    '%d approved loan(s) have an approval time earlier than the application time. They are excluded from the approval lead-time average.',
                    $summary['invalid_approval_timestamps'],
                ),
                'action' => 'Review timestamp handling before using approval speed as a performance measure.',
            ];
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'severity' => 'success',
                'title' => 'No urgent loan portfolio issue detected',
                'message' => $total > 0
                    ? 'The filtered loan set does not currently trigger a queue, overdue, repayment, risk, or data-quality rule.'
                    : 'No loan applications match the selected filters.',
                'action' => 'Continue monitoring the review queue, repayment schedule, and portfolio aging.',
            ];
        }

        return $recommendations;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<string>
     */
    private function dataNotes(array $summary): array
    {
        $notes = [];

        if ($summary['total'] > 0 && $summary['total'] < 5) {
            $notes[] = 'Small sample: fewer than five loan applications match the selected filters. Interpret rates and rankings with caution.';
        }

        if ($summary['decided'] > 0 && $summary['decided'] < 5) {
            $notes[] = 'Approval and rejection rates are based on fewer than five decided applications.';
        }

        if ($summary['decided'] > 0 && $summary['rejected'] === 0) {
            $notes[] = 'No rejected applications are recorded in the filtered set, so the rejection rate is 0%.';
        }

        if ($summary['approved'] > 0 && ! $summary['has_installments'] && ! $summary['has_repayments']) {
            $notes[] = 'Installment and repayment records are unavailable for the filtered loans; collection, on-time, and aging metrics may be incomplete.';
        }

        if ($summary['invalid_approval_timestamps'] > 0) {
            $notes[] = 'Approval lead time uses only valid timestamps; inconsistent timestamps are excluded.';
        }

        return $notes;
    }

    /**
     * @param  list<string>  $preferred
     * @param  Collection<int, mixed>  $actual
     * @return list<string>
     */
    private function labelsWithExtras(array $preferred, Collection $actual): array
    {
        $extras = $actual
            ->filter(fn (mixed $value): bool => filled($value) && ! in_array($value, $preferred, true))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_values(array_unique([...$preferred, ...$extras]));
    }
}
