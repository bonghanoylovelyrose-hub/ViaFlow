<?php

namespace App\Services\Dss;

use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Services\DssEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanApplicationEvaluationService
{
    public const CRITERIA_VERSION = 'dss-loan-eligibility-4-no-membership-gate';

    public function evaluateAndSave(Loan $loan, DssEligibilityService $eligibility): LoanDssEvaluation
    {
        if ($loan->income_verification_status !== 'Verified' || ! $loan->income_verified_by || ! $loan->income_verified_at) {
            throw ValidationException::withMessages(['income_verification' => 'Proof of income must be verified before DSS evaluation.']);
        }
        $consumer = $loan->consumer;
        $assessment = $eligibility->loanEligibility($consumer, $loan->id);
        $metrics = $assessment['metrics'];
        $missing = [];
        $incomeEvidence = is_array($loan->income_ocr_data) ? $loan->income_ocr_data : [];

        if (! $consumer) {
            $missing[] = 'Linked consumer profile';
        } else {
            foreach ([
                'consumer account status' => $consumer->status,
                'consumer account number' => $consumer->account_number,
                'consumer meter number' => $consumer->meter_number,
            ] as $field => $value) {
                if (blank($value)) {
                    $missing[] = ucfirst($field);
                }
            }
            if (in_array($consumer->status, ['Pending', 'Pending Verification'], true)) {
                $missing[] = 'Consumer account verification is pending';
            }
        }

        $blockingLoans = $consumer
            ? $consumer->loans->reject(fn (Loan $existing): bool => $existing->id === $loan->id)
                ->filter(fn (Loan $existing): bool => $existing->blocksNewApplication())->count()
            : null;
        $overdueLoans = $consumer ? (int) $metrics['overdue_loans'] : null;
        $billBalance = $consumer ? (float) $metrics['unpaid_balance'] : null;
        $overdueWaterBalance = $consumer ? (float) $metrics['overdue_water_balance'] : null;
        $priorLoanBalance = $consumer ? (float) $metrics['unpaid_loan_balance'] : null;
        $disconnectionNotices = $consumer
            ? $consumer->disconnectionNotices()->orderBy('issued_at')->get([
                'id', 'status', 'issued_at', 'deadline_at', 'settled_at', 'disconnected_at', 'outstanding_amount', 'months_unpaid',
            ])->map(fn ($notice): array => [
                'notice_id' => $notice->id,
                'status' => $notice->status,
                'issued_at' => $notice->issued_at?->toIso8601String(),
                'deadline_at' => $notice->deadline_at?->toIso8601String(),
                'settled_at' => $notice->settled_at?->toIso8601String(),
                'disconnected_at' => $notice->disconnected_at?->toIso8601String(),
                'outstanding_amount' => (float) $notice->outstanding_amount,
                'months_unpaid' => (int) $notice->months_unpaid,
            ])->all()
            : [];
        $verifiedMonthlyIncome = is_numeric($loan->income_verified_monthly_amount) ? round((float) $loan->income_verified_monthly_amount, 2) : null;

        $values = [
            'consumer_verified' => $consumer?->isVerified(),
            'late_payments_last_six_months' => $consumer ? (int) $metrics['recurring_late_payments'] : null,
            'late_payment_history' => $metrics['late_payment_history'],
            'water_bill_outstanding_balance' => $billBalance,
            'overdue_water_balance' => $overdueWaterBalance,
            'overdue_water_months' => $metrics['overdue_water_months'],
            'overdue_water_history' => $metrics['overdue_water_history'],
            'previous_loan_outstanding_balance' => $priorLoanBalance,
            'blocking_loans' => $blockingLoans,
            'overdue_approved_loans' => $overdueLoans,
            'active_water_connection' => $consumer ? ($consumer->status !== 'Disconnected' && filled($consumer->account_number)) : null,
            'account_status_recorded' => $consumer?->status,
            'account_number_recorded' => $consumer?->account_number,
            'meter_number_recorded' => $consumer?->meter_number,
            'application_status_recorded' => $loan->status,
            'declared_monthly_income' => $loan->monthly_income === null ? null : (float) $loan->monthly_income,
            'verified_monthly_income' => $verifiedMonthlyIncome,
            'income_verification_status' => $loan->income_verification_status,
            'income_ocr_status' => $incomeEvidence['ocr_status'] ?? null,
            'income_ocr_confidence' => $incomeEvidence['ocr_confidence'] ?? null,
            'disconnection_notice_history' => $disconnectionNotices,
        ];

        $accountVerificationMissing = array_intersect($missing, ['Consumer account status', 'Consumer account number', 'Consumer meter number']) !== [];
        $connectionMissing = array_intersect($missing, ['Consumer account status', 'Consumer account number']) !== [];
        $criterionSpecs = $consumer ? [
            ['id' => 'proof_of_income_verified', 'label' => 'Proof of income verification', 'value' => true, 'required' => true, 'unit' => 'boolean', 'passed' => true],
            ['id' => 'verified_consumer', 'label' => 'Consumer account verification', 'value' => $values['consumer_verified'], 'required' => true, 'unit' => 'boolean', 'passed' => $accountVerificationMissing || in_array($consumer->status, ['Pending', 'Pending Verification'], true) ? null : ($assessment['reasons'][0]['passed'] ?? false)],
            ['id' => 'late_payment_history', 'label' => 'Delayed or late payment records in the prior six months', 'value' => $values['late_payments_last_six_months'], 'required' => 0, 'unit' => 'records', 'passed' => $assessment['reasons'][1]['passed'] ?? false],
            ['id' => 'outstanding_balance', 'label' => 'Overdue water and previous-loan balances', 'value' => round(($overdueWaterBalance ?? 0) + ($priorLoanBalance ?? 0), 2), 'required' => 0, 'unit' => 'PHP', 'passed' => $assessment['reasons'][2]['passed'] ?? false],
            ['id' => 'blocking_or_overdue_loan', 'label' => 'Blocking or overdue loans', 'value' => ['blocking' => $blockingLoans, 'overdue' => $overdueLoans], 'required' => ['blocking' => 0, 'overdue' => 0], 'unit' => 'loans', 'passed' => $assessment['reasons'][3]['passed'] ?? false],
            ['id' => 'active_connection', 'label' => 'Active water connection', 'value' => $values['active_water_connection'], 'required' => true, 'unit' => 'boolean', 'passed' => $connectionMissing ? null : ($assessment['reasons'][4]['passed'] ?? false)],
        ] : [];
        $reasonIndexes = ['verified_consumer' => 0, 'late_payment_history' => 1, 'outstanding_balance' => 2, 'blocking_or_overdue_loan' => 3, 'active_connection' => 4];
        $failed = collect($criterionSpecs)->filter(fn (array $criterion): bool => $criterion['passed'] === false)
            ->map(fn (array $criterion): array => [
                ...$criterion,
                'explanation' => $this->explanation($criterion).' '.($assessment['reasons'][$reasonIndexes[$criterion['id']] ?? -1]['text'] ?? ''),
            ])->values()->all();

        $recommendation = $missing !== [] ? 'Needs Review' : ($assessment['eligible'] ? 'Approved' : 'Rejected');
        $values['approval_reasons'] = $recommendation === 'Approved'
            ? array_merge(['Proof of income was verified by an administrator.'], collect($assessment['reasons'])->where('passed', true)->pluck('text')->all())
            : [];
        $incomeEvidence['trusted_verification'] = [
            'status' => $loan->income_verification_status,
            'verified_by' => $loan->income_verified_by,
            'verified_at' => $loan->income_verified_at?->toIso8601String(),
            'verified_monthly_income' => $verifiedMonthlyIncome,
            'verification_notes' => $loan->income_verification_notes,
        ];
        $evidence = [
            'loan_id' => $loan->id,
            'recommendation' => $recommendation,
            'criteria_version' => self::CRITERIA_VERSION,
            'evaluated_values' => $values,
            'income_evidence' => $incomeEvidence,
            'income_verification_status' => $loan->income_verification_status,
            'failed_criteria' => $failed,
            'missing_information' => $missing,
        ];
        $hashEvidence = $evidence;
        unset($hashEvidence['evaluated_values']['application_status_recorded']);
        $hash = hash('sha256', json_encode($hashEvidence, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($loan, $recommendation, $values, $incomeEvidence, $failed, $missing, $hash): LoanDssEvaluation {
            Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $existing = LoanDssEvaluation::query()->where('loan_id', $loan->id)->where('evidence_hash', $hash)->first();
            if ($existing) {
                return $existing;
            }

            $hasPriorEvaluation = LoanDssEvaluation::query()->where('loan_id', $loan->id)->exists();

            return LoanDssEvaluation::create([
                'loan_id' => $loan->id,
                'recommendation' => $recommendation,
                'evaluation_type' => $loan->status === 'Rejected' || $hasPriorEvaluation ? 'reassessment' : 'initial',
                'criteria_version' => self::CRITERIA_VERSION,
                'evaluated_values' => $values,
                'income_evidence' => $incomeEvidence,
                'income_verification_status' => $loan->income_verification_status,
                'failed_criteria' => $failed,
                'missing_information' => $missing,
                'evidence_hash' => $hash,
                'evaluated_at' => now(),
            ]);
        });
    }

    private function explanation(array $criterion): string
    {
        return sprintf(
            '%s: recorded %s %s; required %s %s.',
            $criterion['label'],
            json_encode($criterion['value'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $criterion['unit'],
            json_encode($criterion['required'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $criterion['unit'],
        );
    }
}
