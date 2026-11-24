<?php

namespace App\Services;

use App\Mail\FinalizedLoanAgreement;
use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Models\LoanInstallment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LoanAgreementService
{
    public function __construct(private LoanBillingSynchronizer $billing) {}

    public function release(Loan $loan): string
    {
        return $this->deliver($loan, $this->finalize($loan), LoanAgreement::PENDING);
    }

    public function finalize(Loan $loan): LoanAgreement
    {
        return DB::transaction(function () use ($loan): LoanAgreement {
            $locked = Loan::query()->with('consumer')->lockForUpdate()->findOrFail($loan->id);
            $existing = $locked->agreement()->first();
            if ($existing) {
                return $existing;
            }

            if (! $locked->approved_at || $locked->applicationStatusCategory() !== 'Approved') {
                throw ValidationException::withMessages(['agreement' => 'An approved loan is required to release the installment schedule.']);
            }
            if (! $locked->terms_agreed_at || blank($locked->terms_version) || blank($locked->terms_text)) {
                throw ValidationException::withMessages(['agreement' => 'This loan has no saved agreement acceptance to issue.']);
            }

            $installments = $this->savedInstallments($locked);
            if ($installments->count() < (int) $locked->term_months || $installments->contains(fn (LoanInstallment $row) => ! $row->due_date)) {
                // Use the billing workflow to materialize saved amounts and due dates.
                $result = $this->billing->synchronize($locked->consumer_id, issueFutureFirstBill: false, loanId: $locked->id);
                if (in_array($locked->id, $result['review_loan_ids'], true)) {
                    throw ValidationException::withMessages(['agreement' => 'The installment schedule requires billing review before release.']);
                }
                $installments = $this->savedInstallments($locked);
            }

            $term = (int) $locked->term_months;
            $total = round((float) $installments->sum('required_amount'), 2);
            if (! in_array($term, [3, 6, 12], true)
                || $installments->count() !== $term
                || $total !== round((float) $locked->amount, 2)
                || $locked->regularMonthlyInstallment() <= 0
                || ! $installments->every(fn (LoanInstallment $row, int $index) => (int) $row->installment_number === $index + 1
                    && $row->due_date && (float) $row->required_amount > 0)) {
                throw ValidationException::withMessages(['agreement' => 'A complete saved installment schedule with due dates and amounts matching the approved loan is required.']);
            }

            $issuedAt = now();
            // Embed only the glyphs in the agreement so its encoded PDF fits MySQL's packet limit.
            $pdf = Pdf::setOption('isFontSubsettingEnabled', true)
                ->loadView('shared.loans.agreement-pdf', [
                    'loan' => $locked,
                    'installments' => $installments,
                    'total' => $total,
                    'issuedAt' => $issuedAt,
                ])->output();

            return $locked->agreement()->create([
                'pdf_base64' => base64_encode($pdf),
                'issued_at' => $issuedAt,
            ]);
        }, 3);
    }

    public function retryEmail(Loan $loan): string
    {
        return $this->deliver($loan, $loan->agreement()->firstOrFail(), LoanAgreement::FAILED);
    }

    private function savedInstallments(Loan $loan): Collection
    {
        return $loan->installments()->orderBy('installment_number')->lockForUpdate()->get();
    }

    private function deliver(Loan $loan, LoanAgreement $agreement, string $eligibleStatus): string
    {
        $recipient = $loan->consumer()->value('email');

        // Commit the claim before contacting the mail transport so simultaneous
        // releases and retries cannot send the same agreement.
        $delivery = LoanAgreement::query()->whereKey($agreement->id);
        $claimed = (clone $delivery)->where('email_status', $eligibleStatus)
            ->update(['email_status' => LoanAgreement::SENDING, 'email_recipient' => $recipient]);

        if (! $claimed) {
            return $delivery->value('email_status');
        }

        try {
            if (blank($recipient)) {
                throw new RuntimeException('No registered consumer email address.');
            }

            $sent = Mail::to($recipient)->send(new FinalizedLoanAgreement($agreement));
            if ($sent === null) {
                throw new RuntimeException('The mailer did not send the agreement.');
            }
        } catch (Throwable $exception) {
            $delivery->update(['email_status' => LoanAgreement::FAILED]);

            Log::warning('Loan agreement email failed.', [
                'loan_id' => $loan->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return LoanAgreement::FAILED;
        }

        $delivery->update(['email_status' => LoanAgreement::SENT, 'email_sent_at' => now()]);

        return LoanAgreement::SENT;
    }
}
