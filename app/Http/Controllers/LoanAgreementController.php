<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanAgreement;
use App\Services\LoanAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoanAgreementController extends Controller
{
    public function show(Request $request, Loan $loan): Response
    {
        abort_unless($request->user()->consumer?->id === $loan->consumer_id, 403);

        $agreement = $loan->agreement()->firstOrFail();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($agreement->pdfBytes(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$agreement->filename().'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function release(Loan $loan, LoanAgreementService $agreements): RedirectResponse
    {
        return $this->deliveryResponse($agreements->release($loan));
    }

    public function retryEmail(Loan $loan, LoanAgreementService $agreements): RedirectResponse
    {
        return $this->deliveryResponse($agreements->retryEmail($loan));
    }

    private function deliveryResponse(string $status): RedirectResponse
    {
        return back()->with('agreement_status', match ($status) {
            LoanAgreement::SENT => 'The schedule has been released and the agreement email has been sent.',
            LoanAgreement::FAILED => 'The agreement is available in My Loans. Email delivery failed and can be retried from the loan details.',
            default => 'The agreement is available in My Loans. Email delivery is pending.',
        });
    }
}
