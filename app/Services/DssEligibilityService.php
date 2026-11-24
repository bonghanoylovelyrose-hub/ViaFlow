<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Consumer;
use App\Models\Loan;

class DssEligibilityService
{
    /** Window used to detect recent bad payment records. */
    private const RECORD_LOOKBACK_MONTHS = 6;

    public const MEMBERS_ONLY_MESSAGE = 'Loan services are available to Members only.';

    public const INELIGIBLE_MESSAGE = 'Your loan application is Not Eligible based on the automatic eligibility assessment.';

    public const ACTIVE_LOAN_MESSAGE = 'You currently have an active loan. You can apply for another loan only after your existing loan has been fully paid.';

    public const NOT_VERIFIED_MESSAGE = 'Your consumer account must be verified and activated by the administrator before you can apply for a meter loan.';

    public const OUTSTANDING_BALANCE_MESSAGE = 'You have an overdue water balance or an unpaid previous loan. Fully settle these balances before applying for a meter loan.';

    public const DELAYED_PAYMENT_MESSAGE = 'You have a delayed or late payment record within the last six months. This disqualifies you from applying for a meter loan.';

    /**
     * Every unmet requirement, so the applicant is always told exactly why.
     */
    public function ineligibilityReasons(?Consumer $consumer): array
    {
        $eligibility = $this->loanEligibility($consumer);

        return collect($eligibility['reasons'])
            ->filter(fn (array $reason): bool => ! $reason['passed'])
            ->map(fn (array $reason): string => $reason['text'])
            ->values()
            ->all();
    }

