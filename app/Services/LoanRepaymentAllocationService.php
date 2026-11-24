<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoanRepaymentAllocationService
{
    public function remainingBalance(Loan $loan): float
    {
        $recorded = (float) $loan->repayments()->recordedOrVerified()->sum('amount');
        $allocated = (float) $loan->installments()->sum('amount_paid');

        return max(0, $this->cents($loan->amount) - max($this->cents($recorded), $this->cents($allocated))) / 100;
    }

    /** The caller must wrap this method in a transaction and lock the consumer first. */
    public function record(Loan $loan, float $amount, string $date, User $verifier, ?Payment $payment = null, ?Bill $bill = null, ?string $notes = null): LoanRepayment
    {
        $loan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
        if ($payment && ($existing = LoanRepayment::query()->where('payment_id', $payment->id)->first())) {
            return $existing;
        }
        if (! in_array($loan->status, Loan::PAYMENT_STATUSES, true)) {
            throw ValidationException::withMessages(['amount' => 'Only approved loans can accept payments.']);
        }
        $before = $this->cents($this->remainingBalance($loan));
        $paymentCents = $this->cents($amount);
        if ($paymentCents <= 0 || $paymentCents > $before) {
            throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining loan balance of P '.number_format($before / 100, 2).'.']);
        }

        $installments = $this->prepareInstallments($loan);
        $recorded = $this->cents($loan->repayments()->recordedOrVerified()->sum('amount'));
        $allocated = $this->cents($installments->sum('amount_paid'));
        if ($recorded !== $allocated) {
            throw ValidationException::withMessages(['amount' => 'Existing loan payments must be reconciled before another payment can be allocated.']);
        }
        if ($paymentCents > $this->cents($installments->sum('remaining_balance'))) {
            throw ValidationException::withMessages(['amount' => 'The saved installment schedule is incomplete. Ask an administrator to review it before paying ahead.']);
        }

        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'bill_id' => $bill?->id,
            'payment_id' => $payment?->id,
            'recorded_by_user_id' => $verifier->id,
            'amount' => $paymentCents / 100,
            'payment_date' => $date,
            'notes' => $notes,
            'loan_balance_before' => $before / 100,
            'loan_balance_after' => ($before - $paymentCents) / 100,
        ]);

        $remaining = $paymentCents;
        foreach ($installments as $installment) {
            if ($remaining === 0) {
                break;
            }
            $applied = min($remaining, $this->cents($installment->remaining_balance));
            if ($applied <= 0) {
                continue;
            }
            $installment->forceFill([
                'amount_paid' => ($this->cents($installment->amount_paid) + $applied) / 100,
                'payment_date' => $date,
            ])->syncStatus();
            $repayment->allocations()->create([
                'loan_installment_id' => $installment->id,
                'amount' => $applied / 100,
                'installment_balance_after' => $installment->remaining_balance,
            ]);
            if ($installment->bill_id) {
                $installment->bill?->refresh()->syncPaymentStatus(false);
            }
            $remaining -= $applied;
        }
        $next = $installments->first(fn (LoanInstallment $item) => $this->cents($item->remaining_balance) > 0);
        $updates = ['next_due_date' => $next?->due_date?->toDateString()];
        if ($before === $paymentCents && $loan->status !== 'Ready for Scheduling') {
            $updates['status'] = 'Completed';
        }
        $loan->forceFill($updates)->save();

        return $repayment;
    }

    public function paymentLimit(Loan $loan): float
    {
        $existing = $loan->installments()->with('bill')->orderBy('installment_number')->get();
        if (! $this->recordsAreConsistent($loan, $existing)
            || $this->cents($loan->repayments()->recordedOrVerified()->sum('amount')) !== $this->cents($existing->sum('amount_paid'))) {
            return 0.0;
        }

        $capacity = $this->scheduleAmounts($loan, $existing) !== null
            ? $this->remainingBalance($loan)
            : (float) $existing->sum('remaining_balance');

        return min($this->remainingBalance($loan), $capacity);
    }

    private function prepareInstallments(Loan $loan): Collection
    {
        $existing = $loan->installments()->with('bill')->orderBy('installment_number')->lockForUpdate()->get();
        if (! $this->recordsAreConsistent($loan, $existing)) {
            throw ValidationException::withMessages(['amount' => 'This loan has inconsistent installment records and requires administrator review.']);
        }
        $amounts = $this->scheduleAmounts($loan, $existing);
        if ($amounts !== null) {
            $start = Carbon::parse($loan->start_billing_period)->startOfMonth();
            foreach ($amounts as $number => $amount) {
                if ($existing->contains('installment_number', $number)) {
                    continue;
                }
                $existing->push(LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $number,
                    'billing_period' => $start->copy()->addMonthsNoOverflow($number - 1)->toDateString(),
                    'required_amount' => $amount / 100,
                    'amount_paid' => 0,
                    'remaining_balance' => $amount / 100,
                    'status' => 'Upcoming',
                ]));
            }
        }

        return $existing->sortBy('installment_number')->values();
    }

    private function recordsAreConsistent(Loan $loan, Collection $existing): bool
    {
        foreach ($existing as $index => $item) {
            $required = $this->cents($item->required_amount);
            $paid = $this->cents($item->amount_paid);
            if ($required <= 0 || $paid < 0 || $paid > $required
                || $this->cents($item->remaining_balance) !== $required - $paid
                || (int) $item->installment_number !== $index + 1
                || ($item->bill_id && (! $item->bill || $item->bill->bill_type !== 'Loan'
                    || (int) $item->bill->consumer_id !== (int) $loan->consumer_id
                    || (int) $item->bill->loan_id !== (int) $loan->id))) {
                return false;
            }
        }

        return $this->cents($existing->sum('required_amount')) <= $this->cents($loan->amount);
    }

    private function scheduleAmounts(Loan $loan, Collection $existing): ?array
    {
        $term = (int) $loan->term_months;
        $schedule = $loan->installment_schedule_snapshot;
        if (! $loan->start_billing_period || ! in_array($term, [3, 6, 12], true)
            || ! is_array($schedule) || count($schedule) !== $term) {
            return null;
        }
        $amounts = [];
        foreach (array_values($schedule) as $index => $row) {
            if (! is_array($row) || (isset($row['month']) && (int) $row['month'] !== $index + 1)
                || ! is_numeric($row['amount'] ?? null) || $this->cents($row['amount']) <= 0) {
                return null;
            }
            $amounts[$index + 1] = $this->cents($row['amount']);
        }
        if (array_sum($amounts) !== $this->cents($loan->amount)) {
            return null;
        }
        $start = Carbon::parse($loan->start_billing_period)->startOfMonth();
        foreach ($existing as $item) {
            $number = (int) $item->installment_number;
            if (! isset($amounts[$number]) || $amounts[$number] !== $this->cents($item->required_amount)
                || ! $item->billing_period->isSameDay($start->copy()->addMonthsNoOverflow($number - 1))) {
                return null;
            }
        }
        $bills = Bill::query()->where('loan_id', $loan->id)->get();
        if ($bills->contains(fn (Bill $bill) => ! $existing->contains('bill_id', $bill->id))) {
            return null;
        }

        return $amounts;
    }

    private function cents(float|int|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
