<?php
    $consumer = $bill->consumer;
    $meterReading = $bill->meterReading;
    $breakdown = $bill->billing_breakdown;
    $previousBalanceAudit = $bill->previous_balance_audit;
    $isCarriedForward = $bill->balanceIsCarriedForward();

    $previousUnpaidBalance = (float) $breakdown['previous_unpaid_balance'];
    $previousBalanceSourcePeriod = $previousBalanceAudit['source_period'] ?? $previousBill?->billing_period;
    $previousBalanceHasMultipleMonths = (float) ($previousBill?->arrears_amount ?? 0) > 0;
    $previousBalanceLabel = 'Previous Balance';

    if ($previousUnpaidBalance > 0) {
        $previousBalanceLabel .= ' ('.($previousBalanceHasMultipleMonths
            ? 'Multiple Months'
            : ($previousBalanceSourcePeriod?->format('F Y') ?? 'Multiple Months')).')';
    }

    $statementTotalOutstanding = (float) ($consumerPaymentRuleSummary['total_outstanding'] ?? $bill->balance_due);
    $statementHasDisconnectionNotice = (bool) ($consumerPaymentRuleSummary['active'] ?? false);
    $statementRequiredPaymentAmount = $consumerPaymentRuleSummary['required_payment_amount'] ?? $statementTotalOutstanding;
    $statementPaymentRuleMessage = $consumerPaymentRuleSummary['message'] ?? ($statementHasDisconnectionNotice
        ? 'Full payment is required. Partial payment is not allowed.'
        : 'Partial payment is allowed.');
    $statementPaymentStatus = match (true) {
        $isCarriedForward => 'Carried Forward',
        $statementHasDisconnectionNotice => 'Disconnection Notice',
        round($statementTotalOutstanding, 2) <= 0.0 => 'Paid',
        (float) $bill->verified_paid_amount > 0 => 'Paid with Balance',
        default => 'Unpaid',
    };

    $hasVerifiedMembership = $consumer?->hasVerifiedMembership() ?? false;
    $hasPendingMembership = $consumer?->hasPendingMembershipVerification() ?? false;
    $isMemberApplicant = $consumer?->isMemberApplicant() ?? false;
    $membershipLabel = match (true) {
        $hasVerifiedMembership => 'Member · Verified',
        $hasPendingMembership => 'Member application · Pending verification',
        $isMemberApplicant => 'Member application · Not verified',
        default => 'Non-Member',
    };
    $billedUnitRate = (float) $breakdown['rate_per_cubic_meter'];
    $standardUnitRate = (float) $breakdown['standard_unit_rate'];
    $memberRateApplied = $billedUnitRate < $standardUnitRate;
    $membershipNote = match (true) {
        $hasVerifiedMembership && $memberRateApplied => 'Member billing rate is applied. The usage charge already reflects the member savings.',
        $hasVerifiedMembership => 'Membership is verified. This saved bill uses the standard rate.',
        $hasPendingMembership && $memberRateApplied => 'Membership verification is pending. This saved bill reflects a member rate. Future member benefits remain subject to verification.',
        $hasPendingMembership => 'Membership verification is pending. The standard rate applies to this bill.',
        $isMemberApplicant && $memberRateApplied => 'A member application is on record. This saved bill reflects a member rate. Future member benefits remain subject to verification.',
        $isMemberApplicant => 'A member application is on record. The standard rate applies to this bill.',
        default => 'The standard unit rate applies to non-members.',
    };

    $serviceAddress = $consumer?->service_address ?? '';
    $block = $consumer?->address_block;
    if (preg_match('/\b(?:block|blk\.?)\s+([^\s,;]+)/i', $block ?: $serviceAddress, $blockMatch)) {
        $block = $blockMatch[1];
    }
    preg_match('/\blot\s+([^\s,;]+)/i', ($consumer?->address_block ?? '').' '.$serviceAddress, $lotMatch);

    $billingMonth = $bill->billing_period->format('F Y');
    $readingDate = $meterReading?->reading_date;
    $previousMeterReading = $readingDate && $previousBill?->meter_reading_id ? $previousBill->meterReading : null;
    $previousReadingDate = $previousMeterReading?->reading_date;
    $hasReadingPeriod = $previousReadingDate && $previousReadingDate->lt($readingDate)
        && $previousMeterReading->meter_number === $meterReading->meter_number
        && (float) $previousMeterReading->current_reading === (float) $meterReading->previous_reading;

    $currentCharges = (float) $breakdown['current_bill_amount'];
    $penaltyAmount = (float) $breakdown['penalties'];
    $loanAmount = (float) $breakdown['loan_installment_amount'];
    $totalAmountDue = (float) $breakdown['total_amount_due'];
    $otherAdjustment = round($totalAmountDue - ($currentCharges + $previousUnpaidBalance + $penaltyAmount + $loanAmount), 2);
    $verifiedPayments = $bill->statement_paid_amount;

    $money = fn (float|int|string|null $amount): string => 'P '.number_format((float) $amount, 2);
    $historicalMoney = fn (float|int|string|null $amount): string => $amount === null ? 'Not available' : $money($amount);
    $signedMoney = fn (float|int|string|null $amount): string => (float) $amount < 0
        ? '-'.$money(abs((float) $amount))
        : $money($amount);
    $quantity = fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
