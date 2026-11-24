<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanDssEvaluation;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use App\Services\Dss\LoanApplicationEvaluationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LoanIncomeVerificationService
{
    public function __construct(
        private readonly LoanApplicationEvaluationService $evaluations,
        private readonly DssEligibilityService $eligibility,
    ) {}

    public function confirm(Loan $loan, User $admin, array $findings): string
    {
        $evaluation = DB::transaction(function () use ($loan, $admin, $findings): LoanDssEvaluation {
            $locked = $this->lockPendingProof($loan);
            $verifiedAt = now();
            $updates = [
                'income_verification_status' => 'Verified',
                'income_verified_by' => $admin->id,
                'income_verified_at' => $verifiedAt,
            ];

            // Preserve the previous API for callers that still submit a complete set of findings.
            if (count(array_intersect(['identity_confirmed', 'document_authentic', 'income_source_verified', 'verified_monthly_income', 'verification_notes'], array_keys($findings))) === 5) {
                $income = round((float) $findings['verified_monthly_income'], 2);
                $notes = trim($findings['verification_notes']);
                $updates['income_verified_monthly_amount'] = $income;
                $updates['income_verification_notes'] = $notes;
                $updates['income_ocr_data'] = array_merge($locked->income_ocr_data ?? [], [
                    'staff_verification' => [
                        'identity_confirmed' => true,
                        'document_authentic' => true,
                        'income_source_verified' => true,
                        'verified_monthly_income' => $income,
                        'verified_by' => $admin->id,
                        'verified_at' => $verifiedAt->toIso8601String(),
                        'notes' => $notes,
                        'method' => 'Admin staff inspection of original proof of income',
                    ],
                ]);
            }
            $locked->forceFill($updates)->save();

            return $this->evaluateLocked($locked);
        });

        if ($evaluation->wasRecentlyCreated) {
            $this->notifyDecision($loan, $evaluation->recommendation);
        }

        return $evaluation->recommendation;
    }

    public function evaluateVerified(Loan $loan): string
    {
        $evaluation = DB::transaction(function () use ($loan): LoanDssEvaluation {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, Loan::PENDING_STATUSES, true), 409);

            return $this->evaluateLocked($locked);
        });

        if ($evaluation->wasRecentlyCreated) {
            $this->notifyDecision($loan, $evaluation->recommendation);
        }

        return $evaluation->recommendation;
    }

    public function reconcilePreviouslyVerified(): void
    {
        $ids = Loan::query()->whereIn('status', Loan::PENDING_STATUSES)
            ->where('income_verification_status', 'Verified')
            ->whereNotNull('income_verified_by')->whereNotNull('income_verified_at')
            ->whereDoesntHave('latestDssEvaluation', fn ($query) => $query->whereIn('recommendation', ['Approved', 'Rejected']))
            ->whereNotIn('id', LoanDssEvaluation::query()->where('criteria_version', LoanApplicationEvaluationService::CRITERIA_VERSION)->select('loan_id'))
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                $evaluation = DB::transaction(function () use ($id): ?LoanDssEvaluation {
                    $loan = Loan::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                    if (! in_array($loan->status, Loan::PENDING_STATUSES, true)
                        || $loan->income_verification_status !== 'Verified'
                        || ! $loan->income_verified_by || ! $loan->income_verified_at
                        || LoanDssEvaluation::query()->where('loan_id', $id)->where('criteria_version', LoanApplicationEvaluationService::CRITERIA_VERSION)->exists()
                        || in_array($loan->latestDssEvaluation?->recommendation, ['Approved', 'Rejected'], true)) {
                        return null;
                    }

                    return $this->evaluateLocked($loan);
                });
                if ($evaluation?->wasRecentlyCreated) {
                    $this->notifyDecision(Loan::query()->findOrFail($id), $evaluation->recommendation);
                }
            } catch (\Throwable $exception) {
                Log::warning('Could not evaluate previously verified loan.', ['loan_id' => $id, 'exception' => $exception->getMessage()]);
            }
        }
    }

    private function evaluateLocked(Loan $loan): LoanDssEvaluation
    {
        abort_unless(filled($loan->proof_of_income_path) && Storage::disk('local')->exists($loan->proof_of_income_path), 422);
        abort_unless($loan->income_verification_status === 'Verified'
            && $loan->income_verified_by !== null
            && $loan->income_verified_at !== null, 409);

        $evaluation = $this->evaluations->evaluateAndSave($loan->load('consumer'), $this->eligibility);
        if ($evaluation->recommendation === 'Approved') {
            $loan->forceFill([
                'status' => 'Ready for Scheduling',
                'review_status' => 'Reviewed',
                'approved_at' => $loan->approved_at ?? now(),
                'decision_at' => $evaluation->evaluated_at,
            ])->save();
        } elseif ($evaluation->recommendation === 'Rejected') {
            $loan->forceFill([
                'status' => 'Rejected',
                'review_status' => 'Reviewed',
                'decision_at' => $evaluation->evaluated_at,
            ])->save();
        } else {
            $loan->forceFill(['status' => 'Needs Review', 'review_status' => 'Pending Review'])->save();
        }

        return $evaluation;
    }

    private function notifyDecision(Loan $loan, string $decision): void
    {
        try {
            $loan = $loan->fresh('consumer.user');
            $consumerMessage = match ($decision) {
                'Approved' => 'Your loan application has been approved. Please wait for the admin to schedule the next step.',
                'Rejected' => 'Your loan application was rejected. Review the decision details in your loan application.',
                default => 'Your proof of income was verified. Your loan application needs further review.',
            };
            $loan->consumer?->user?->notify(new LoanStatusNotification(
                loan: $loan,
                title: 'Loan application '.$decision,
                message: $consumerMessage,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Could not notify consumer of loan decision.', ['loan_id' => $loan->id, 'exception' => $exception->getMessage()]);
        }

        try {
            User::query()->where('role', 'admin')->get()->each(fn (User $admin) => $admin->notify(new LoanStatusNotification(
                loan: $loan,
                title: 'DSS loan evaluation: '.$decision,
                message: 'DSS evaluated loan #'.$loan->id.' for '.($loan->consumer?->full_name ?? 'the consumer').'. Result: '.$decision.'.',
                extraData: ['url' => route('loans.index', ['tab' => 'all'])],
            )));
        } catch (\Throwable $exception) {
            Log::warning('Could not notify administrators of loan decision.', ['loan_id' => $loan->id, 'exception' => $exception->getMessage()]);
        }
    }

    public function reject(Loan $loan, User $admin, string $reason): void
    {
        $reason = trim($reason);
        $loan = DB::transaction(function () use ($loan, $admin, $reason): Loan {
            $locked = $this->lockPendingProof($loan);
            $locked->forceFill([
                'income_verification_status' => 'Rejected',
                'income_verified_by' => $admin->id,
                'income_verified_at' => now(),
                'income_rejection_reason' => $reason,
            ])->save();

            return $locked->load('consumer.user');
        });

        $recipient = $loan->consumer?->email ?: $loan->consumer?->user?->email;
        if (filled($recipient)) {
            try {
                Mail::send('emails.consumer.loan-income-rejected', ['loan' => $loan, 'reason' => $reason], function ($message) use ($recipient): void {
                    $message->to($recipient)->subject('Proof of income needs correction');
                });
            } catch (\Throwable $exception) {
                Log::warning('Could not send loan income rejection email.', ['loan_id' => $loan->id, 'exception' => $exception->getMessage()]);
            }
        }

        $loan->consumer?->user?->notify(new LoanStatusNotification(
            loan: $loan,
            title: 'Proof of income needs correction',
            message: 'Your loan application remains pending. Please correct and resubmit your proof of income. Reason: '.$reason,
        ));
    }

    private function lockPendingProof(Loan $loan): Loan
    {
        $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($locked->status, Loan::PENDING_STATUSES, true), 404);
        abort_unless(in_array($locked->income_verification_status, [null, 'Pending Verification', 'Needs Review'], true), 409);
        abort_unless(filled($locked->proof_of_income_path) && Storage::disk('local')->exists($locked->proof_of_income_path), 422);

        return $locked;
    }
}
