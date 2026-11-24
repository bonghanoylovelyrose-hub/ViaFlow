<?php

namespace App\Services\Reports;

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
use App\Support\FilterYears;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private readonly ConsumerBalanceService $balances,
        private readonly OutstandingBalanceService $outstandingBalances,
    ) {}

    /**
     * @return array<string, ReportDefinition>
     */
    public function definitions(): array
    {
        return ReportCatalog::all();
    }

    /**
     * @return array<string, list<ReportDefinition>>
     */
    public function groupedDefinitions(): array
    {
        return ReportCatalog::grouped();
    }

    public function resolveKey(?string $key): string
    {
        $aliases = [
            'collection' => 'collections',
            'balance' => 'outstanding-balances',
            'payments' => 'payment-monitoring',
            'loans' => 'loan-portfolio',
            'disconnected' => 'disconnected-accounts',
            'reconnected' => 'reconnected-accounts',
            'consumers' => 'consumer-accounts',
        ];

        $key = $aliases[$key] ?? $key;

        return array_key_exists((string) $key, $this->definitions())
            ? (string) $key
            : 'collections';
    }

    public function definition(string $key): ReportDefinition
    {
        return $this->definitions()[$this->resolveKey($key)];
    }

    public function paginate(string $key, ReportFilters $filters, int $page = 1, int $perPage = 25): ReportResult
    {
        $key = $this->resolveKey($key);
        $rows = $this->rowsFor($key, $filters);
        $total = count($rows);
        $perPage = max(1, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return new ReportResult(
            definition: $this->definition($key),
            filters: $filters,
            rows: array_slice($rows, ($page - 1) * $perPage, $perPage),
            summary: $this->summaryFor($key, $rows),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    public function export(string $key, ReportFilters $filters): ReportResult
    {
        $key = $this->resolveKey($key);
        $rows = $this->rowsFor($key, $filters);

        return new ReportResult(
            definition: $this->definition($key),
            filters: $filters,
            rows: $rows,
            summary: $this->summaryFor($key, $rows),
            total: count($rows),
            page: 1,
            perPage: max(1, count($rows)),
        );
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $nextYearStart = now()->startOfYear()->addYear()->toDateString();
        $recordedFutureYears = collect([
            [Bill::query(), 'billing_period'],
            [Payment::query(), 'payment_date'],
            [MeterReading::query(), 'reading_date'],
            [Incident::query(), 'reported_at'],
            [Loan::query(), 'applied_at'],
            [LoanRepayment::query(), 'payment_date'],
            [ServiceRequest::query(), 'requested_at'],
            [Consumer::query(), 'created_at'],
            [Consumer::query(), 'disconnected_at'],
            [Consumer::query(), 'reconnected_at'],
        ])->map(function (array $source) use ($nextYearStart): ?int {
            [$query, $column] = $source;
            $latest = $query->where($column, '>=', $nextYearStart)->toBase()->max($column);

            return $latest === null ? null : (int) substr($latest, 0, 4);
        })->filter();

        return FilterYears::available($recordedFutureYears);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $key, ReportFilters $filters): array
    {
        return match ($key) {
            'billing' => $this->billingRows($filters),
            'payment-monitoring' => $this->paymentMonitoringRows($filters),
            'outstanding-balances' => $this->outstandingBalanceRows($filters),
            'incidents' => $this->incidentRows($filters),
            'meter-readings' => $this->meterReadingRows($filters),
            'loan-portfolio' => $this->loanPortfolioRows($filters),
            'loan-repayments' => $this->loanRepaymentRows($filters),
            'disconnected-accounts' => $this->disconnectedAccountRows($filters),
            'reconnected-accounts' => $this->reconnectedAccountRows($filters),
            'consumer-accounts' => $this->consumerAccountRows($filters),
            'service-requests' => $this->serviceRequestRows($filters),
            default => $this->collectionRows($filters),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectionRows(ReportFilters $filters): array
    {
        $query = Payment::query()
            ->with([
                'bill:id,prn',
                'consumer:id,full_name,account_number',
                'verifier:id,name',
            ])
            ->where('status', 'Verified')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'payment_date');

        if ($filters->channel !== null) {
            $query->where('payment_channel', $filters->channel);
        }

        return $query->get()->map(fn (Payment $payment): array => [
            'payment_date' => $this->carbon($payment->payment_date),
            'receipt_number' => $payment->receipt_number,
            'reference_number' => $payment->reference_number,
            'account_number' => $payment->consumer?->account_number,
            'consumer' => $payment->consumer?->full_name ?? 'Deleted consumer',
            'prn' => $payment->bill?->prn,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_channel' => $payment->payment_channel,
            'verified_at' => $this->carbon($payment->verified_at),
            'verified_by' => $payment->verifier?->name,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function billingRows(ReportFilters $filters): array
    {
        $query = Bill::query()
            ->with([
                'consumer:id,full_name,account_number,meter_number,service_purok',
                'meterReading:id,previous_reading,current_reading,consumption',
                'carriedForwardTo:id,prn',
            ])
            ->withSum(
                ['payments as verified_payments_sum_amount' => fn ($payments) => $payments->where('status', 'Verified')],
                'amount',
            )
            ->orderByDesc('billing_period')
            ->orderBy('prn');

        $filters->applyPeriod($query, 'billing_period');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(fn (Bill $bill): array => [
            'billing_period' => $this->carbon($bill->billing_period),
            'prn' => $bill->prn,
            'account_number' => $bill->consumer?->account_number,
            'consumer' => $bill->consumer?->full_name ?? 'Deleted consumer',
            'purok' => $bill->consumer?->service_purok,
            'meter_number' => $bill->consumer?->meter_number,
            'previous_reading' => $this->floatOrNull($bill->meterReading?->previous_reading),
            'current_reading' => $this->floatOrNull($bill->meterReading?->current_reading),
            'consumption' => $this->floatOrNull($bill->units_consumed ?? $bill->meterReading?->consumption),
            'unit_rate' => $this->floatOrNull($bill->unit_rate),
            'base_charge' => (float) ($bill->base_charge ?? 0),
            'water_charge' => (float) ($bill->water_charge ?? 0),
            'arrears_amount' => (float) ($bill->arrears_amount ?? 0),
            'penalty_amount' => (float) ($bill->penalty_amount ?? 0),
            'loan_installment_amount' => (float) ($bill->loan_installment_amount ?? 0),
            'amount' => (float) $bill->amount,
            'verified_paid' => $bill->verified_paid_amount,
            'balance_due' => $bill->balance_due,
            'status' => $bill->computedStatus(),
            'due_date' => $this->carbon($bill->due_date),
            'carried_forward_to' => $bill->carriedForwardTo?->prn,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentMonitoringRows(ReportFilters $filters): array
    {
        $query = Payment::query()
            ->with([
                'bill:id,prn',
                'consumer:id,full_name,account_number',
                'verifier:id,name',
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'payment_date');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->channel !== null) {
            $query->where('payment_channel', $filters->channel);
        }

        return $query->get()->map(fn (Payment $payment): array => [
            'payment_date' => $this->carbon($payment->payment_date),
            'receipt_number' => $payment->receipt_number,
            'reference_number' => $payment->reference_number,
            'account_number' => $payment->consumer?->account_number,
            'consumer' => $payment->consumer?->full_name ?? 'Deleted consumer',
            'prn' => $payment->bill?->prn,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_channel' => $payment->payment_channel,
            'gateway_reference' => $payment->gateway_reference,
            'status' => $payment->status,
            'verified_at' => $this->carbon($payment->verified_at),
            'verified_by' => $payment->verifier?->name,
            'notes' => $payment->notes,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function outstandingBalanceRows(ReportFilters $filters): array
    {
        $cutoff = $filters->periodCutoff() ?? now()->endOfDay();
        $openBills = $this->outstandingBalances->openBillsAsOf($cutoff);

        if ($filters->status !== null) {
            $openBills = $openBills
                ->filter(fn (Bill $bill): bool => $bill->consumer?->status === $filters->status)
                ->values();
        }

        $rows = $openBills
            ->groupBy('consumer_id')
            ->map(function (Collection $bills): ?array {
                $consumer = $bills->first()?->consumer;

                if ($consumer === null) {
                    return null;
                }

                $oldestDue = $bills
                    ->filter(fn (Bill $bill): bool => $bill->due_date !== null)
                    ->sortBy(fn (Bill $bill) => $bill->due_date->timestamp)
                    ->first()?->due_date;

                $latestPeriod = $bills
                    ->filter(fn (Bill $bill): bool => $bill->billing_period !== null)
                    ->sortByDesc(fn (Bill $bill) => $bill->billing_period->timestamp)
                    ->first()?->billing_period;

                return [
                    'account_number' => $consumer->account_number,
                    'consumer' => $consumer->full_name,
                    'purok' => $consumer->service_purok,
                    'contact_number' => $consumer->contact_number,
                    'outstanding_balance' => round((float) $bills->sum(
                        fn (Bill $bill): float => (float) ($bill->balance_as_of ?? 0),
                    ), 2),
                    'open_bills' => $bills->count(),
                    'oldest_due_date' => $this->carbon($oldestDue),
                    'latest_billing_period' => $this->carbon($latestPeriod),
                    'notice' => $bills->contains(
                        fn (Bill $bill): bool => $bill->notice_level === ConsumerBalanceService::DISCONNECTION_NOTICE,
                    ) ? 'Issued' : 'None',
                    'account_status' => $consumer->status,
                ];
            })
            ->filter()
            ->sortByDesc('outstanding_balance')
            ->values();

        return $rows->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incidentRows(ReportFilters $filters): array
    {
        $query = Incident::query()
            ->with('consumer:id,full_name,account_number')
            ->orderByDesc('reported_at')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'reported_at');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->category !== null) {
            $query->whereRaw('LOWER(TRIM(category)) = ?', [strtolower(trim($filters->category))]);
        }

        return $query->get()->map(function (Incident $incident): array {
            $resolvedAt = $incident->resolved_at ?? $incident->confirmed_at;
            $resolutionDays = $incident->reported_at && $resolvedAt
                ? max(0, (int) round($incident->reported_at->diffInHours($resolvedAt) / 24))
                : null;

            return [
                'reported_at' => $this->carbon($incident->reported_at),
                'reference' => 'INC-'.str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT),
                'account_number' => $incident->consumer?->account_number,
                'consumer' => $incident->consumer?->full_name ?? 'General report',
                'reported_by' => $incident->reported_by,
                'category' => strtolower(trim($incident->category)) === 'others' ? 'Others' : $incident->category,
                'custom_incident_type' => $incident->custom_incident_type,
                'location' => $incident->location,
                'description' => $incident->description,
                'status' => $incident->status,
                'resolved_at' => $this->carbon($resolvedAt),
                'resolution_days' => $resolutionDays,
                'resolution_notes' => $incident->resolution_notes,
                'evidence' => $incident->photo_path
                    ? ucfirst((string) ($incident->evidence_media_type ?? 'attachment'))
                    : 'None',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function meterReadingRows(ReportFilters $filters): array
    {
        $query = MeterReading::query()
            ->with([
                'consumer:id,full_name,account_number,meter_number',
                'reader:id,name',
                'bill:id,prn',
            ])
            ->orderByDesc('reading_date')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'reading_date');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(fn (MeterReading $reading): array => [
            'reading_date' => $this->carbon($reading->reading_date),
            'account_number' => $reading->consumer?->account_number,
            'consumer' => $reading->consumer?->full_name ?? 'Deleted consumer',
            'meter_number' => $reading->meter_number ?? $reading->consumer?->meter_number,
            'reader' => $reading->reader?->name ?? 'Unassigned',
            'previous_reading' => (float) $reading->previous_reading,
            'current_reading' => (float) $reading->current_reading,
            'consumption' => (float) $reading->consumption,
            'status' => $reading->status,
            'remarks' => $reading->remarks,
            'bill_prn' => $reading->bill?->prn,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loanPortfolioRows(ReportFilters $filters): array
    {
        $query = Loan::query()
            ->with([
                'consumer:id,full_name,account_number',
                'installments',
                'repayments',
            ])
            ->orderByDesc('applied_at')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'applied_at');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(fn (Loan $loan): array => [
            'applied_at' => $this->carbon($loan->applied_at),
            'loan_id' => 'LN-'.str_pad((string) $loan->id, 5, '0', STR_PAD_LEFT),
            'account_number' => $loan->consumer?->account_number,
            'consumer' => $loan->consumer?->full_name ?? 'Deleted consumer',
            'amount' => (float) $loan->amount,
            'term_months' => (int) $loan->term_months,
            'monthly_installment' => (float) ($loan->monthly_installment ?? 0),
            'installation_cost_total' => (float) ($loan->installation_cost_total ?? 0),
            'total_repaid' => $loan->total_repaid,
            'balance_remaining' => $loan->balance_remaining,
            'status' => $loan->status,
            'review_status' => $loan->review_status,
            'installation_status' => $loan->installation_status,
            'next_due_date' => $this->carbon($loan->next_due_date),
            'approved_at' => $this->carbon($loan->approved_at),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loanRepaymentRows(ReportFilters $filters): array
    {
        $query = LoanRepayment::query()
            ->with([
                'loan.consumer:id,full_name,account_number',
                'loan.installments',
                'loan.repayments',
                'bill:id,prn',
                'payment:id,reference_number,receipt_number',
                'recorder:id,name',
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'payment_date');

        return $query->get()->map(fn (LoanRepayment $repayment): array => [
            'payment_date' => $this->carbon($repayment->payment_date),
            'loan_id' => $repayment->loan
                ? 'LN-'.str_pad((string) $repayment->loan->id, 5, '0', STR_PAD_LEFT)
                : null,
            'account_number' => $repayment->loan?->consumer?->account_number,
            'consumer' => $repayment->loan?->consumer?->full_name ?? 'Deleted consumer',
            'amount' => (float) $repayment->amount,
            'bill_prn' => $repayment->bill?->prn,
            'payment_reference' => $repayment->payment?->reference_number
                ?? $repayment->bill?->prn
                ?? 'Manual entry',
            'recorder' => $repayment->recorder?->name,
            'notes' => $repayment->notes,
            'loan_balance' => $repayment->loan?->balance_remaining,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function disconnectedAccountRows(ReportFilters $filters): array
    {
        $query = Consumer::query()
            ->with([
                'bills' => function ($query): void {
                    $query
                        ->whereNull('carried_forward_to_bill_id')
                        ->withSum(
                            ['payments as verified_payments_sum_amount' => fn ($payments) => $payments->where('status', 'Verified')],
                            'amount',
                        )
                        ->orderByDesc('billing_period')
                        ->orderByDesc('id');
                },
                'serviceRequests' => fn ($requests) => $requests
                    ->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                    ->with('reviewer:id,name')
                    ->orderByDesc('requested_at')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('disconnected_at')
            ->orderBy('full_name');

        $currentPeriod = ($filters->year === null && $filters->month === null)
            || ($filters->year === now()->year
                && ($filters->month === null || $filters->month === now()->month));

        if ($filters->year !== null || $filters->month !== null) {
            $query->where(function ($disconnected) use ($filters, $currentPeriod): void {
                $disconnected->where(function ($dated) use ($filters): void {
                    $dated->whereNotNull('disconnected_at');
                    $filters->applyPeriod($dated, 'disconnected_at');
                });

                if ($currentPeriod) {
                    $disconnected->orWhere(function ($legacy): void {
                        $legacy
                            ->whereNull('disconnected_at')
                            ->whereIn('status', ['Disconnected', 'For Reconnection']);
                    });
                }
            });
        } else {
            $query->where(function ($disconnected): void {
                $disconnected
                    ->whereIn('status', ['Disconnected', 'For Reconnection'])
                    ->orWhereNotNull('disconnected_at');
            });
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(function (Consumer $consumer): array {
            $openBills = $consumer->bills
                ->filter(fn (Bill $bill): bool => $bill->balance_due > 0)
                ->values();
            $lastRequest = $consumer->serviceRequests->first();

            return [
                'disconnected_at' => $this->carbon($consumer->disconnected_at),
                'account_number' => $consumer->account_number,
                'consumer' => $consumer->full_name,
                'purok' => $consumer->service_purok,
                'contact_number' => $consumer->contact_number,
                'status' => $consumer->status,
                'outstanding_balance' => round((float) $openBills->sum(fn (Bill $bill): float => $bill->balance_due), 2),
                'open_bills' => $openBills->count(),
                'months_unpaid' => $this->balances->consecutiveUnpaidMonthsFor($consumer->id),
                'last_reconnection_request' => $lastRequest
                    ? sprintf(
                        '%s (%s)',
                        $lastRequest->status,
                        $lastRequest->requested_at?->format('M d, Y') ?? 'date not recorded',
                    )
                    : 'None',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reconnectedAccountRows(ReportFilters $filters): array
    {
        $query = Consumer::query()
            ->whereNotNull('reconnected_at')
            ->with([
                'serviceRequests' => fn ($requests) => $requests
                    ->where('request_type', ServiceRequest::TYPE_RECONNECTION)
                    ->where('status', ServiceRequest::STATUS_COMPLETED)
                    ->with('reviewer:id,name')
                    ->orderByDesc('reviewed_at')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('reconnected_at');

        $filters->applyPeriod($query, 'reconnected_at');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(function (Consumer $consumer): array {
            $completedRequest = $consumer->serviceRequests->first();
            $downtimeDays = $consumer->disconnected_at && $consumer->reconnected_at
                ? max(0, (int) $consumer->disconnected_at->diffInDays($consumer->reconnected_at))
                : null;

            return [
                'reconnected_at' => $this->carbon($consumer->reconnected_at),
                'account_number' => $consumer->account_number,
                'consumer' => $consumer->full_name,
                'purok' => $consumer->service_purok,
                'disconnected_at' => $this->carbon($consumer->disconnected_at),
                'requested_at' => $this->carbon($completedRequest?->requested_at),
                'reviewed_at' => $this->carbon($completedRequest?->reviewed_at),
                'downtime_days' => $downtimeDays,
                'balance_snapshot' => $completedRequest ? (float) $completedRequest->current_balance_snapshot : null,
                'status' => $consumer->status,
                'reviewed_by' => $completedRequest?->reviewer?->name,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consumerAccountRows(ReportFilters $filters): array
    {
        $query = Consumer::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'created_at');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return $query->get()->map(fn (Consumer $consumer): array => [
            'created_at' => $this->carbon($consumer->created_at),
            'application_reference' => $consumer->application_reference,
            'account_number' => $consumer->account_number,
            'meter_number' => $consumer->meter_number,
            'consumer' => $consumer->full_name,
            'email' => $consumer->email,
            'contact_number' => $consumer->contact_number,
            'purok' => $consumer->service_purok,
            'status' => $consumer->status,
            'membership_type' => $consumer->membership_type,
            'membership_verification_status' => $consumer->membership_verification_status,
            'property_type' => $consumer->property_type,
            'ownership_status' => $consumer->ownership_status,
            'installation_date' => $this->carbon($consumer->installation_date),
            'disconnected_at' => $this->carbon($consumer->disconnected_at),
            'reconnected_at' => $this->carbon($consumer->reconnected_at),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceRequestRows(ReportFilters $filters): array
    {
        $query = ServiceRequest::query()
            ->with([
                'consumer:id,full_name,account_number',
                'reviewer:id,name',
            ])
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        $filters->applyPeriod($query, 'requested_at');

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->type !== null) {
            $query->where('request_type', $filters->type);
        }

        return $query->get()->map(fn (ServiceRequest $request): array => [
            'requested_at' => $this->carbon($request->requested_at),
            'reference' => 'SR-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT),
            'request_type' => $request->request_type,
            'account_number' => $request->consumer?->account_number,
            'consumer' => $request->consumer?->full_name ?? 'Deleted consumer',
            'status' => $request->status,
            'current_balance_snapshot' => (float) $request->current_balance_snapshot,
            'reviewed_at' => $this->carbon($request->reviewed_at),
            'reviewed_by' => $request->reviewer?->name,
            'notes' => $request->information ?: $request->notes,
            'admin_notes' => $request->admin_notes,
        ])->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function summaryFor(string $key, array $rows): array
    {
        $collection = collect($rows);

        return match ($key) {
            'billing' => $this->billingSummary($collection),
            'payment-monitoring' => $this->paymentSummary($collection),
            'outstanding-balances' => $this->outstandingSummary($collection),
            'incidents' => $this->incidentSummary($collection),
            'meter-readings' => $this->meterReadingSummary($collection),
            'loan-portfolio' => $this->loanPortfolioSummary($collection),
            'loan-repayments' => $this->loanRepaymentSummary($collection),
            'disconnected-accounts' => $this->disconnectedSummary($collection),
            'reconnected-accounts' => $this->reconnectedSummary($collection),
            'consumer-accounts' => $this->consumerSummary($collection),
            'service-requests' => $this->serviceRequestSummary($collection),
            default => $this->collectionSummary($collection),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function collectionSummary(Collection $rows): array
    {
        $total = (float) $rows->sum('amount');

        return [
            ['label' => 'Verified Collections', 'value' => $total, 'type' => 'money'],
            ['label' => 'Transactions', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Average Payment', 'value' => $rows->isEmpty() ? 0 : round($total / $rows->count(), 2), 'type' => 'money'],
            ['label' => 'Paying Accounts', 'value' => $rows->pluck('account_number')->filter()->unique()->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function billingSummary(Collection $rows): array
    {
        return [
            ['label' => 'Bills Generated', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Total Billed', 'value' => round((float) $rows->sum('amount'), 2), 'type' => 'money'],
            ['label' => 'Verified Paid', 'value' => round((float) $rows->sum('verified_paid'), 2), 'type' => 'money'],
            ['label' => 'Outstanding Balance', 'value' => round((float) $rows->sum('balance_due'), 2), 'type' => 'money'],
            ['label' => 'Overdue Bills', 'value' => $rows->where('status', 'Overdue')->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function paymentSummary(Collection $rows): array
    {
        return [
            ['label' => 'Verified Amount', 'value' => round((float) $rows->where('status', 'Verified')->sum('amount'), 2), 'type' => 'money'],
            ['label' => 'Pending Amount', 'value' => round((float) $rows->where('status', 'Pending')->sum('amount'), 2), 'type' => 'money'],
            ['label' => 'Pending Verifications', 'value' => $rows->where('status', 'Pending')->count(), 'type' => 'number'],
            ['label' => 'Rejected Payments', 'value' => $rows->where('status', 'Rejected')->count(), 'type' => 'number'],
            ['label' => 'All Transactions', 'value' => $rows->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function outstandingSummary(Collection $rows): array
    {
        $total = (float) $rows->sum('outstanding_balance');

        return [
            ['label' => 'Total Receivable', 'value' => round($total, 2), 'type' => 'money'],
            ['label' => 'Accounts With Balance', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Open Bills', 'value' => (int) $rows->sum('open_bills'), 'type' => 'number'],
            ['label' => 'Average Balance', 'value' => $rows->isEmpty() ? 0 : round($total / $rows->count(), 2), 'type' => 'money'],
            ['label' => 'Notice Issued', 'value' => $rows->where('notice', 'Issued')->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function incidentSummary(Collection $rows): array
    {
        $resolvedRows = $rows->whereNotNull('resolution_days');
        $topCategory = $rows->countBy('category')->sortDesc()->keys()->first();

        return [
            ['label' => 'Total Incidents', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Open / Unresolved', 'value' => $rows->where('status', '!=', 'Resolved')->count(), 'type' => 'number'],
            ['label' => 'Resolved', 'value' => $rows->where('status', 'Resolved')->count(), 'type' => 'number'],
            ['label' => 'Average Resolution (Days)', 'value' => $resolvedRows->isEmpty() ? 0 : round((float) $resolvedRows->avg('resolution_days'), 1), 'type' => 'number'],
            ['label' => 'Most Reported Category', 'value' => $topCategory ?: 'None', 'type' => 'text'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function meterReadingSummary(Collection $rows): array
    {
        return [
            ['label' => 'Readings Recorded', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Total Consumption', 'value' => round((float) $rows->sum('consumption'), 2), 'type' => 'number'],
            ['label' => 'Average Consumption', 'value' => $rows->isEmpty() ? 0 : round((float) $rows->avg('consumption'), 2), 'type' => 'number'],
            ['label' => 'Encoded', 'value' => $rows->where('status', 'Encoded')->count(), 'type' => 'number'],
            ['label' => 'Needs Revisit', 'value' => $rows->where('status', 'Needs Revisit')->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function loanPortfolioSummary(Collection $rows): array
    {
        return [
            ['label' => 'Loan Applications', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Total Loan Amount', 'value' => round((float) $rows->sum('amount'), 2), 'type' => 'money'],
            ['label' => 'Total Repaid', 'value' => round((float) $rows->sum('total_repaid'), 2), 'type' => 'money'],
            ['label' => 'Outstanding Loan Balance', 'value' => round((float) $rows->sum('balance_remaining'), 2), 'type' => 'money'],
            ['label' => 'Approved / Active', 'value' => $rows->whereIn('status', ['Approved', 'Active', 'Ongoing'])->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function loanRepaymentSummary(Collection $rows): array
    {
        $total = (float) $rows->sum('amount');

        return [
            ['label' => 'Total Repaid', 'value' => round($total, 2), 'type' => 'money'],
            ['label' => 'Repayment Entries', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Loans Repaid', 'value' => $rows->pluck('loan_id')->filter()->unique()->count(), 'type' => 'number'],
            ['label' => 'Average Repayment', 'value' => $rows->isEmpty() ? 0 : round($total / $rows->count(), 2), 'type' => 'money'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function disconnectedSummary(Collection $rows): array
    {
        return [
            ['label' => 'Disconnected Accounts', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Outstanding Balance', 'value' => round((float) $rows->sum('outstanding_balance'), 2), 'type' => 'money'],
            ['label' => 'Open Bills', 'value' => (int) $rows->sum('open_bills'), 'type' => 'number'],
            ['label' => 'Pending Reconnection Requests', 'value' => $rows->filter(
                fn (array $row): bool => str_starts_with((string) $row['last_reconnection_request'], 'Pending'),
            )->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function reconnectedSummary(Collection $rows): array
    {
        $withDowntime = $rows->whereNotNull('downtime_days');

        return [
            ['label' => 'Reconnected Accounts', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Average Downtime (Days)', 'value' => $withDowntime->isEmpty() ? 0 : round((float) $withDowntime->avg('downtime_days'), 1), 'type' => 'number'],
            ['label' => 'Balance at Request', 'value' => round((float) $rows->sum('balance_snapshot'), 2), 'type' => 'money'],
            ['label' => 'Completed Requests', 'value' => $rows->whereNotNull('reviewed_at')->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function consumerSummary(Collection $rows): array
    {
        return [
            ['label' => 'Registered Accounts', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Active', 'value' => $rows->where('status', 'Active')->count(), 'type' => 'number'],
            ['label' => 'Pending Verification', 'value' => $rows->where('status', 'Pending Verification')->count(), 'type' => 'number'],
            ['label' => 'Disconnected', 'value' => $rows->where('status', 'Disconnected')->count(), 'type' => 'number'],
            ['label' => 'Members', 'value' => $rows->where('membership_type', 'Member')->count(), 'type' => 'number'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int|float|string|null, type: string}>
     */
    private function serviceRequestSummary(Collection $rows): array
    {
        return [
            ['label' => 'Total Requests', 'value' => $rows->count(), 'type' => 'number'],
            ['label' => 'Pending', 'value' => $rows->where('status', 'Pending')->count(), 'type' => 'number'],
            ['label' => 'Approved', 'value' => $rows->where('status', 'Approved')->count(), 'type' => 'number'],
            ['label' => 'Completed', 'value' => $rows->where('status', 'Completed')->count(), 'type' => 'number'],
            ['label' => 'Rejected', 'value' => $rows->where('status', 'Rejected')->count(), 'type' => 'number'],
        ];
    }

    private function carbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
