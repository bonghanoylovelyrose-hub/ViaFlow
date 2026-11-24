<?php

namespace App\Services;

use App\Models\Consumer;
use App\Models\Loan;

class DisconnectedAccountService
{
    public function __construct(
        private readonly ConsumerStatusService $statuses,
        private readonly ConsumerBalanceService $balances,
        private readonly LoanRepaymentAllocationService $loanRepayments,
    ) {}

    public function isRestricted(?Consumer $consumer): bool
    {
        return $consumer !== null && $this->statuses->key($consumer) === ConsumerStatusService::INACTIVE;
    }

    public function summary(Consumer $consumer): array
    {
        return [
            'consumer_name' => $consumer->full_name,
            'account_number' => $consumer->account_number,
            'meter_number' => $consumer->meter_number,
            'account_status' => 'Inactive / Disconnected',
            ...$this->balanceBreakdown($consumer),
        ];
    }

    public function balanceBreakdown(Consumer $consumer): array
    {
        $waterBalance = $this->balances->recordedOutstandingFor($consumer->id, includeDemo: false);
        $loanBalance = $consumer->loans()->whereIn('status', Loan::PAYMENT_STATUSES)
            ->where('amount', '>', 0)->get()->sum(fn (Loan $loan) => $this->loanRepayments->remainingBalance($loan));

        return [
            'outstanding_water_balance' => $waterBalance,
            'outstanding_loan_balance' => round((float) $loanBalance, 2),
            'total_amount_to_pay' => round($waterBalance + $loanBalance, 2),
        ];
    }
}