?>

<article class="stmt-document" id="billing-statement" aria-labelledby="stmt-heading" data-admin-natural-page-scroll>
    <table class="stmt-bill" aria-labelledby="stmt-heading">
        <colgroup>
            <col class="stmt-label-column">
            <col class="stmt-reading-column">
            <col class="stmt-unit-column">
            <col class="stmt-amount-column">
        </colgroup>
        <tbody>
            <tr>
                <td colspan="4" class="stmt-header">
                    <div class="stmt-organization">Via Vida Townhomes HOA Water System</div>
                    <div>Purok Via Vida, Brgy. Datu Abdul Dadia</div>
                    <div>Panabo City, Davao del Norte</div>
                    <h1 id="stmt-heading">Water Bill</h1>
                </td>
            </tr>
            <tr>
                <td colspan="3" class="stmt-consumer-field">Name: <strong><?php echo e($consumer?->full_name ?? 'N/A'); ?></strong></td>
                <td>Date: <strong><?php echo e($bill->created_at?->format('F d, Y') ?? '—'); ?></strong></td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="stmt-inline-field">Blk: <strong><?php echo e($block ?: '—'); ?></strong></span>
                    <span class="stmt-inline-field">Lot: <strong><?php echo e($lotMatch[1] ?? '—'); ?></strong></span>
                    <span class="stmt-consumer-field">Street: <strong><?php echo e($consumer?->address_street ?: '—'); ?></strong></span>
                </td>
                <td>Due Date: <strong><?php echo e($bill->due_date->format('F d, Y')); ?></strong></td>
            </tr>
            <tr>
                <td class="stmt-consumer-field">Status: <strong><?php echo e($membershipLabel); ?></strong></td>
                <th scope="row" colspan="2" class="stmt-period">Period Covered:</th>
                <td class="stmt-period">
                    <?php if($hasReadingPeriod): ?>
                        <?php echo e($previousReadingDate->format('M d, Y')); ?> – <?php echo e($readingDate->format('M d, Y')); ?>

                    <?php else: ?>
                        <?php echo e($billingMonth); ?>

                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="4" class="stmt-band">Billing Computation</th></tr>
            <tr>
                <th scope="row" class="stmt-label-column">Present Reading</th>
                <td class="stmt-number stmt-reading-column"><?php echo e($meterReading?->current_reading === null ? '—' : $quantity($meterReading->current_reading)); ?></td>
                <td class="stmt-number stmt-unit stmt-unit-column">cu.m</td>
                <td class="stmt-amount-column"></td>
            </tr>
            <tr>
                <th scope="row">Previous Reading</th>
                <td class="stmt-number"><?php echo e($meterReading?->previous_reading === null ? '—' : $quantity(-(float) $meterReading->previous_reading)); ?></td>
                <td class="stmt-number stmt-unit">cu.m</td>
                <td></td>
            </tr>
            <tr>
                <th scope="row">Current Water Consumption</th>
                <td class="stmt-number"><?php echo e($quantity($breakdown['total_consumption'])); ?></td>
                <td class="stmt-number stmt-unit">cu.m</td>
                <td></td>
            </tr>
            <tr class="stmt-total">
                <th scope="row" colspan="3">Total Current Bill</th>
                <td class="stmt-number"><?php echo e($money($currentCharges)); ?></td>
            </tr>
            <tr>
                <th scope="row" colspan="3">ADD: Unpaid Balance Before Reading</th>
                <td class="stmt-number"><?php echo e($money($previousUnpaidBalance)); ?></td>
            </tr>
            <tr>
                <th scope="row" colspan="3" class="stmt-indent">Monthly Dues</th>
                <td class="stmt-number">—</td>
            </tr>
            <?php if($penaltyAmount != 0): ?>
                <tr>
                    <th scope="row" colspan="3">Penalty or Additional Charges</th>
                    <td class="stmt-number"><?php echo e($signedMoney($penaltyAmount)); ?></td>
                </tr>
            <?php endif; ?>
            <?php if($otherAdjustment != 0): ?>
                <tr>
                    <th scope="row" colspan="3">Other Charges / Adjustments</th>
                    <td class="stmt-number"><?php echo e($signedMoney($otherAdjustment)); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="stmt-total">
                <th scope="row" colspan="3">Total Obligation</th>
                <td class="stmt-number"><?php echo e($money($totalAmountDue)); ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">
                    Remarks: <?php echo e($meterReading?->remarks ?: '—'); ?>

                    <?php if($bill->is_demo): ?>
                        <strong>Demo bill · Excluded from payment collection</strong>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td colspan="3">With Meter Loan:</td>
                <td class="stmt-number">
                    <?php if($loanAmount != 0): ?>
                        <?php echo e($signedMoney($loanAmount)); ?> <span class="stmt-loan-note">(included above)</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            <tr><td colspan="4" class="stmt-reminder">Please disregard if payment has been made.</td></tr>
        </tfoot>
    </table>

    <table class="stmt-details" aria-label="Billing and payment details">
        <colgroup>
            <col class="stmt-detail-column">
            <col class="stmt-detail-column">
        </colgroup>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Consumer Information</th></tr>
            <tr>
                <td>Account Number: <strong><?php echo e($consumer?->account_number ?? 'N/A'); ?></strong></td>
                <td>Meter Number: <strong><?php echo e($meterReading?->meter_number ?: ($consumer?->meter_number ?? 'N/A')); ?></strong></td>
            </tr>
            <tr><td colspan="2">Service Address: <?php echo e($serviceAddress ?: 'N/A'); ?></td></tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Detailed Billing Breakdown</th></tr>
            <tr>
                <td>Billing Month: <?php echo e($billingMonth); ?></td>
                <td>Bill Status: <?php echo e($isCarriedForward ? 'Carried Forward' : $bill->computedStatus()); ?></td>
            </tr>
            <tr>
                <td>Base Charge: <?php echo e($money($breakdown['base_charge'])); ?></td>
                <td>Usage Charge: <?php echo e($money($breakdown['usage_charge'])); ?></td>
            </tr>
            <tr>
                <td><?php echo e($previousBalanceLabel); ?>: <?php echo e($money($previousUnpaidBalance)); ?></td>
                <td>Previous Bill Amount: <?php echo e($money($previousBill?->amount ?? $previousBalanceAudit['source_bill_amount'] ?? 0)); ?></td>
            </tr>
            <tr>
                <td>Other Charges / Adjustments: <?php echo e($signedMoney($otherAdjustment)); ?></td>
                <td>Previous Period: <?php echo e(($previousBill?->billing_period ?? $previousBalanceAudit['source_period'] ?? null)?->format('F Y') ?? 'No previous bill'); ?></td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Discounts &amp; Adjustments</th></tr>
            <tr>
                <td>Billed Unit Rate: <?php echo e($money($billedUnitRate)); ?> / cu.m</td>
                <td>Standard Unit Rate: <?php echo e($money($standardUnitRate)); ?> / cu.m</td>
            </tr>
            <tr>
                <td colspan="2">Member Rate Savings: <?php echo e($money($breakdown['discounts'])); ?>. <?php echo e($membershipNote); ?></td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Meter Reading Details</th></tr>
            <?php if($meterReading): ?>
                <tr>
                    <td>Reading Date: <?php echo e($readingDate?->format('F d, Y') ?? 'N/A'); ?></td>
                    <td>Reading Status: <?php echo e($meterReading->status ?? 'N/A'); ?></td>
                </tr>
                <tr><td colspan="2">Encoded By: <?php echo e($meterReading->reader?->name ?? 'N/A'); ?></td></tr>
            <?php else: ?>
                <tr><td colspan="2">No linked meter reading record is attached to this bill.</td></tr>
            <?php endif; ?>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Payment Summary</th></tr>
            <tr>
                <td>Verified Payments: <?php echo e($verifiedPayments === null ? 'Not available' : $signedMoney(-$verifiedPayments)); ?></td>
                <td><strong>Remaining Balance (This Bill): <?php echo e($historicalMoney($bill->statement_balance)); ?></strong></td>
            </tr>
            <?php if($isCarriedForward): ?>
                <tr><td colspan="2"><strong>Carried forward:</strong> This historical balance moved to a later bill and is not charged twice.</td></tr>
            <?php else: ?>
                <tr>
                    <td colspan="2">
                        <strong>Account Payment Requirements</strong><br>
                        Payment Status: <strong><?php echo e($statementPaymentStatus); ?></strong><br>
                        Disconnection Notice: <strong><?php echo e($statementHasDisconnectionNotice ? 'Active' : 'None'); ?></strong><br>
                        Total Outstanding Balance: <strong>&#8369;<?php echo e(number_format($statementTotalOutstanding, 2)); ?></strong><br>
                        <?php if($statementHasDisconnectionNotice): ?>
                            Required Payment Amount: <strong>&#8369;<?php echo e(number_format($statementRequiredPaymentAmount, 2)); ?></strong><br>
                        <?php endif; ?>
                        <?php echo e($statementPaymentRuleMessage); ?>

                    </td>
                </tr>
            <?php endif; ?>
            <tr><th scope="row" colspan="2" class="stmt-band">Payment Instructions</th></tr>
            <tr>
                <td colspan="2">
                    Payment Reference Number: <strong><?php echo e($bill->prn); ?></strong><br>
                    Present this bill and the PRN at the Via-Vida Waterworks payment counter or use an authorized digital payment channel.
                    Verified payments are applied oldest-first.
                </td>
            </tr>
        </tbody>
        <?php if(! $isCarriedForward && $loanInstallments->isNotEmpty()): ?>
            <tbody>
                <tr><th scope="rowgroup" colspan="2" class="stmt-band">Loan Installments</th></tr>
                <?php $__currentLoopData = $loanInstallments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $installment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td>
                            <?php echo e($installment->remaining_balance > 0 && $installment->bill_id !== $bill->id ? 'Unpaid ' : ''); ?>Month <?php echo e($installment->installment_number); ?> Loan Installment (<?php echo e($installment->status); ?>)
                        </td>
                        <td class="stmt-number"><?php echo e($money($installment->remaining_balance)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <th scope="row">Total Loan Amount Due</th>
                    <td class="stmt-number"><?php echo e($money($loanInstallments->sum('remaining_balance'))); ?></td>
                </tr>
            </tbody>
        <?php endif; ?>
        <?php if($previousBalanceAudit): ?>
            <tbody>
                <tr><th scope="rowgroup" colspan="2" class="stmt-band">How the Previous Balance Was Carried Forward</th></tr>
                <tr>
                    <td colspan="2">
                        <?php echo e($previousBalanceAudit['consumer_name']); ?> had a previous bill of <?php echo e($money($previousBalanceAudit['source_bill_amount'])); ?>

                        <?php if($previousBalanceAudit['source_period']): ?>
                            for <?php echo e($previousBalanceAudit['source_period']->format('F Y')); ?>

                        <?php endif; ?>
                        and had <?php echo e($money($previousBalanceAudit['payments_applied_before_carryover'])); ?> applied before
                        <?php echo e($money($previousBalanceAudit['carried_balance'])); ?> was carried into the <?php echo e($previousBalanceAudit['current_period']->format('F Y')); ?> bill.
                    </td>
                </tr>
                <tr>
                    <td>Previous Bill PRN: <?php echo e($previousBalanceAudit['source_prn'] ?? 'N/A'); ?></td>
                    <td>Total Verified Payments on Previous Bill: <?php echo e($historicalMoney($previousBalanceAudit['verified_payments'])); ?></td>
                </tr>
                <tr><td colspan="2">Reason: <?php echo e($previousBalanceAudit['reason']); ?></td></tr>
            </tbody>
        <?php endif; ?>
    </table>
</article>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/shared/billing/statement-document.blade.php ENDPATH**/ ?>