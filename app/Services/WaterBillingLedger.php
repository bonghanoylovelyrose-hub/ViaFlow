<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class WaterBillingLedger
{
    public const METERS = ['MT-0909', 'MT-9191', 'MT-3333', 'MT-7777'];

    public const NOVEMBER_PERIOD = '2026-11-01';

    public static function isScenarioAccount(?Consumer $consumer): bool
    {
        return app()->isLocal() && in_array($consumer?->meter_number, self::METERS, true);
    }

    public static function isScenarioNovember(?Consumer $consumer, ?string $period): bool
    {
        return $period === self::NOVEMBER_PERIOD && self::isScenarioAccount($consumer);
    }

    public function bills(): Builder
    {
        return Bill::query()->where('bills.bill_type', 'Water')->whereNull('bills.superseded_by_bill_id')
            ->when(app()->isLocal(), fn (Builder $query) => $query->withBillingFixtures()->where(function (Builder $query): void {
                $query->where('bills.is_billing_fixture', false)->orWhereIn('bills.consumer_id', $this->localAccountIds());
            })->whereNotIn('bills.id', $this->novemberProjections()->select('bills.id')));
    }

    public function novemberProjections(): Builder
    {
        return Bill::query()->onlyBillingFixtures()->where('bills.bill_type', 'Water')
            ->whereDate('bills.billing_period', self::NOVEMBER_PERIOD)->whereIn('bills.consumer_id', $this->localAccountIds());
    }

    public function withoutArchivedNovemberReadings(Builder $readings): Builder
    {
        return $readings->when(app()->isLocal(), fn (Builder $query) => $query->whereDoesntHave('bill', fn (Builder $bill) => $bill
            ->where('bills.bill_type', 'Water')->whereDate('bills.billing_period', self::NOVEMBER_PERIOD)
            ->whereIn('bills.consumer_id', $this->localAccountIds())->whereNotNull('bills.superseded_by_bill_id')));
    }

    public function withoutUnbilledScenarioNovember(QueryBuilder $readings): QueryBuilder
    {
        return $readings->when(app()->isLocal(), fn (QueryBuilder $query) => $query->whereNot(fn (QueryBuilder $november) => $november
            ->whereDate('meter_readings.reading_date', '>=', self::NOVEMBER_PERIOD)
            ->whereDate('meter_readings.reading_date', '<', '2026-12-01')
            ->whereIn('meter_readings.consumer_id', $this->localAccountIds())));
    }

    public function outstandingBefore(Consumer $consumer, string $period): Collection
    {
        return $this->withAmounts($this->bills())->where('consumer_id', $consumer->id)
            ->where('billing_period', '<', $period)->orderBy('billing_period')->orderBy('id')->get()
            ->filter(fn (Bill $bill): bool => $bill->isOutstanding())->values();
    }

    public function localAccountIds(): Builder
    {
        return Consumer::query()->select('id')->whereIn('meter_number', self::METERS);
    }

    public function withAmounts(Builder $query): Builder
    {
        return $query->with(['meterReading', 'carriedForwardTo.consumer', 'earlierIssues' => fn ($issues) => $issues->withSum([
            'payments as verified_payments_sum_amount' => fn ($payments) => $payments->where('status', 'Verified')->where('payment_type', 'Water'),
        ], 'amount')])->withSum([
            'payments as verified_payments_sum_amount' => fn ($payments) => $payments->where('status', 'Verified')->where('payment_type', 'Water'),
        ], 'amount');
    }

    public function summary(Collection $bills): array
    {
        $issued = $bills->filter(fn (Bill $bill): bool => $bill->hasBeenIssued());
        $current = $issued->first();

        return [
            'current_bill' => $current,
            'issued_bills' => $issued,
            'outstanding_balance' => round((float) $issued->sum('balance_due'), 2),
            'status' => $current?->waterBillingStatus() ?? 'Awaiting Billing',
        ];
    }
}
