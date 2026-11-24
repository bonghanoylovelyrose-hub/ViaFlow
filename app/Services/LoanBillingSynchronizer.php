<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanBillingSynchronizer
{
    /**
     * Reconcile legacy bills, expose the complete saved schedule, and issue
     * bills for approved repayment periods.
     */
    public function synchronize(?int $consumerId = null, ?Carbon $throughPeriod = null, bool $issueFutureFirstBill = true, ?int $loanId = null): array
    {
        $throughPeriod = ($throughPeriod ?? today())->copy()->startOfMonth();

        return DB::transaction(function () use ($consumerId, $throughPeriod, $issueFutureFirstBill, $loanId): array {
            $stats = ['linked' => 0, 'generated' => 0, 'skipped' => 0, 'review_loan_ids' => []];

            $this->linkExistingLoanBills($consumerId, $stats, $loanId);

            $loans = Loan::query()
                ->whereIn('status', ['Ready for Scheduling', 'Approved', 'Active', 'Ongoing'])
                ->where('amount', '>', 0)
                ->whereIn('term_months', [3, 6, 12])
                ->where(fn ($query) => $query->whereNotNull('start_billing_period')->orWhereNotNull('approved_at')->orWhereHas('installments'))
                ->when($consumerId !== null, fn ($query) => $query->where('consumer_id', $consumerId))
                ->when($loanId !== null, fn ($query) => $query->whereKey($loanId))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($loans as $loan) {
                $schedule = $loan->installment_schedule_snapshot;
                if (! $this->scheduleIsValid($loan, $schedule)) {
                    continue;
                }
                $schedule = array_values($schedule);

                if (! $loan->start_billing_period) {
                    $existingStart = LoanInstallment::query()->where('loan_id', $loan->id)->orderBy('installment_number')->first();
                    $firstPeriod = $existingStart
                        ? $existingStart->billing_period->copy()->startOfMonth()->subMonthsNoOverflow((int) $existingStart->installment_number - 1)
                        : $loan->approved_at?->copy()->startOfMonth()->addMonthNoOverflow();
                    if (! $firstPeriod) {
                        $stats['skipped']++;

                        continue;
                    }
                    $loan->forceFill(['start_billing_period' => $firstPeriod->toDateString()])->save();
                }
                $start = Carbon::parse($loan->start_billing_period)->startOfMonth();
                $existingRows = LoanInstallment::query()->where('loan_id', $loan->id)->lockForUpdate()->get();
                $conflictingSchedule = $existingRows->contains(function (LoanInstallment $row) use ($schedule, $start): bool {
                    $number = (int) $row->installment_number;
                    $expected = $schedule[$number - 1]['amount'] ?? null;

                    return ! is_numeric($expected) || round((float) $row->required_amount, 2) !== round((float) $expected, 2)
                        || ! $row->billing_period?->isSameDay($start->copy()->addMonthsNoOverflow($number - 1));
                });
                if ($conflictingSchedule) {
                    $stats['skipped']++;
                    $stats['review_loan_ids'][] = $loan->id;

                    continue;
                }
                if (! $this->reconcileVerifiedLoanPayments($loan)) {
                    $stats['skipped']++;
                    $stats['review_loan_ids'][] = $loan->id;

                    continue;
                }

                for ($number = 1; $number <= (int) $loan->term_months; $number++) {
                    $period = $start->copy()->addMonthsNoOverflow($number - 1)->startOfMonth();

                    $scheduledRow = $schedule[$number - 1] ?? null;
                    if (! is_array($scheduledRow)
                        || (isset($scheduledRow['month']) && (int) $scheduledRow['month'] !== $number)
                        || ! is_numeric($scheduledRow['amount'] ?? null)
                        || (float) $scheduledRow['amount'] <= 0) {
                        $stats['skipped']++;

                        continue;
                    }

                    $installment = LoanInstallment::query()
                        ->where('loan_id', $loan->id)
                        ->where('installment_number', $number)
                        ->lockForUpdate()
                        ->first();
                    $existingPeriodBill = Bill::query()
                        ->where('loan_id', $loan->id)
                        ->whereDate('billing_period', $period->toDateString())
                        ->lockForUpdate()
                        ->first();

                    if ($existingPeriodBill && $existingPeriodBill->bill_type !== 'Loan') {
                        // Preserve mixed or historically ambiguous bill records.
                        $stats['skipped']++;

                        continue;
                    }

                    if ($installment?->bill_id) {
                        $linkedBill = Bill::query()->whereKey($installment->bill_id)->lockForUpdate()->first();
                        if (! $linkedBill || $linkedBill->bill_type !== 'Loan'
                            || $linkedBill->consumer_id !== $loan->consumer_id
                            || ($linkedBill->loan_id && $linkedBill->loan_id !== $loan->id)) {
                            $stats['skipped']++;

                            continue;
                        }

                        continue;
                    }

                    if ($existingPeriodBill && $existingPeriodBill->consumer_id !== $loan->consumer_id) {
                        $stats['skipped']++;

                        continue;
                    }

                    $requiredAmount = $installment && (float) $installment->required_amount > 0
                        ? round((float) $installment->required_amount, 2)
                        : round((float) $scheduledRow['amount'], 2);
                    $dueDate = $this->dueDateFor($loan, $number, $installment, $existingPeriodBill);
                    if (! $installment) {
                        $installment = LoanInstallment::query()->create([
                            'loan_id' => $loan->id,
                            'installment_number' => $number,
                            'billing_period' => $period->toDateString(),
                            'due_date' => $dueDate?->toDateString(),
                            'required_amount' => $requiredAmount,
                            'amount_paid' => 0,
                            'remaining_balance' => $requiredAmount,
                            'status' => 'Upcoming',
                        ]);
                    } elseif (! $installment->due_date && $dueDate) {
                        $installment->forceFill(['due_date' => $dueDate->toDateString()])->save();
                    }

                    $billingActive = in_array($loan->status, Loan::PAYMENT_STATUSES, true);
                    $allowFutureBill = $issueFutureFirstBill && $loan->status !== 'Ready for Scheduling';
                    if (! $billingActive || ($period->greaterThan($throughPeriod) && (! $allowFutureBill || $number !== 1))) {
                        continue;
                    }

                    if ($installment && (float) $installment->remaining_balance <= 0 && ! $existingPeriodBill) {
                        // A prepaid month is already settled and must not produce a new payable bill.
                        continue;
                    }

                    if ($requiredAmount <= 0 || ! $dueDate) {
                        $stats['skipped']++;

                        continue;
                    }

                    $prn = sprintf('LN-%d-%d', $loan->id, $number);
                    if (! $existingPeriodBill && Bill::query()->where('prn', $prn)->exists()) {
                        $stats['skipped']++;

                        continue;
                    }

                    $bill = $existingPeriodBill;
                    if (! $bill) {
                        $bill = Bill::query()->create([
                            'consumer_id' => $loan->consumer_id,
                            'bill_type' => 'Loan',
                            'loan_id' => $loan->id,
                            'billing_period' => $period->toDateString(),
                            'due_date' => $dueDate->toDateString(),
                            'prn' => $prn,
                            'amount' => $requiredAmount,
                            'loan_installment_amount' => $requiredAmount,
                            'status' => 'Unpaid',
                        ]);
                        $stats['generated']++;
                    }

                    $installment->forceFill([
                        'bill_id' => $bill->id,
                        'due_date' => $installment->due_date ?? $bill->due_date,
                    ])->syncStatus();
                    $bill->refresh()->syncPaymentStatus(false);
                    $stats['linked']++;
                }

                $this->allocateUnassignedLoanRepayments($loan);
                $this->refreshLoanBillStatuses($loan);
            }

            return $stats;
        });
    }

    private function linkExistingLoanBills(?int $consumerId, array &$stats, ?int $loanId): void
    {
        Bill::query()
            ->with('loan')
            ->where('bill_type', 'Loan')
            ->whereNotNull('loan_id')
            ->when($consumerId !== null, fn ($query) => $query->where('consumer_id', $consumerId))
            ->when($loanId !== null, fn ($query) => $query->where('loan_id', $loanId))
            ->orderBy('loan_id')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(function (Bill $bill) use (&$stats): void {
                $loan = $bill->loan;
                if (! $loan || (int) $loan->consumer_id !== (int) $bill->consumer_id) {
                    $stats['skipped']++;

                    return;
                }

                $installment = LoanInstallment::query()
                    ->where('bill_id', $bill->id)
                    ->orWhere(fn ($query) => $query->where('loan_id', $loan->id)->whereDate('billing_period', $bill->billing_period))
                    ->lockForUpdate()
                    ->first();
                if ($installment) {
                    if (! $installment->bill_id) {
                        $updates = ['bill_id' => $bill->id];
                        if (! $installment->due_date && $bill->due_date) {
                            $updates['due_date'] = $bill->due_date->toDateString();
                        }
                        if ((float) $installment->required_amount <= 0) {
                            $updates['required_amount'] = round((float) ($bill->loan_installment_amount ?: $bill->amount), 2);
                        }
                        if ((float) $installment->amount_paid <= 0) {
                            $paid = min(
                                (float) ($updates['required_amount'] ?? $installment->required_amount),
                                $this->verifiedPaymentsForBill($bill->id) + $this->unlinkedOfficeRepaymentsForBill($bill->id),
                            );
                            if ($paid > 0) {
                                $updates['amount_paid'] = round($paid, 2);
                                $updates['payment_date'] = $this->latestVerifiedPaymentDate($bill->id);
                            }
                        }
                        $installment->forceFill($updates)->save();
                        $installment->syncStatus();
                        $stats['linked']++;
                    }

                    return;
                }

                $number = $this->installmentNumberForExistingBill($loan, $bill);
                if ($number < 1 || $number > (int) $loan->term_months
                    || LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_number', $number)->exists()) {
                    $stats['skipped']++;

                    return;
                }

                $requiredAmount = round((float) ($bill->loan_installment_amount ?: $bill->amount), 2);
                if ($requiredAmount <= 0) {
                    $stats['skipped']++;

                    return;
                }

                $paid = $this->verifiedPaymentsForBill($bill->id)
                    + $this->unlinkedOfficeRepaymentsForBill($bill->id);
                $paid = round(min($requiredAmount, $paid), 2);
                $remaining = round(max(0, $requiredAmount - $paid), 2);
                $installment = LoanInstallment::query()->create([
                    'loan_id' => $loan->id,
                    'bill_id' => $bill->id,
                    'installment_number' => $number,
                    'billing_period' => $bill->billing_period->toDateString(),
                    'due_date' => $bill->due_date?->toDateString(),
                    'required_amount' => $requiredAmount,
                    'amount_paid' => $paid,
                    'remaining_balance' => $remaining,
                    'payment_date' => $paid > 0 ? $this->latestVerifiedPaymentDate($bill->id) : null,
                    'status' => $this->installmentStatus($remaining, $paid, $bill->due_date),
                ]);
                $bill->forceFill(['loan_installment_amount' => $bill->loan_installment_amount ?: $requiredAmount])->save();
                $stats['linked']++;
            });
    }

    private function installmentNumberForExistingBill(Loan $loan, Bill $bill): int
    {
        if ($loan->start_billing_period) {
            $number = Carbon::parse($loan->start_billing_period)->startOfMonth()->diffInMonths(Carbon::parse($bill->billing_period)->startOfMonth()) + 1;
            if ($number >= 1 && $number <= (int) $loan->term_months) {
                return $number;
            }
        }

        return (int) Bill::query()
            ->where('loan_id', $loan->id)
            ->where('bill_type', 'Loan')
            ->where(function ($query) use ($bill) {
                $query->whereDate('billing_period', '<', $bill->billing_period)
                    ->orWhere(fn ($samePeriod) => $samePeriod->whereDate('billing_period', $bill->billing_period)->where('id', '<=', $bill->id));
            })
            ->count();
    }

    private function scheduleIsValid(Loan $loan, mixed $schedule): bool
    {
        $term = (int) $loan->term_months;
        if (! is_array($schedule) || ! in_array($term, [3, 6, 12], true) || count($schedule) !== $term) {
            return false;
        }

        $total = 0.0;
        foreach (array_values($schedule) as $index => $row) {
            if (! is_array($row)
                || (isset($row['month']) && (int) $row['month'] !== $index + 1)
                || ! is_numeric($row['amount'] ?? null)
                || (float) $row['amount'] <= 0) {
                return false;
            }

            $total += (float) $row['amount'];
        }

        return abs(round($total, 2) - round((float) $loan->amount, 2)) < 0.01;
    }

    private function dueDateFor(Loan $loan, int $number, ?LoanInstallment $installment, ?Bill $bill): ?Carbon
    {
        if ($installment?->due_date) {
            return Carbon::parse($installment->due_date);
        }

        if ($bill?->due_date) {
            return Carbon::parse($bill->due_date);
        }

        $anchor = LoanInstallment::query()
            ->where('loan_id', $loan->id)
            ->whereNotNull('due_date')
            ->orderBy('installment_number')
            ->first();
        if ($anchor) {
            if (! $loan->next_due_date && $loan->approved_at && $anchor->billing_period && $loan->start_billing_period) {
                $anchorMonth = $anchor->billing_period->copy()->startOfMonth();
                $approvalDeadline = $anchorMonth->copy()->day(min($loan->approved_at->day, $anchorMonth->daysInMonth));
                if ($anchor->due_date->isSameDay($approvalDeadline)) {
                    $period = Carbon::parse($loan->start_billing_period)->startOfMonth()->addMonthsNoOverflow($number - 1);

                    return $period->day(min($loan->approved_at->day, $period->daysInMonth));
                }
            }

            return Carbon::parse($anchor->due_date)->addMonthsNoOverflow($number - (int) $anchor->installment_number);
        }

        if ($loan->next_due_date) {
            $nextNumber = (int) (LoanInstallment::query()->where('loan_id', $loan->id)->where('remaining_balance', '>', 0)->min('installment_number') ?? 1);

            return Carbon::parse($loan->next_due_date)->addMonthsNoOverflow($number - $nextNumber);
        }

        if (! $loan->approved_at || ! $loan->start_billing_period) {
            return null;
        }

        $period = Carbon::parse($loan->start_billing_period)->startOfMonth()->addMonthsNoOverflow($number - 1);

        return $period->day(min($loan->approved_at->day, $period->daysInMonth));
    }

    private function allocateUnassignedLoanRepayments(Loan $loan): void
    {
        $recordedTotal = round((float) LoanRepayment::query()->where('loan_id', $loan->id)->recordedOrVerified()->sum('amount'), 2);
        $allocatedTotal = round((float) LoanInstallment::query()->where('loan_id', $loan->id)->sum('amount_paid'), 2);
        $unallocated = round(max(0, $recordedTotal - $allocatedTotal), 2);
        if ($unallocated <= 0) {
            return;
        }

        $lastRepaymentDate = LoanRepayment::query()->where('loan_id', $loan->id)->recordedOrVerified()->max('payment_date');
        $installments = LoanInstallment::query()->where('loan_id', $loan->id)
            ->where('remaining_balance', '>', 0)
            ->orderBy('billing_period')->orderBy('installment_number')->orderBy('id')
            ->lockForUpdate()->get();

        foreach ($installments as $installment) {
            if ($unallocated <= 0) {
                break;
            }
            $allocated = round(min($unallocated, (float) $installment->remaining_balance), 2);
            if ($allocated <= 0) {
                continue;
            }
            $installment->forceFill([
                'amount_paid' => round((float) $installment->amount_paid + $allocated, 2),
                'payment_date' => $lastRepaymentDate ?? $installment->payment_date,
            ])->syncStatus();
            $unallocated = round($unallocated - $allocated, 2);
        }
    }

    private function reconcileVerifiedLoanPayments(Loan $loan): bool
    {
        $paid = round((float) LoanInstallment::query()->where('loan_id', $loan->id)->sum('amount_paid'), 2);
        $recorded = round((float) LoanRepayment::query()->where('loan_id', $loan->id)->recordedOrVerified()->sum('amount'), 2);
        $difference = round($paid - $recorded, 2);
        $payments = Payment::query()->where('consumer_id', $loan->consumer_id)
            ->where('payment_type', 'Loan')->where('status', 'Verified')
            ->whereHas('bill', fn ($bill) => $bill->where('loan_id', $loan->id)->where('bill_type', 'Loan'))
            ->whereDoesntHave('loanRepayment')->orderBy('payment_date')->orderBy('id')->lockForUpdate()->get();
        if ($payments->isEmpty()) {
            return true;
        }
        if (round((float) $payments->sum('amount'), 2) !== $difference) {
            return false;
        }

        foreach ($payments as $payment) {
            LoanRepayment::query()->firstOrCreate(['payment_id' => $payment->id], [
                'loan_id' => $loan->id,
                'bill_id' => $payment->bill_id,
                'recorded_by_user_id' => $payment->verified_by,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'notes' => 'Reconciled verified loan payment '.$payment->reference_number,
            ]);
        }

        return true;
    }

    private function refreshLoanBillStatuses(Loan $loan): void
    {
        LoanInstallment::query()->with('bill')->where('loan_id', $loan->id)->get()->each(function (LoanInstallment $installment): void {
            if ($installment->bill && $installment->bill->bill_type === 'Loan') {
                $installment->bill->syncPaymentStatus(false);
            }
        });
    }

    private function verifiedPaymentsForBill(int $billId): float
    {
        return round((float) Payment::query()->where('bill_id', $billId)
            ->where('payment_type', 'Loan')->where('status', 'Verified')->sum('amount'), 2);
    }

    private function unlinkedOfficeRepaymentsForBill(int $billId): float
    {
        return round((float) LoanRepayment::query()->where('bill_id', $billId)
            ->whereNull('payment_id')->sum('amount'), 2);
    }

    private function latestVerifiedPaymentDate(int $billId): ?string
    {
        return Payment::query()->where('bill_id', $billId)
            ->where('payment_type', 'Loan')->where('status', 'Verified')
            ->orderByDesc('payment_date')->value('payment_date');
    }

    private function installmentStatus(float $remaining, float $paid, mixed $dueDate): string
    {
        return match (true) {
            $remaining <= 0 => 'Paid',
            $paid > 0 => 'Partially Paid',
            $dueDate && Carbon::parse($dueDate)->lt(today()) => 'Overdue',
            default => 'Billed',
        };
    }
}
