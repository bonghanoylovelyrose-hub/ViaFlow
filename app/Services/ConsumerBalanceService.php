<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use Illuminate\Support\Collection;

class ConsumerBalanceService
{
    /**
     * A consumer is flagged for disconnection once this many consecutive
     * billing periods remain unpaid past their grace period.
     */
    public const DISCONNECTION_NOTICE_AFTER_MONTHS = 3;

    /**
     * Days after the due date that a consumer is still allowed to pay before
     * the bill is treated as overdue.
     *
     * A bill due on 4 September is therefore overdue from 8 September.
     */
    public const PAYMENT_GRACE_DAYS = 3;

    public const DISCONNECTION_NOTICE = 'Disconnection Notice';

    /**
     * Every bill that still owes money, oldest billing period first.
     *
     * A balance that a newer bill already absorbed is excluded here because it
     * has moved, not multiplied. Every peso therefore lives in exactly one bill,
     * so summing these can never count the same debt twice.
     */
    public function openBillsFor(int $consumerId): Collection
    {
        return $this->openBillsForConsumers([$consumerId]);
    }

    /** @param list<int> $consumerIds */
    public function openBillsForConsumers(array $consumerIds): Collection
    {
        return Bill::query()
            ->forCollection()
            ->with(['consumer', 'meterReading'])
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Water')], 'amount')
            ->whereIn('consumer_id', $consumerIds)
            ->where('bill_type', 'Water')
            ->whereNull('carried_forward_to_bill_id')
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get()
            ->filter(fn (Bill $bill): bool => round((float) $bill->balance_due, 2) > 0)
            ->values();
    }

    /**
     * Outstanding bills oldest-first - the order payments are applied in.
     */
    public function payableOpenBillsFor(int $consumerId): Collection
    {
        return $this->openBillsFor($consumerId);
    }

    /**
     * @param  Collection<int, Bill>  $openBills
     * @return Collection<int, Bill>
     */
    public function payableOpenBills(Collection $openBills): Collection
    {
        return $openBills
            ->filter(fn (Bill $bill): bool => $bill->isOutstanding())
            ->sortBy(fn (Bill $bill) => sprintf('%s-%010d', $bill->billing_period?->format('Ymd') ?? '99999999', $bill->id))
            ->values();
    }

    public function totalOutstandingFor(int $consumerId): float
    {
        return round((float) $this->openBillsFor($consumerId)->sum(fn (Bill $bill) => $bill->balance_due), 2);
    }

    public function recordedOutstandingFor(int $consumerId, bool $includeDemo = true): float
    {
        return round((float) Bill::query()->where('consumer_id', $consumerId)
            ->where('bill_type', 'Water')
            ->when(! $includeDemo, fn ($query) => $query->forCollection())
            ->whereNull('carried_forward_to_bill_id')
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Water')], 'amount')
            ->get()->sum(fn (Bill $bill) => $bill->balance_due), 2);
    }

    public function reviewBillsFor(int $consumerId): Collection
    {
        return Bill::query()
            ->with('consumer')
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Review')], 'amount')
            ->where('consumer_id', $consumerId)
            ->where('bill_type', 'Review')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();
    }

    /**
     * Everything still owed before a given billing period. This is the amount a
     * new bill must carry forward: every remaining unpaid month, never a single
     * one and never a peso twice.
     */
    public function outstandingBefore(int $consumerId, string $beforePeriod, ?int $ignoreBillId = null): Collection
    {
        return $this->openBillsFor($consumerId)
            ->filter(function (Bill $bill) use ($beforePeriod, $ignoreBillId): bool {
                if ($ignoreBillId && $bill->id === $ignoreBillId) {
                    return false;
                }

                return $bill->billing_period?->toDateString() < $beforePeriod;
            })
            ->values();
    }

    /**
     * The date a bill stops being inside its grace period, i.e. the first day
     * it counts as overdue. A bill due on 4 September is overdue from
     * 8 September with a three day grace period.
     */
    public function overdueDateFor(Bill $bill): ?\Illuminate\Support\Carbon
    {
        return $bill->due_date?->copy()->addDays(self::PAYMENT_GRACE_DAYS)->startOfDay();
    }

