<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(
        private readonly ConsumerBalanceService $balances,
        private readonly ConsumerStatusService $statuses,
    ) {
    }

    /**
 * Outstanding bills in the order money is applied: oldest period first.
 */
    public function payableBills(int $consumerId): Collection
    {
        return $this->balances->openBillsFor($consumerId);
    }

    public function totalOutstanding(int $consumerId): float
    {
        return $this->balances->totalOutstandingFor($consumerId);
    }

    public function requiresFullPayment(int $consumerId): bool
    {
        return $this->balances->hasWaterDisconnectionNotice($consumerId)
            || $this->hasInactiveServiceStatus($consumerId);
    }

    public function validateAmount(int $consumerId, float $amount): void
    {
        $amount = round($amount, 2);
        $totalOutstanding = $this->totalOutstanding($consumerId);

        if ($this->requiresFullPayment($consumerId) && $amount < $totalOutstanding) {
            throw ValidationException::withMessages([
                'amount' => $this->fullPaymentRequiredMessage($totalOutstanding),
            ]);
        }
    }

    public function fullPaymentRequiredMessage(float $amount): string
    {
        return 'Full payment is required for this account because it is inactive/disconnected or has an active disconnection notice. Partial payment is not allowed. Required Payment Amount: PHP '.number_format($amount, 2).'.';
    }

    private function hasInactiveServiceStatus(int $consumerId): bool
    {
        $consumer = Consumer::query()->find($consumerId);

        return $consumer !== null
            && $this->statuses->key($consumer) === ConsumerStatusService::INACTIVE;
    }

    /**
     * Spreads a verified payment across outstanding bills oldest-first.
     *
     * The oldest unpaid period absorbs the money first, any remainder moves on to
     * the next period, and whatever is still unpaid afterwards stays on the latest
     * bill so it can be carried into the next billing cycle.
     *
     * Each peso lives on exactly one bill - a balance carried into a later bill is
     * closed at that point - so a payment can never be counted against the same
     * debt twice.
     */
    public function allocateVerifiedFullPayment(
        Payment $primaryPayment,
        User $verifier,
        ?string $receiptNumber = null,
    ): Collection {
        $linkedBill = $primaryPayment->bill()->firstOrFail();
        $billType = $linkedBill->bill_type;
        $paymentType = $primaryPayment->payment_type ?: $billType;
        if ($paymentType === 'Water' && $billType === 'Water' && $primaryPayment->payment_type !== 'Water') {
            $primaryPayment->forceFill(['payment_type' => 'Water'])->save();
        }
        if ($paymentType !== 'Water' || $billType !== 'Water' || $linkedBill->consumer_id !== $primaryPayment->consumer_id) {
            throw ValidationException::withMessages(['payment_type' => 'Water payments can only be allocated to water bills.']);
        }

        $primaryPayment->forceFill([
            'status' => 'Pending',
            'verified_at' => null,
            'verified_by' => null,
        ])->save();

        $payableBills = $this->payableBills($primaryPayment->consumer_id);
        $remainingAmount = round((float) $primaryPayment->amount, 2);
        $payments = collect();

        foreach ($payableBills as $index => $bill) {
            if ($remainingAmount <= 0) {
                break;
            }

            $billBalance = round((float) $bill->balance_due, 2);
            $allocatedAmount = round(min($remainingAmount, $billBalance), 2);

            if ($allocatedAmount <= 0) {
                continue;
            }

            $payment = $index === 0 ? $primaryPayment : $primaryPayment->replicate([
                'id',
                'reference_number',
                'receipt_number',
                'gateway_reference',
                'created_at',
                'updated_at',
            ]);

            $payment->forceFill([
                'bill_id' => $bill->id,
                'consumer_id' => $bill->consumer_id,
                'reference_number' => $index === 0
                    ? $primaryPayment->reference_number
                    : $this->generateReferenceNumber(),
                'receipt_number' => $index === 0
                    ? ($receiptNumber ?: $primaryPayment->receipt_number)
                    : $this->generateReceiptNumber(),
                'amount' => $allocatedAmount,
                'status' => 'Verified',
                'verified_at' => now(),
                'verified_by' => $verifier->id,
            ])->save();

            $payment->refresh();
            $payments->push($payment);

            $bill->refresh()->loadSum([
                'payments as verified_payments_sum_amount' => fn ($query) => $query->where('status', 'Verified'),
            ], 'amount');
            $bill->syncPaymentStatus(false);

            $remainingAmount = round($remainingAmount - $allocatedAmount, 2);
        }

        $primaryPayment->consumer?->syncAccountStanding();

        return $payments;
    }

    public function allocateVerifiedLoanPayment(Payment $payment, User $verifier): LoanRepayment
    {
        if ($payment->status !== 'Verified') {
            throw ValidationException::withMessages(['status' => 'Only verified loan payments can be allocated.']);
        }
        $bill = $payment->bill()->with('loanInstallment.loan')->first();
        $loan = $payment->loan_id ? $payment->loan()->first() : $bill?->loanInstallment?->loan;
        if ($payment->payment_type !== 'Loan' || ! $loan
            || (int) $loan->consumer_id !== (int) $payment->consumer_id
            || ($bill && ($bill->bill_type !== 'Loan' || (int) $bill->consumer_id !== (int) $payment->consumer_id
                || (int) $bill->loanInstallment?->loan_id !== (int) $loan->id))) {
            throw ValidationException::withMessages(['bill_id' => 'This payment does not belong to a loan installment for the selected consumer.']);
        }

        return DB::transaction(function () use ($loan, $payment, $verifier, $bill): LoanRepayment {
            $consumer = Consumer::query()->whereKey($payment->consumer_id)->lockForUpdate()->firstOrFail();

            $repayment = app(LoanRepaymentAllocationService::class)->record(
                $loan, (float) $payment->amount, $payment->payment_date->toDateString(),
                $verifier, $payment, $bill, 'Allocated from verified payment '.$payment->reference_number,
            );
            if ($consumer->disconnectionNotices()->active()->where('source', \App\Models\DisconnectionNotice::SOURCE_LOAN)->exists()) {
                app(DisconnectionNoticeService::class)->reconcileLoanNotices($consumer);
            }

            return $repayment;
        });
    }

    public function syncVerifiedLoanRepayment(Payment $payment): void
    {
        // Retained for compatibility with older callers; water bill payments
        // must never be interpreted as loan repayments.
        if ($payment->payment_type !== 'Loan') {
            return;
        }

        if ($payment->status !== 'Verified') {
            return;
        }

        $bill = $payment->bill()
            ->with('loan')
            ->first();

        if (! $bill || ! $bill->loan || (float) ($bill->loan_installment_amount ?? 0) <= 0) {
            if (! $bill) {
                return;
            }
        }

        if (Schema::hasTable('loan_installments')) {
            $this->syncVerifiedInstallmentRepayment($payment, $bill);
            return;
        }

        if (! $bill->loan || (float) ($bill->loan_installment_amount ?? 0) <= 0) {
            return;
        }

        $loanInstallment = round((float) $bill->loan_installment_amount, 2);
        $nonLoanBillAmount = round(max(0, (float) $bill->amount - $loanInstallment), 2);
        $paidBefore = round((float) Payment::query()
            ->where('bill_id', $bill->id)
            ->where('status', 'Verified')
            ->where(function ($query) use ($payment) {
                $query
                    ->whereDate('payment_date', '<', $payment->payment_date)
                    ->orWhere(function ($sameDateQuery) use ($payment) {
                        $sameDateQuery
                            ->whereDate('payment_date', $payment->payment_date)
                            ->where('id', '<', $payment->id);
                    });
            })
            ->sum('amount'), 2);

        $paidThrough = round($paidBefore + (float) $payment->amount, 2);
        $loanPaidBefore = round(min($loanInstallment, max(0, $paidBefore - $nonLoanBillAmount)), 2);
        $loanPaidThrough = round(min($loanInstallment, max(0, $paidThrough - $nonLoanBillAmount)), 2);
        $loanPaymentAmount = round($loanPaidThrough - $loanPaidBefore, 2);

        if ($loanPaymentAmount <= 0) {
            LoanRepayment::query()->where('payment_id', $payment->id)->delete();
            return;
        }

        LoanRepayment::query()->updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'loan_id' => $bill->loan_id,
                'bill_id' => $bill->id,
                'recorded_by_user_id' => $payment->verified_by,
                'amount' => $loanPaymentAmount,
                'payment_date' => $payment->payment_date,
                'notes' => 'Allocated from verified bill payment '.$payment->reference_number,
            ],
        );

        $bill->loan->refresh()->loadSum('repayments', 'amount');
        $bill->loan->syncRepaymentStatus();
    }

    private function syncVerifiedInstallmentRepayment(Payment $payment, Bill $bill): void
    {
        if (LoanRepayment::query()->where('payment_id', $payment->id)->exists()) {
            return;
        }

        $loanDue = round((float) LoanInstallment::query()
            ->whereHas('loan', fn ($query) => $query->where('consumer_id', $bill->consumer_id))
            ->whereDate('billing_period', '<=', $bill->billing_period?->toDateString() ?? $bill->billing_period)
            ->sum('remaining_balance'), 2);

        if ($loanDue <= 0) {
            LoanRepayment::query()->where('payment_id', $payment->id)->delete();
            return;
        }

        $nonLoanBillAmount = round(max(0, (float) $bill->amount - $loanDue), 2);
        $paidBefore = round((float) Payment::query()
            ->where('bill_id', $bill->id)
            ->where('status', 'Verified')
            ->where(function ($query) use ($payment) {
                $query
                    ->whereDate('payment_date', '<', $payment->payment_date)
                    ->orWhere(function ($sameDateQuery) use ($payment) {
                        $sameDateQuery
                            ->whereDate('payment_date', $payment->payment_date)
                            ->where('id', '<', $payment->id);
                    });
            })
            ->sum('amount'), 2);

        $paidThrough = round($paidBefore + (float) $payment->amount, 2);
        $loanPaidBefore = round(min($loanDue, max(0, $paidBefore - $nonLoanBillAmount)), 2);
        $loanPaidThrough = round(min($loanDue, max(0, $paidThrough - $nonLoanBillAmount)), 2);
        $loanPaymentAmount = round($loanPaidThrough - $loanPaidBefore, 2);

        if ($loanPaymentAmount <= 0) {
            LoanRepayment::query()->where('payment_id', $payment->id)->delete();
            return;
        }

        $remainingPayment = $loanPaymentAmount;
        $loanIds = [];

        $installments = LoanInstallment::query()
            ->with('loan')
            ->whereHas('loan', fn ($query) => $query->where('consumer_id', $bill->consumer_id))
            ->whereDate('billing_period', '<=', $bill->billing_period?->toDateString() ?? $bill->billing_period)
            ->where('remaining_balance', '>', 0)
            ->orderBy('billing_period')
            ->orderBy('installment_number')
            ->orderBy('id')
            ->get();

        foreach ($installments as $installment) {
            if ($remainingPayment <= 0) {
                break;
            }

            $allocated = round(min($remainingPayment, (float) $installment->remaining_balance), 2);

            if ($allocated <= 0) {
                continue;
            }

            $installment->forceFill([
                'amount_paid' => round((float) $installment->amount_paid + $allocated, 2),
                'payment_date' => $payment->payment_date,
            ]);
            $installment->syncStatus();

            $loanIds[] = $installment->loan_id;
            $remainingPayment = round($remainingPayment - $allocated, 2);
        }

        $loanId = $loanIds[0] ?? $bill->loan_id;

        if ($loanId) {
            LoanRepayment::query()->updateOrCreate(
                ['payment_id' => $payment->id],
                [
                    'loan_id' => $loanId,
                    'bill_id' => $bill->id,
                    'recorded_by_user_id' => $payment->verified_by,
                    'amount' => $loanPaymentAmount,
                    'payment_date' => $payment->payment_date,
                    'notes' => 'Allocated from verified bill payment '.$payment->reference_number,
                ],
            );
        }

        collect($loanIds)
            ->unique()
            ->each(function (int $loanId): void {
                Loan::query()->find($loanId)?->syncRepaymentStatus();
            });
    }

    public function generateReferenceNumber(): string
    {
        $nextId = (Payment::max('id') ?? 0) + 1;

        do {
            $reference = sprintf('PAY-%04d', $nextId);
            $nextId++;
        } while (Payment::query()->where('reference_number', $reference)->exists());

        return $reference;
    }

    public function generateReceiptNumber(): string
    {
        $nextId = (Payment::whereNotNull('receipt_number')->max('id') ?? 0) + 1;

        do {
            $receiptNumber = sprintf('OR-%s-%04d', now()->format('ymd'), $nextId);
            $nextId++;
        } while (Payment::query()->where('receipt_number', $receiptNumber)->exists());

        return $receiptNumber;
    }
}
