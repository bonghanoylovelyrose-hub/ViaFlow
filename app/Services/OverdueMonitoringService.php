<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\DisconnectionNotice;
use Illuminate\Support\Facades\DB;

/**
 * Applies the water billing cycle and loan disconnection rules.
 *
 * Everything is derived from real bill amounts and verified payments. A
 * qualifying account stays Active with an active disconnection notice until an
 * administrator confirms the physical disconnection. Settling the full balance
 * before that point closes the notice automatically while keeping its history.
 */
class OverdueMonitoringService
{
    public function __construct(
        private readonly ConsumerBalanceService $balances,
        private readonly DisconnectionNoticeService $notices,
    ) {
    }

    /**
     * Re-evaluates a consumer's overdue standing and disconnection notice.
     *
     * @return array{months_unpaid:int, flagged:bool, notice_issued:bool, notice_cleared:bool}
     */
    public function evaluate(Consumer $consumer, bool $notify = false): array
    {
        $localWaterFixture = app()->environment('local')
            && in_array((string) $consumer->id, config('billing.local_fixture_consumer_ids', []), true);

        $result = DB::transaction(function () use ($consumer, $localWaterFixture): array {
            $lockedConsumer = Consumer::query()->lockForUpdate()->findOrFail($consumer->id);

            return $this->evaluateStanding($lockedConsumer, $localWaterFixture);
        });

        $consumer->refresh();

        $sendWater = ! $localWaterFixture && ($notify || $result['water_notice_issued']);
        $sendLoan = $notify || $result['loan_notice_issued'];
        if ($sendWater || $sendLoan) {
            // Bill generation can wrap reconciliation in a larger transaction.
            // Wait until the bill and notice have committed before sending.
            DB::afterCommit(function () use ($consumer, $notify, $sendWater, $sendLoan): void {
                if ($sendWater) {
                    $this->notices->sendDisconnectionNotice($consumer);
                    if ($notify) {
                        $this->sendOverdueReminders($consumer);
                    }
                }
                if ($sendLoan) {
                    $this->notices->sendLoanNotices($consumer);
                }
            });
        }

        unset($result['water_notice_issued'], $result['loan_notice_issued']);

        return $result;
    }

    private function evaluateStanding(Consumer $consumer, bool $localWaterFixture): array
    {
        ['months_unpaid' => $monthsUnpaid, 'notice_bill' => $noticeBill] = $this->balances->waterNoticeStanding($consumer->id);
        $totalOutstanding = $this->balances->totalOutstandingFor($consumer->id);

        $activeNotice = $this->notices->activeNoticeFor($consumer->id, DisconnectionNotice::SOURCE_WATER);
        $noticeIssued = false;
        $noticeCleared = false;

        // A notice belongs to a connected account. Once an administrator has
        // confirmed a physical disconnection, the account moves to Inactive and
        // no new notice is raised for the same delinquent run.
        $alreadyDisconnected = $consumer->reconnection_pending || in_array($consumer->status, ['Disconnected', 'For Reconnection'], true);

        if (! $localWaterFixture && $noticeBill && $totalOutstanding > 0 && ! $activeNotice && ! $alreadyDisconnected
            && in_array($consumer->status, ['Active', 'For Review'], true)) {
            $activeNotice = $this->notices->raiseNotice($consumer, $monthsUnpaid, $noticeBill);
            $noticeIssued = $activeNotice !== null;
        }

        // Full payment before the administrator confirms disconnection closes
        // the active notice. The row remains in the notice history.
        if (! $localWaterFixture && $activeNotice && $totalOutstanding <= 0) {
            $this->notices->settleNotice($activeNotice);
            $activeNotice = null;
            $noticeCleared = true;
        }

        $flagChanges = $this->syncBillNoticeFlags(
            $consumer,
            $monthsUnpaid,
            $activeNotice !== null,
        );

        $noticeCleared = $noticeCleared || $flagChanges;

        [$loanIssued, $loanSettled] = $this->notices->reconcileLoanNotices($consumer);
        $hasLoanNotice = $consumer->disconnectionNotices()->active()->where('source', DisconnectionNotice::SOURCE_LOAN)->exists();

        $this->syncConsumerStatus(
            $consumer,
            $localWaterFixture ? 0 : $monthsUnpaid,
            $activeNotice !== null || $hasLoanNotice,
            $totalOutstanding,
        );

        return [
            'months_unpaid' => $monthsUnpaid,
            'flagged' => $activeNotice !== null || $hasLoanNotice,
            'notice_issued' => $noticeIssued || $loanIssued,
            'notice_cleared' => $noticeCleared || $loanSettled,
            'water_notice_issued' => $noticeIssued,
            'loan_notice_issued' => $loanIssued,
        ];
    }