    /**
     * True once the grace period after the due date has elapsed.
     */
    public function isOverdue(Bill $bill): bool
    {
        $overdueOn = $this->overdueDateFor($bill);

        return $bill->hasBeenIssued() && $overdueOn !== null && today()->greaterThanOrEqualTo($overdueOn);
    }

    /**
 * Consecutive unpaid billing periods that are already past their grace
     * period, counting back from the newest unsettled bill.
     *
     * A month only counts once it is genuinely delinquent. Bills that are up to
     * date on top of the run are skipped, and the first settled month ends the
     * run, so two unpaid months separated by a paid month never add up to a
     * disconnection.
     */
    public function consecutiveUnpaidMonthsFor(int $consumerId): int
    {
        return $this->waterNoticeStanding($consumerId)['months_unpaid'];
    }

    /** @return array{months_unpaid:int, notice_bill:?Bill} */
    public function waterNoticeStanding(int $consumerId): array
    {
        $bills = Bill::query()
            ->forCollection()
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Water')], 'amount')
            ->where('consumer_id', $consumerId)
            ->where('bill_type', 'Water')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();

        return $this->waterNoticeStandingForBills($bills);
    }

    /** @param Collection<int, Bill> $bills Chronological water bills from a single account and ledger. */
    public function waterNoticeStandingForBills(Collection $bills): array
    {
        $remaining = array_replace([], ...array_values($this->remainingBillPortions($bills)));

        // Carried bills are marked Paid on purpose. The remaining portions
        // identify the original unpaid period even when a newer bill holds it.
        $overdueBills = $bills->filter(fn (Bill $bill) => ($remaining[$bill->id] ?? 0) > 0 && $this->isOverdue($bill));
        $overduePeriods = $overdueBills->groupBy(fn (Bill $bill) => $bill->billing_period?->format('Y-m'));
        $months = $bills->groupBy(fn (Bill $bill) => $bill->billing_period?->format('Y-m'))
            ->sortKeysDesc();
        $consecutive = 0;
        $previousMonth = null;

        foreach ($months as $period => $monthlyBills) {
            if ($period === '') {
                continue;
            }

            $unpaid = $monthlyBills->contains(fn (Bill $bill) => ($remaining[$bill->id] ?? 0) > 0);

            if (! $unpaid) {
                if ($previousMonth !== null) {
                    break;
                }

                continue;
            }

            if (! $overduePeriods->has($period)) {
                continue;
            }

            $month = $monthlyBills->first()->billing_period->copy()->startOfMonth();

            if ($previousMonth !== null && ! $month->equalTo($previousMonth->copy()->subMonth())) {
                break;
            }

            $previousMonth = $month;
            $consecutive++;
        }

        $cycle = $bills->last(fn (Bill $bill) => $bill->billing_period !== null && ! str_starts_with((string) $bill->prn, 'RECON-FEE-'));
        $previousTwoOverdue = $cycle?->meter_reading_id && $cycle->hasBeenIssued() && $overduePeriods->has([
            $cycle->billing_period->copy()->subMonthNoOverflow()->format('Y-m'),
            $cycle->billing_period->copy()->subMonthsNoOverflow(2)->format('Y-m'),
        ]);

        return [
            'months_unpaid' => $consecutive,
            'notice_bill' => $consecutive >= self::DISCONNECTION_NOTICE_AFTER_MONTHS
                ? $overdueBills->last()
                : ($previousTwoOverdue ? $cycle : null),
        ];
    }

    public function remainingBillPortionsFor(int $consumerId): array
    {
        $bills = Bill::query()->where('consumer_id', $consumerId)
            ->where('bill_type', 'Water')
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Water')], 'amount')
            ->orderBy('billing_period')->orderBy('id')->get();

        return $this->remainingBillPortions($bills);
    }

