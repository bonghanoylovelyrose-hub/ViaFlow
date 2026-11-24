<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LoanIncomeProofResubmissionService
{
    public function __construct(private readonly LoanIncomeOcrService $incomeOcr) {}

    public function replace(Loan $loan, UploadedFile $proof): void
    {
        $ocr = strtolower($proof->getClientOriginalExtension()) === 'pdf' ? [] : $this->incomeOcr->extract($proof);
        $path = $proof->store('loan-income-proofs', 'local');

        try {
            $loan = DB::transaction(function () use ($loan, $path, $ocr): Loan {
                $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($locked->status, Loan::PENDING_STATUSES, true) && $locked->income_verification_status === 'Rejected', 409);
                $evidence = $locked->income_ocr_data ?? [];
                $prior = $evidence['prior_proof_rejections'] ?? [];
                unset($evidence['prior_proof_rejections']);
                $prior[] = [
                    'path' => $locked->proof_of_income_path,
                    'reason' => $locked->income_rejection_reason ?? $locked->income_verification_notes,
                    'verified_by' => $locked->income_verified_by,
                    'verified_at' => $locked->income_verified_at?->toIso8601String(),
                    'verification_notes' => $locked->income_verification_notes,
                    'verified_monthly_income' => $locked->income_verified_monthly_amount,
                    'evidence' => $evidence,
                ];
                $locked->forceFill([
                    'proof_of_income_path' => $path,
                    'income_ocr_data' => array_merge($ocr, ['prior_proof_rejections' => $prior]),
                    'income_verification_status' => 'Pending Verification',
                    'income_verified_monthly_amount' => null,
                    'income_verified_by' => null,
                    'income_verified_at' => null,
                    'income_verification_notes' => null,
                    'income_rejection_reason' => null,
                    'status' => 'Needs Review',
                    'review_status' => 'Pending Review',
                ])->save();

                return $locked->load('consumer');
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        try {
            User::query()->where('role', 'admin')->get()->each(fn (User $admin) => $admin->notify(new LoanStatusNotification(
                loan: $loan,
                title: 'Replacement income proof submitted',
                message: ($loan->consumer?->full_name ?? 'A consumer').' resubmitted proof of income for verification.',
                extraData: ['url' => route('loans.index', ['tab' => 'pending', 'pending' => 'income'])],
            )));
        } catch (\Throwable $exception) {
            Log::warning('Could not notify admins of replacement income proof.', ['loan_id' => $loan->id, 'exception' => $exception->getMessage()]);
        }
    }
}
