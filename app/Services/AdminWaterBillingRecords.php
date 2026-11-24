<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminWaterBillingRecords
{
    public const STATUSES = ['Paid', 'Unpaid', 'Paid with Balance', 'Overdue', ConsumerBalanceService::DISCONNECTION_NOTICE, 'Carried Forward', 'Scheduled'];

    private const OPEN_STATUSES = ['Unpaid', 'Overdue', 'Paid with Balance'];

    public function __construct(private readonly ConsumerBalanceService $balances, private readonly WaterBillingLedger $ledger) {}

    private function matchingConsumerIds(string $search): Builder
    {
        $pattern = '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        return DB::table('consumers')->select('id')
            ->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(account_number) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(meter_number) LIKE ? ESCAPE '!'", [$pattern]);
    }

    public function years(): Collection
    {
        return $this->recordQuery([])->distinct()->pluck('record_date')
            ->map(fn (string $date) => substr($date, 0, 4))->unique()->sortDesc()->values();
    }

    public function bills(array $filters, ?int $consumerId = null): EloquentBuilder
    {
        $bills = $this->ledger->bills();
        $this->filterRecords($bills->getQuery(), $filters, 'bills.billing_period', $consumerId);
        $bills->when(($filters['prn'] ?? '') !== '', fn ($query) => $query->where('bills.prn', 'like', '%'.$filters['prn'].'%'));

        $status = $filters['status'] ?? '';
        if ($status === '') {
            return $bills;
        }
        if (! app()->isLocal()) {
            return $this->filterRecordedStatus($bills, $status);
        }

        $matchingScenarioIds = $this->ledger->withAmounts((clone $bills)->whereIn('bills.consumer_id', $this->ledger->localAccountIds()))->get()
            ->filter(fn (Bill $bill) => match ($status) {
                'Unpaid' => $bill->balance_due > 0,
                'Carried Forward' => $bill->balanceHasBeenCarriedForward(),
                'Paid with Balance' => $bill->paymentSettlementStatus() === 'Paid with Balance',
                default => $bill->waterBillingStatus() === $status,
            })->modelKeys();

        return $bills->where(function (EloquentBuilder $query) use ($status, $matchingScenarioIds): void {
            $query->where(fn ($issued) => $this->filterRecordedStatus($issued->whereNotIn('bills.consumer_id', $this->ledger->localAccountIds()), $status))
                ->orWhereIn('bills.id', $matchingScenarioIds);
        });
    }

    private function filterRecordedStatus(EloquentBuilder $bills, string $status): EloquentBuilder
    {
        if ($status === 'Scheduled') {
            return $bills->where('bills.created_at', '>', now());
        }
        if ($status === 'Carried Forward') {
            return $bills->whereNotNull('bills.carried_forward_to_bill_id');
        }

        // Match the live status shown in history using the same inputs as Bill::balance_due.
        $verified = "(SELECT COALESCE(SUM(payments.amount), 0) FROM payments
            WHERE payments.bill_id = bills.id AND payments.status = 'Verified' AND payments.payment_type = 'Water')";
        $balance = "ROUND(bills.amount - {$verified} - COALESCE(bills.waived_reconnection_fee_amount, 0), 2)";
        if ($status === 'Unpaid') {
            return $bills->whereNull('bills.carried_forward_to_bill_id')->whereRaw("{$balance} > 0");
        }

        return $bills->whereRaw("CASE
            WHEN bills.carried_forward_to_bill_id IS NOT NULL OR {$balance} <= 0 THEN 'Paid'
            WHEN bills.notice_level = ? THEN ?
            WHEN bills.status IN (?, ?, ?) THEN bills.status
            WHEN {$verified} > 0 THEN 'Paid with Balance'
            ELSE 'Unpaid'
        END = ?", [
            ConsumerBalanceService::DISCONNECTION_NOTICE,
            ConsumerBalanceService::DISCONNECTION_NOTICE,
            ...self::OPEN_STATUSES,
            $status,
        ]);
    }

    public function accounts(array $filters): LengthAwarePaginator
    {
        $matchingAccounts = $this->recordQuery($filters)
            ->selectRaw('consumer_id, MAX(submitted_at) as last_submission')
            ->groupBy('consumer_id');

        $accounts = Consumer::query()
            ->joinSub($matchingAccounts, 'matching_accounts', 'matching_accounts.consumer_id', '=', 'consumers.id')
            ->select('consumers.*')
            ->withMax(['bills as latest_billing_period' => fn ($query) => $query->where('bill_type', 'Water')], 'billing_period')
            ->withCasts(['latest_billing_period' => 'date'])
            ->orderByDesc('matching_accounts.last_submission')
            ->orderByDesc('consumers.id')
            ->paginate(10)->appends(array_filter($filters, fn ($value) => $value !== ''));

        $matchedScenarioAccounts = app()->isLocal()
            ? $accounts->getCollection()->whereIn('meter_number', WaterBillingLedger::METERS)->modelKeys()
            : [];
        $scenarioBills = $matchedScenarioAccounts
            ? $this->ledger->withAmounts($this->ledger->bills())->whereIn('consumer_id', $matchedScenarioAccounts)
                ->orderByDesc('billing_period')->orderByDesc('id')->get()->groupBy('consumer_id')
            : collect();

        $openBills = $this->balances->openBillsForConsumers($accounts->getCollection()->modelKeys())
            ->groupBy('consumer_id');

        return $accounts->through(function (Consumer $consumer) use ($openBills, $scenarioBills): array {
            $history = $scenarioBills->get($consumer->id);
            if ($history?->isNotEmpty()) {
                $summary = $this->ledger->summary($history);
                $consumer->latest_billing_period = $summary['current_bill']?->billing_period;

                return ['consumer' => $consumer, 'outstanding' => $summary['outstanding_balance'], 'status' => $summary['status']];
            }

            $bills = $openBills->get($consumer->id, collect());
            $status = match (true) {
                $bills->isEmpty() => $consumer->latest_billing_period ? 'Paid' : 'Awaiting Billing',
                $bills->contains('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE) => ConsumerBalanceService::DISCONNECTION_NOTICE,
                $bills->contains('status', 'Overdue') => 'Overdue',
                $bills->contains('status', 'Paid with Balance') => 'Paid with Balance',
                default => 'Unpaid',
            };

            return [
                'consumer' => $consumer,
                'outstanding' => round($bills->sum('balance_due'), 2),
                'status' => $status,
            ];
        });
    }

    public function history(Consumer $consumer, array $filters, int $page): LengthAwarePaginator
    {
        $query = $this->recordQuery($filters, $consumer->id)
            ->orderByDesc('record_date')->orderByDesc('record_id')->orderBy('record_type');
        $records = $query->paginate(6, page: $page);
        if ($page > $records->lastPage()) {
            $records = $query->paginate(6, page: $records->lastPage(), total: $records->total());
        }
        $pageRecords = $records->getCollection();

        $bills = $this->ledger->withAmounts($this->ledger->bills())
            ->whereKey($pageRecords->where('record_type', 'bill')->pluck('record_id'))
            ->get()->keyBy('id');
        $readings = MeterReading::query()->withBillingFixtures()
            ->whereKey($pageRecords->where('record_type', 'reading')->pluck('record_id'))
            ->get()->keyBy('id');

        return $records->through(function ($record) use ($bills, $readings, $consumer) {
            $record->item = ($record->record_type === 'bill' ? $bills : $readings)->get($record->record_id);
            if ($record->item instanceof Bill) {
                $record->item->setRelation('consumer', $consumer);
                $record->status = WaterBillingLedger::isScenarioAccount($consumer) ? $record->item->waterBillingStatus() : $this->billStatus($record->item);
            }

            return $record;
        });
    }

    private function recordQuery(array $filters, ?int $consumerId = null): Builder
    {
        $bills = $this->bills($filters, $consumerId)->toBase()
            ->selectRaw("bills.consumer_id, bills.id as record_id, 'bill' as record_type, bills.billing_period as record_date, COALESCE((SELECT COALESCE(meter_readings.submitted_at, meter_readings.created_at) FROM meter_readings WHERE meter_readings.id = bills.meter_reading_id), bills.created_at) as submitted_at");

        if (($filters['status'] ?? '') !== '' || ($filters['prn'] ?? '') !== '') {
            return DB::query()->fromSub($bills, 'billing_records');
        }

        $readings = DB::table('meter_readings')
            ->where(function (Builder $query): void {
                $query->where('meter_readings.is_billing_fixture', false);
                if (app()->isLocal()) {
                    $query->orWhereIn('meter_readings.consumer_id', $this->ledger->localAccountIds());
                }
            })
            ->where('meter_readings.status', MeterReading::STATUS_ENCODED)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('bills')->whereColumn('bills.meter_reading_id', 'meter_readings.id'))
            ->selectRaw("meter_readings.consumer_id, meter_readings.id as record_id, 'reading' as record_type, meter_readings.reading_date as record_date, COALESCE(meter_readings.submitted_at, meter_readings.created_at) as submitted_at");
        $this->filterRecords($this->ledger->withoutUnbilledScenarioNovember($readings), $filters, 'meter_readings.reading_date', $consumerId);

        return DB::query()->fromSub($bills->unionAll($readings), 'billing_records');
    }

    private function filterRecords(Builder $query, array $filters, string $dateColumn, ?int $consumerId): void
    {
        $search = $filters['search'] ?? '';
        $query->when($consumerId, fn ($records) => $records->where('consumer_id', $consumerId))
            ->when($filters['year'] ?? '', fn ($records, $year) => $records->whereYear($dateColumn, $year))
            ->when($filters['month'] ?? '', fn ($records, $month) => $records->whereMonth($dateColumn, $month))
            ->when($search !== '', fn ($records) => $records->whereIn('consumer_id', $this->matchingConsumerIds($search)));
    }

    private function billStatus(Bill $bill): string
    {
        return match (true) {
            ! $bill->isOutstanding() => 'Paid',
            $bill->notice_level === ConsumerBalanceService::DISCONNECTION_NOTICE => ConsumerBalanceService::DISCONNECTION_NOTICE,
            in_array($bill->status, self::OPEN_STATUSES, true) => $bill->status,
            default => $bill->paymentSettlementStatus(),
        };
    }
}