    public function loanEligibility(?Consumer $consumer, ?int $excludeLoanId = null): array
    {
        if (! $consumer) {
            return $this->result([
                $this->reason(false, 'No consumer profile is linked to this account.', 'Ask ViaFlow to link your active consumer account before applying.'),
            ], $this->emptyMetrics());
        }

        $sixMonthsAgo = now()->subMonths(self::RECORD_LOOKBACK_MONTHS)->startOfDay();
        $bills = $consumer->bills()->where('bill_type', 'Water')
            ->with(['payments' => fn ($query) => $query->where('status', 'Verified')->where('payment_type', 'Water')->orderBy('payment_date')])
            ->get();
        $recentBills = $bills->filter(fn (Bill $bill) => $bill->due_date && $bill->due_date->gte($sixMonthsAgo) && $bill->due_date->lte(today()));
        $lateBills = $bills->filter(fn (Bill $bill) => $bill->due_date && $bill->payments->contains(
            fn ($payment) => $payment->payment_date && $payment->payment_date->gte($sixMonthsAgo)
                && $payment->payment_date->gt($bill->due_date) && $payment->payment_date->lte(today())
        ));
        $latePaymentRecords = $lateBills->count();
        $latePaymentHistory = $lateBills->map(fn (Bill $bill): array => [
            'bill_id' => $bill->id,
            'billing_period' => $bill->billing_period?->format('Y-m'),
            'due_date' => $bill->due_date?->toDateString(),
            'late_payments' => $bill->payments->filter(fn ($payment) => $payment->payment_date && $payment->payment_date->gte($sixMonthsAgo)
                && $payment->payment_date->gt($bill->due_date) && $payment->payment_date->lte(today()))
                ->map(fn ($payment): array => ['payment_id' => $payment->id, 'payment_date' => $payment->payment_date->toDateString(), 'days_late' => (int) $bill->due_date->diffInDays($payment->payment_date), 'amount' => (float) $payment->amount])->values()->all(),
        ])->values()->all();
        $recentBillsWithPayments = $bills->filter(fn (Bill $bill) => $bill->payments->contains(fn ($payment) => $payment->payment_date && $payment->payment_date->gte($sixMonthsAgo) && $payment->payment_date->lte(today())));
        $onTimePayments = $recentBillsWithPayments->filter(fn (Bill $bill) => ! $lateBills->contains('id', $bill->id))->count();
        $unpaidBillBalance = round((float) $bills->sum(fn (Bill $bill) => $bill->balance_due), 2);
        $unpaidBills = $bills->filter(fn (Bill $bill) => $bill->balance_due > 0)->count();
        $balanceService = app(ConsumerBalanceService::class);
        $portionGroups = $balanceService->remainingBillPortionsFor($consumer->id);
        $remainingByBill = $portionGroups ? array_replace([], ...array_values($portionGroups)) : [];
        $overdueBillRecords = $bills->filter(fn (Bill $bill) => ($remainingByBill[$bill->id] ?? 0) > 0 && $balanceService->isOverdue($bill));
        $overdueBills = $overdueBillRecords->count();
        $overdueWaterBalance = round((float) $overdueBillRecords->sum(fn (Bill $bill) => $remainingByBill[$bill->id]), 2);
        $overdueMonths = $overdueBillRecords->pluck('billing_period')->filter()->map(fn ($date) => $date->format('Y-m'))->unique()->count();
        $overdueHistory = $overdueBillRecords->map(fn (Bill $bill): array => [
            'bill_id' => $bill->id, 'billing_period' => $bill->billing_period?->format('Y-m'),
            'due_date' => $bill->due_date?->toDateString(), 'amount' => round((float) $remainingByBill[$bill->id], 2),
            'days_overdue' => (int) $balanceService->overdueDateFor($bill)->diffInDays(today()),
        ])->values()->all();

        $loans = $consumer->loans()->with('repayments')->get()
            ->reject(fn (Loan $loan): bool => $excludeLoanId !== null && $loan->id === $excludeLoanId)
            ->values();
        $paymentRecords = $consumer->payments()->count();
        $verifiedPaymentRecords = $consumer->payments()->where('status', 'Verified')->count();
        $hasExistingHistory = $verifiedPaymentRecords > 0 || $loans->isNotEmpty();
        $isNewConsumer = ! $hasExistingHistory;
        $blockingLoans = $loans->filter(fn (Loan $loan) => $loan->blocksNewApplication());
        $activeLoans = $loans->filter(fn (Loan $loan) => in_array($loan->status, Loan::ACTIVE_STATUSES, true));
        $overdueLoans = $loans->filter(fn (Loan $loan) => $loan->status === 'Approved'
            && $loan->balance_remaining > 0
            && $loan->next_due_date?->isPast());
        $unpaidLoanBalance = round((float) $loans
            ->reject(fn (Loan $loan) => $loan->status === 'Rejected')
            ->sum(fn (Loan $loan) => $loan->balance_remaining), 2);

        $isVerified = $consumer->isVerified();
        // A settled account that previously fell into disconnection rests in
        // "For Reconnection" while it waits for the association's procedure.
        // That is a good-standing state for eligibility purposes; only an
        // account whose supply is still actively disconnected is blocked.
        $hasActiveConnection = $consumer->status !== 'Disconnected' && filled($consumer->account_number);
        $hasOutstandingBalance = $overdueWaterBalance > 0 || $unpaidLoanBalance > 0;
        $hasLatePayments = $latePaymentRecords > 0;

        // Single source of truth: the consumer page explains these rules and
        // the saved DSS evaluation records their measured values and outcomes.
        $reasons = [
            $this->reason(
                $isVerified,
                $isVerified
                    ? 'Your consumer account is verified and activated.'
                    : self::NOT_VERIFIED_MESSAGE,
                'Have the administrator verify and activate your consumer account.'
            ),
            $this->reason(
                ! $hasLatePayments,
                ! $hasLatePayments
                    ? ($isNewConsumer
                        ? 'Late-payment history check is not applicable because this new consumer has no payment records.'
                        : 'No delayed or late water-bill payments in the last six months.')
                    : self::DELAYED_PAYMENT_MESSAGE
                        ." Found {$latePaymentRecords} delayed or late payment record(s) within the last six months.",
                'Maintain six consecutive months without a delayed or late payment.'
            ),
            $this->reason(
                ! $hasOutstandingBalance,
                ! $hasOutstandingBalance
                    ? 'No overdue water balance or unpaid previous loan.'
                    : self::OUTSTANDING_BALANCE_MESSAGE
                        .' Overdue water bills P '.number_format($overdueWaterBalance, 2)
                        .' across '.$overdueMonths.' billing month(s), and previous loans P '.number_format($unpaidLoanBalance, 2).'.',
                'Fully settle overdue water-bill and previous-loan balances.'
            ),
            $this->reason(
                $blockingLoans->isEmpty() && $overdueLoans->isEmpty(),
                $blockingLoans->isEmpty() && $overdueLoans->isEmpty()
                    ? 'No active or overdue loan.'
                    : self::ACTIVE_LOAN_MESSAGE,
                'Complete or settle the existing loan before submitting another application.'
            ),
            $this->reason(
                $hasActiveConnection,
                $hasActiveConnection
                    ? 'Active water connection.'
                    : 'The water connection is not active.',
                'Restore the water connection before applying for a meter loan.'
            ),
        ];

        return $this->result($reasons, [
            'billing_records' => $bills->count(),
            'recent_billing_records' => $recentBills->count(),
            'consumption_records' => $consumer->meterReadings()->count(),
            'recent_consumption_records' => $consumer->meterReadings()->where('reading_date', '>=', $sixMonthsAgo)->count(),
            'verified_payments' => $verifiedPaymentRecords,
            'payment_records' => $paymentRecords,
            'recent_paid_bills' => $recentBills->filter(fn (Bill $bill) => $bill->balance_due <= 0)->count(),
            'recent_on_time_payments' => $onTimePayments,
            'unpaid_bills' => $unpaidBills,
            'recent_unpaid_bills' => $recentBills->filter(fn (Bill $bill) => $bill->balance_due > 0)->count(),
            'overdue_bills' => $overdueBills,
            'recent_overdue_bills' => $overdueBillRecords->filter(fn (Bill $bill) => $bill->due_date->gte($sixMonthsAgo))->count(),
            'unpaid_balance' => $unpaidBillBalance,
            'overdue_water_balance' => $overdueWaterBalance,
            'overdue_water_months' => $overdueMonths,
            'overdue_water_history' => $overdueHistory,
            'late_payment_history' => $latePaymentHistory,
            'recent_unpaid_balance' => round((float) $recentBills->sum(fn (Bill $bill) => $bill->balance_due), 2),
            'payment_reliability' => $recentBillsWithPayments->isEmpty() ? 100.0 : round(($onTimePayments / $recentBillsWithPayments->count()) * 100, 1),
            'recurring_late_payments' => $latePaymentRecords,
            'unresolved_violations' => 0,
            'active_loans' => $activeLoans->count(),
            'overdue_loans' => $overdueLoans->count(),
            'unpaid_loan_balance' => $unpaidLoanBalance,
            'is_verified' => $isVerified,
            'is_new_consumer' => ! $hasExistingHistory,
        ]);
    }

