<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for outstanding balances at a point in time.
 *
 * The dashboard and the Reports Center both use this service so a balance
 * snapshot cannot disagree with the report it links to. Only payments dated on
 * or before the cutoff are applied, and a bill whose balance has already been
 * carried into a bill generated on or before the cutoff is excluded.
 */
class OutstandingBalanceService
{
    /**
     * @return Collection<int, Bill>
     */
    public function openBillsAsOf(Carbon|string|null $cutoff = null): Collection
    {
        $cutoff = $cutoff === null
            ? now()->endOfDay()
            : Carbon::parse($cutoff)->endOfDay();

        $bills = Bill::query()
            ->with([
                'consumer:id,full_name,account_number,service_purok,contact_number,status',
                'carriedForwardTo:id,billing_period',
            ])
            ->withSum([
                'payments as verified_payments_sum_amount' => fn ($query) => $query
                    ->where('status', 'Verified')
                    ->where('payment_type', 'Water')
                    ->where('payment_date', '<=', $cutoff->toDateString()),
            ], 'amount')
            ->where('billing_period', '<=', $cutoff->toDateString())
            ->where('bill_type', 'Water')
            ->orderBy('consumer_id')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();

        return $bills
            ->filter(function (Bill $bill) use ($cutoff): bool {
                $carriedToPeriod = $bill->carriedForwardTo?->billing_period;

                if ($carriedToPeriod !== null && $carriedToPeriod->lte($cutoff)) {
                    return false;
                }

                return $this->balanceFor($bill) > 0;
            })
            ->each(fn (Bill $bill) => $bill->setAttribute('balance_as_of', $this->balanceFor($bill)))
            ->values();
    }

    public function balanceFor(Bill $bill): float
    {
        $paid = array_key_exists('verified_payments_sum_amount', $bill->getAttributes())
            ? (float) $bill->getAttributes()['verified_payments_sum_amount']
            : (float) $bill->verified_paid_amount;

        return round(max(0, (float) $bill->amount - $paid), 2);
    }

    /**
     * @param  Collection<int, Bill>  $openBills
     * @return array{
     *     total: float,
     *     accounts: int,
     *     open_bills: int,
     *     notices: int,
     *     average: float,
     *     oldest_due_date: Carbon|null,
     *     as_of: Carbon,
     *     buckets: list<array{label: string, amount: float, accounts: int}>
     * }
     */
    public function summarize(Collection $openBills, Carbon|string|null $cutoff = null): array
    {
        $cutoff = $cutoff === null
            ? now()->endOfDay()
            : Carbon::parse($cutoff)->endOfDay();

        $bucketDefinitions = [
            ['label' => 'Current', 'minimum' => null, 'maximum' => 0],
            ['label' => '1-30 days', 'minimum' => 1, 'maximum' => 30],
            ['label' => '31-60 days', 'minimum' => 31, 'maximum' => 60],
            ['label' => '61-90 days', 'minimum' => 61, 'maximum' => 90],
            ['label' => '90+ days', 'minimum' => 91, 'maximum' => null],
        ];

        $buckets = collect($bucketDefinitions)
            ->map(fn (array $bucket): array => $bucket + ['amount' => 0.0, 'account_ids' => []])
            ->all();

        $noticeAccountIds = [];
        $total = 0.0;
        $oldestDueDate = null;

        foreach ($openBills as $bill) {
            $balance = (float) ($bill->balance_as_of ?? $this->balanceFor($bill));
            $total += $balance;

            if ($bill->notice_level === ConsumerBalanceService::DISCONNECTION_NOTICE) {
                $noticeAccountIds[$bill->consumer_id] = true;
            }

            if ($bill->due_date !== null && ($oldestDueDate === null || $bill->due_date->lt($oldestDueDate))) {
                $oldestDueDate = $bill->due_date->copy();
            }

            $daysPastDue = $this->daysPastDue($bill, $cutoff);
            $bucketIndex = match (true) {
                $daysPastDue <= 0 => 0,
                $daysPastDue <= 30 => 1,
                $daysPastDue <= 60 => 2,
                $daysPastDue <= 90 => 3,
                default => 4,
            };

            $buckets[$bucketIndex]['amount'] += $balance;
            $buckets[$bucketIndex]['account_ids'][$bill->consumer_id] = true;
        }

        $accounts = $openBills->pluck('consumer_id')->filter()->unique()->count();

        return [
            'total' => round($total, 2),
            'accounts' => $accounts,
            'open_bills' => $openBills->count(),
            'notices' => count($noticeAccountIds),
            'average' => $accounts > 0 ? round($total / $accounts, 2) : 0.0,
            'oldest_due_date' => $oldestDueDate,
            'as_of' => $cutoff->copy(),
            'buckets' => collect($buckets)->map(fn (array $bucket): array => [
                'label' => $bucket['label'],
                'amount' => round((float) $bucket['amount'], 2),
                'accounts' => count($bucket['account_ids']),
            ])->all(),
        ];
    }

    /**
     * @return array{
     *     total: float,
     *     accounts: int,
     *     open_bills: int,
     *     notices: int,
     *     average: float,
     *     oldest_due_date: Carbon|null,
     *     as_of: Carbon,
     *     buckets: list<array{label: string, amount: float, accounts: int}>
     * }
     */
    public function summarizeAsOf(Carbon|string|null $cutoff = null): array
    {
        $cutoff = $cutoff === null
            ? now()->endOfDay()
            : Carbon::parse($cutoff)->endOfDay();

        return $this->summarize($this->openBillsAsOf($cutoff), $cutoff);
    }

    private function daysPastDue(Bill $bill, Carbon $cutoff): int
    {
        if ($bill->due_date === null) {
            return 0;
        }

        $dueDate = $bill->due_date->copy()->startOfDay();
        $asOf = $cutoff->copy()->startOfDay();

        if ($dueDate->greaterThanOrEqualTo($asOf)) {
            return 0;
        }

        return (int) floor(($asOf->timestamp - $dueDate->timestamp) / 86400);
    }
}
