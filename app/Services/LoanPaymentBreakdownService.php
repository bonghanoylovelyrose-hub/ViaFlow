<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Support\Facades\Schema;

class LoanPaymentBreakdownService
{
    public function forLoan(Loan $loan): array
    {
        if (Schema::hasTable('loan_installments')) {
            $loan->loadMissing('installments');
        }
        $records = $loan->relationLoaded('installments') ? $loan->installments->keyBy('installment_number') : collect();
        $term = (int) $loan->term_months;
        $category = $loan->applicationStatusCategory();
        $scheduleState = match (true) {
            $category === 'Rejected' => 'rejected',
            $category !== 'Approved' => 'pending',
            ! $loan->approved_at => 'missing_approval',
            ! in_array($term, [3, 6, 12], true) => 'invalid_term',
            default => 'available',
        };
        $firstMonth = $scheduleState === 'available' ? $loan->approved_at->copy()->startOfMonth()->addMonthNoOverflow() : null;
        $snapshot = $loan->installment_schedule_snapshot;
        $plan = [];
        if (in_array($term, [3, 6, 12], true) && is_array($snapshot) && count($snapshot) === $term) {
            foreach (array_values($snapshot) as $index => $row) {
                if (! is_array($row) || (isset($row['month']) && (int) $row['month'] !== $index + 1)
                    || ! is_numeric($row['amount'] ?? null) || (float) $row['amount'] <= 0) {
                    $plan = [];
                    break;
                }
                $plan[$index + 1] = round((float) $row['amount'], 2);
            }
            if (count($plan) !== $term || abs(round(array_sum($plan), 2) - round((float) $loan->amount, 2)) >= 0.01) {
                $plan = [];
            }
        }

        $rows = [];
        $recordMismatch = false;
        $periodMismatch = false;
        if ($scheduleState === 'available') {
            for ($number = 1; $number <= $term; $number++) {
                $record = $records->get($number);
                $month = $firstMonth->copy()->addMonthsNoOverflow($number - 1);
                if ($record?->billing_period && $record->billing_period->format('Y-m') !== $month->format('Y-m')) {
                    $periodMismatch = true;
                }
                if ($record && isset($plan[$number]) && abs((float) $record->required_amount - $plan[$number]) >= 0.01) {
                    $recordMismatch = true;
                }
                $rows[] = [
                    'number' => $number,
                    'month' => $month->format('F Y'),
                    'due_date' => $record?->due_date,
                    'amount_due' => $record ? $record->required_amount : ($plan[$number] ?? null),
                    'amount_paid' => $record?->amount_paid,
                    'remaining' => $record?->remaining_balance,
                    'status' => $record?->status ?? (isset($plan[$number]) ? 'Not billed' : 'Not recorded'),
                ];
            }
        }

        $totalDue = null;
        if ($rows && collect($rows)->every(fn ($row) => $row['amount_due'] !== null)) {
            $totalDue = round(collect($rows)->sum(fn ($row) => (float) $row['amount_due']), 2);
        }

        return [
            'rows' => $rows,
            'has_plan' => $plan !== [],
            'record_mismatch' => $recordMismatch,
            'period_mismatch' => $periodMismatch,
            'schedule_state' => $scheduleState,
            'total_due' => $totalDue,
        ];
    }
}