    private function reason(bool $passed, string $text, string $requirement): array
    {
        return compact('passed', 'text', 'requirement');
    }

    private function result(array $reasons, array $metrics): array
    {
        $eligible = collect($reasons)->every(fn (array $reason) => $reason['passed']);

        return [
            'status' => $eligible ? 'Eligible' : 'Not Eligible',
            'eligible' => $eligible,
            'reasons' => $reasons,
            'summary' => $eligible
                ? 'Loan eligibility result: Eligible. All account, billing, and loan-standing requirements are satisfied.'
                : self::INELIGIBLE_MESSAGE,
            'metrics' => $metrics,
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'billing_records' => 0,
            'recent_billing_records' => 0,
            'consumption_records' => 0,
            'recent_consumption_records' => 0,
            'verified_payments' => 0,
            'payment_records' => 0,
            'recent_paid_bills' => 0,
            'recent_on_time_payments' => 0,
            'unpaid_bills' => 0,
            'recent_unpaid_bills' => 0,
            'overdue_bills' => 0,
            'recent_overdue_bills' => 0,
            'unpaid_balance' => 0.0,
            'overdue_water_balance' => 0.0,
            'overdue_water_months' => 0,
            'overdue_water_history' => [],
            'late_payment_history' => [],
            'recent_unpaid_balance' => 0.0,
            'payment_reliability' => 0.0,
            'recurring_late_payments' => 0,
            'unresolved_violations' => 0,
            'active_loans' => 0,
            'overdue_loans' => 0,
            'unpaid_loan_balance' => 0.0,
            'is_verified' => false,
            'is_new_consumer' => false,
        ];
    }
}
