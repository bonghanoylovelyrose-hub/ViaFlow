<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the meter reader's pending and completed reading lists.
 *
 * Both lists are scoped to one billing month. Pending is derived from
 * consumers, not from a separate queue table, so a verified account joins
 * automatically and can never be queued twice. The order is newest-verified
 * first: the most recently verified account is at the top and older accounts
 * follow down to the oldest at the bottom. The verification timestamp is
 * written once and never reset by a new billing month, so the order stays
 * stable across periods.
 *
 * Completed readings are limited to the selected billing month and ordered by
 * when the reading was actually encoded (created_at), not by the back-datable
 * reading_date field. Earlier months are never deleted or overwritten.
 */
class MeterReadingQueueService
{
    public function pendingConsumers(string $search = '', ?Carbon $period = null): Collection
    {
        [$periodStart, $periodEnd] = $this->monthBounds($period);

        return Consumer::query()
            ->readingQueueEligible()
            ->when($search !== '', fn (Builder $query) => $this->filterConsumerSearch($query, $search))
            // A past month's queue only contains accounts that were already
            // verified by the end of that month. Current-month queues include
            // every eligible account verified during the month.
            ->whereRaw(
                "COALESCE(verified_at, created_at, '1970-01-01 00:00:00') <= ?",
                [$periodEnd.' 23:59:59'],
            )
            // An Encoded reading for the selected month completes the account's
            // cycle for every reader. Pending and Needs Revisit captures are
            // not complete, so those accounts stay in the queue.
            ->whereDoesntHave('meterReadings', function (Builder $query) use ($periodStart, $periodEnd): void {
                app(WaterBillingLedger::class)->withoutArchivedNovemberReadings($query)
                    ->where('status', MeterReading::STATUS_ENCODED)
                    ->whereBetween('reading_date', [$periodStart, $periodEnd]);
            })
            ->orderByRaw("COALESCE(verified_at, created_at, '1970-01-01 00:00:00') desc")
            ->orderByDesc('id')
            ->get();
    }

    public function completedReadings(User $user, string $search = '', ?Carbon $period = null, bool $billingFixture = false): Collection
    {
        [$periodStart, $periodEnd] = $this->monthBounds($period);

        $query = MeterReading::query();
        if ($billingFixture) {
            $query->onlyBillingFixtures();
        } else {
            app(WaterBillingLedger::class)->withoutArchivedNovemberReadings($query);
        }

        return $query
            ->with(['consumer', 'reader'])
            ->where('status', MeterReading::STATUS_ENCODED)
            ->whereBetween('reading_date', [$periodStart, $periodEnd])
            // Keep one row per consumer per billing cycle even if an earlier
            // import left duplicate completed records behind. The superseded
            // row is not deleted and remains available in All Captured
            // Readings and in the database.
            ->whereNotExists(function (QueryBuilder $query) use ($periodStart, $periodEnd): void {
                $query->select(DB::raw(1))
                    ->from('meter_readings as later_reading')
                    ->whereColumn('later_reading.consumer_id', 'meter_readings.consumer_id')
                    ->whereColumn('later_reading.is_billing_fixture', 'meter_readings.is_billing_fixture')
                    ->where('later_reading.status', MeterReading::STATUS_ENCODED)
                    ->whereBetween('later_reading.reading_date', [$periodStart, $periodEnd])
                    ->where(function (QueryBuilder $query): void {
                        $query
                            ->whereColumn('later_reading.created_at', '>', 'meter_readings.created_at')
                            ->orWhere(function (QueryBuilder $query): void {
                                $query
                                    ->whereColumn('later_reading.created_at', '=', 'meter_readings.created_at')
                                    ->whereColumn('later_reading.id', '>', 'meter_readings.id');
                            });
                    });
            })
            ->when($user->isMeterReader(), fn (Builder $query) => $query->where('reader_id', $user->id))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('consumer', function (Builder $consumerQuery) use ($search): void {
                    $this->filterConsumerSearch($consumerQuery, $search);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBounds(?Carbon $period): array
    {
        $month = ($period ?? now())->copy()->startOfMonth();

        return [
            $month->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function filterConsumerSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%")
                ->orWhere('meter_number', 'like', "%{$search}%");
        });
    }
}
