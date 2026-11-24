<?php

namespace App\Mail;

use App\Models\LoanAgreement;
use Illuminate\Mail\Mailable;

class FinalizedLoanAgreement extends Mailable
{
    public function __construct(private LoanAgreement $agreement) {}

    public function build(): self
    {
        return $this->subject('Loan #'.$this->agreement->loan_id.' Agreement and Installment Schedule')
            ->view('emails.consumer.loan-agreement', ['loanId' => $this->agreement->loan_id])
            ->attachData($this->agreement->pdfBytes(), $this->agreement->filename(), ['mime' => 'application/pdf']);
    }
}
