<?php

namespace App\Support;

final class LoanTerms
{
    public const VERSION = 'loan-terms-v3';

    public const TITLE = 'Loan Meter Application Terms and Conditions';

    private const REPAYMENT_DEFAULT_POLICY = 'I understand that if I fail to pay my loan meter balance in full by the end of my selected repayment term of 3, 6, or 12 months, I will be ineligible to apply for another loan meter.';

    private const DISCONNECTION_NOTICE_POLICY = 'I understand that if I fail to fully pay my outstanding water meter loan balance by the end of my selected repayment term of 3, 6, or 12 months, I will receive a Disconnection Notice. I will be given three (3) days from the issuance of the notice to settle my outstanding balance. Failure to pay within this period may result in the disconnection of my water service, subject to confirmation by the VVWD Administrator.';

    public static function paragraphs(): array
    {
        return [
            'I am applying for a loan meter and confirm that I am a registered VHOA member living in Via Vida Subdivision with no overdue water bills or unpaid loans. I understand that the Decision Support System (DSS) checks my eligibility and that the VVWD Administrator makes the final decision on my application.',
            'If approved, I agree to pay for the water meter in interest-free monthly installments over my selected repayment term of 3, 6, or 12 months. I agree to pay each installment on or before its scheduled due date.',
            self::REPAYMENT_DEFAULT_POLICY,
            self::DISCONNECTION_NOTICE_POLICY,
            'The system issues this notice automatically. If the outstanding balance remains unpaid after the three-day grace period, the account will be marked Ready for Disconnection, subject to confirmation by the VVWD Administrator.',
            'I may pay in cash at the VVWD office or pay online and upload clear proof of payment. VVWD will credit my payment and issue a digital receipt after verification.',
            'I understand that the water meter remains the property of VVWD until I pay for it in full, and I agree to keep it at its assigned location and refrain from tampering with it.',
            'I understand that VVWD will use my personal information to process my application and manage my account in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173). VVWD will send application and payment updates to my registered email address.',
        ];
    }

    public static function isEmphasized(string $paragraph): bool
    {
        return in_array($paragraph, [self::REPAYMENT_DEFAULT_POLICY, self::DISCONNECTION_NOTICE_POLICY], true);
    }

    public static function text(int $term): string
    {
        return implode("\n\n", array_merge([self::TITLE], self::paragraphs()));
    }
}
