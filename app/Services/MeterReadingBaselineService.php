<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use Illuminate\Support\Carbon;

class MeterReadingBaselineService
{
    public function previousReadingFor(Consumer|int $consumer, ?Carbon $billingPeriod = null): float
    {
        $consumerId = $consumer instanceof Consumer ? $consumer->id : $consumer;
        $period = $billingPeriod?->copy()->startOfMonth();

        $account = $consumer instanceof Consumer ? $consumer : (app()->isLocal() ? Consumer::find($consumerId) : null);
        $bills = WaterBillingLedger::isScenarioAccount($account)
            ? app(WaterBillingLedger::class)->bills()
            : Bill::query()->forCollection();

        $latestPreviousBill = $bills
            ->with('meterReading')
            ->where('consumer_id', $consumerId)
            ->whereNotNull('meter_reading_id')
            ->when($period, fn ($query) => $query->where('billing_period', '<', $period->toDateString()))
            ->orderByDesc('billing_period')
            ->orderByDesc('id')
            ->first();

        if ($latestPreviousBill?->meterReading) {
            return round((float) $latestPreviousBill->meterReading->current_reading, 2);
        }

        $initialReading = MeterReading::query()
            ->where('consumer_id', $consumerId)
            ->whereDoesntHave('bill', fn ($bill) => $bill->where('is_demo', true))
            ->orderBy('reading_date')
            ->orderBy('id')
            ->first();

        return round((float) ($initialReading?->previous_reading ?? 0), 2);
    }
}