    private function remainingBillPortions(Collection $bills): array
    {
        if ($bills->isEmpty()) {
            return [];
        }
        $linkedFeeBill = Consumer::query()->whereKey($bills->first()->consumer_id)->value('reconnection_fee_bill_id');
        $feeIds = $bills->filter(fn (Bill $bill) => $bill->id === (int) $linkedFeeBill || str_starts_with((string) $bill->prn, 'RECON-FEE-'))
            ->pluck('id')->flip()->all();
        $forwarded = [];
        $remaining = [];

        foreach ($bills as $bill) {
            $portions = $forwarded[$bill->id] ?? [];
            $portions[$bill->id] = round(max(0, (float) $bill->amount - array_sum($portions)), 2);
            $waiver = (float) $bill->waived_reconnection_fee_amount;
            foreach ($portions as $sourceId => $amount) {
                if (isset($feeIds[$sourceId])) {
                    $applied = min($waiver, $amount);
                    $portions[$sourceId] = round($amount - $applied, 2);
                    $waiver = round($waiver - $applied, 2);
                }
            }
            $payment = $bill->verified_paid_amount;
            foreach ($portions as $sourceId => $amount) {
                $applied = min($payment, $amount);
                $portions[$sourceId] = round($amount - $applied, 2);
                $payment = round($payment - $applied, 2);
            }
            if ($bill->balanceIsCarriedForward()) {
                $forwarded[$bill->carried_forward_to_bill_id] = array_replace($forwarded[$bill->carried_forward_to_bill_id] ?? [], $portions);
            } else {
                $remaining[$bill->id] = $portions;
            }
        }

        return $remaining;
    }

    public function qualifiesForDisconnectionNotice(int $consumerId): bool
    {
        return $this->waterNoticeStanding($consumerId)['notice_bill'] !== null;
    }

    /**
     * Whether the consumer currently carries an active disconnection notice.
     *
     * The notice table is the audit trail and, once it has entries for the
     * consumer, the authoritative source. Bills flagged by older builds remain
     * readable as a fallback so existing records are not silently forgotten.
     */
    public function hasDisconnectionNotice(int $consumerId): bool
    {
        return $this->hasWaterDisconnectionNotice($consumerId) || DisconnectionNotice::query()
            ->where('consumer_id', $consumerId)->active()->where('source', DisconnectionNotice::SOURCE_LOAN)->exists();
    }

    public function hasWaterDisconnectionNotice(int $consumerId): bool
    {
        $notices = DisconnectionNotice::query()
            ->where('consumer_id', $consumerId)
            ->where('source', DisconnectionNotice::SOURCE_WATER);

        if ($notices->exists()) {
            return $notices->active()->exists();
        }

        return Bill::query()
            ->where('consumer_id', $consumerId)
            ->where('bill_type', 'Water')
            ->where('notice_level', self::DISCONNECTION_NOTICE)
            ->exists();
    }

    public function paymentRuleSummaryFor(int $consumerId): array
    {
        $totalOutstanding = $this->totalOutstandingFor($consumerId);
        $monthsUnpaid = $this->consecutiveUnpaidMonthsFor($consumerId);
        $hasDisconnectionNotice = $this->hasWaterDisconnectionNotice($consumerId);

        return [
            'active' => $hasDisconnectionNotice,
            'notice' => $hasDisconnectionNotice ? self::DISCONNECTION_NOTICE : 'None',
            'months_unpaid' => $monthsUnpaid,
            'months_required' => self::DISCONNECTION_NOTICE_AFTER_MONTHS,
            'total_outstanding' => $totalOutstanding,
            'required_payment_amount' => $hasDisconnectionNotice ? $totalOutstanding : null,
            'message' => $hasDisconnectionNotice
                ? 'Full payment is required. Partial payment is not allowed.'
                : 'Partial payment is allowed.',
        ];
    }

    public function latestPayableBillFor(int $consumerId): ?Bill
    {
        return $this->openBillsFor($consumerId)->last();
    }
}