    /**
     * Keeps the legacy bill flags aligned with the notice table. Other modules
     * (risk scoring, reports, payment rules) still read these flags.
     */
    private function syncBillNoticeFlags(Consumer $consumer, int $monthsUnpaid, bool $carryNotice): bool
    {
        $noticeWasCleared = false;

        $openBills = Bill::query()
            ->forCollection()
            ->where('consumer_id', $consumer->id)
            ->where('bill_type', 'Water')
            ->whereIn('status', ['Unpaid', 'Overdue', 'Paid with Balance'])
            ->get();

        foreach ($openBills as $bill) {
            $newNoticeLevel = $carryNotice ? ConsumerBalanceService::DISCONNECTION_NOTICE : null;

            if ((int) $bill->months_unpaid === $monthsUnpaid && $bill->notice_level === $newNoticeLevel) {
                continue;
            }

            if (! $carryNotice && $bill->notice_level === ConsumerBalanceService::DISCONNECTION_NOTICE) {
                $noticeWasCleared = true;
            }

            $bill->forceFill([
                'months_unpaid' => $monthsUnpaid,
                'notice_level' => $newNoticeLevel,
            ])->save();
        }

        if (! $carryNotice) {
            // Settled, carried-forward, or otherwise closed bills must not keep
            // an old notice flag alive after the notice has been closed.
            $staleNotices = Bill::query()
                ->where('consumer_id', $consumer->id)
                ->where('bill_type', 'Water')
                ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE);

            if ($staleNotices->exists()) {
                $noticeWasCleared = true;
                $staleNotices->update(['notice_level' => null]);
            }
        } elseif ($openBills->isNotEmpty()) {
            // A settled bill from an earlier cycle is not part of the current
            // active notice.
            Bill::query()
                ->where('consumer_id', $consumer->id)
                ->where('bill_type', 'Water')
                ->where('notice_level', ConsumerBalanceService::DISCONNECTION_NOTICE)
                ->whereNotIn('id', $openBills->pluck('id'))
                ->update(['notice_level' => null]);
        }

        return $noticeWasCleared;
    }

    /**
     * Warns the consumer about every bill that has passed its due date but is
     * still inside (or past) its grace period.
     */
    private function sendOverdueReminders(Consumer $consumer): void
    {
        if ($this->balances->totalOutstandingFor($consumer->id) <= 0) {
            return;
        }

        $bills = Bill::query()
            ->forCollection()
            ->withSum(['payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified')], 'amount')
            ->where('consumer_id', $consumer->id)
            ->where('bill_type', 'Water')
            ->where('status', '!=', 'Paid')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();

        foreach ($bills as $bill) {
            if (round((float) $bill->balance_due, 2) <= 0) {
                continue;
            }

            $this->notices->sendOverdueReminder($consumer, $bill);
        }
    }

    /**
     * Keeps the account Active while a notice is outstanding. Disconnection is
     * only recorded when an administrator confirms it, never automatically.
     */
    private function syncConsumerStatus(
        Consumer $consumer,
        int $monthsUnpaid,
        bool $hasActiveNotice,
        float $totalOutstanding,
    ): void {
        if ($consumer->reconnection_pending || in_array($consumer->status, [...Consumer::ACCOUNT_CATEGORY_STATUSES['pending'], 'Rejected'], true)) {
            return;
        }

        if ($hasActiveNotice) {
            // The service is still connected. Bring any earlier "For Review"
            // label back to Active so the account stays under Active Accounts
            // until the physical disconnection is confirmed.
            if (! in_array($consumer->status, ['Active', 'Disconnected', 'For Reconnection'], true)) {
                $consumer->forceFill(['status' => 'Active'])->save();
            }

            return;
        }

        if ($monthsUnpaid > 0 && $totalOutstanding > 0) {
            if (! in_array($consumer->status, ['For Review', 'Disconnected', 'For Reconnection'], true)) {
                $consumer->forceFill(['status' => 'For Review'])->save();
            }

            return;
        }

        if ($totalOutstanding <= 0) {
            if ($consumer->status === 'Disconnected' && $this->notices->outstandingForReconnection($consumer) <= 0) {
                // The balance is settled after a real disconnection: the
                // account waits for the authorised reconnection procedure.
                $consumer->forceFill(['status' => 'For Reconnection'])->save();
            } elseif ($consumer->status === 'For Review') {
                // A connected account that cleared its arrears returns to
                // normal service; it was never physically disconnected.
                $consumer->forceFill(['status' => 'Active'])->save();
            }
        }
    }
}
