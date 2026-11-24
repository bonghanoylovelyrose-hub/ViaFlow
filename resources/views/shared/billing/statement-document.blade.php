@php
    $consumer = $bill->consumer;
    $meterReading = $bill->meterReading;
    $breakdown = $bill->billing_breakdown;
    $previousBalanceAudit = $bill->previous_balance_audit;
    $isCarriedForward = $bill->balanceHasBeenCarriedForward();

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
@endphp

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
                <td colspan="3" class="stmt-consumer-field">Name: <strong>{{ $consumer?->full_name ?? 'N/A' }}</strong></td>
                <td>Date: <strong>{{ ($bill->is_billing_fixture ? $bill->created_at?->copy()->timezone('Asia/Manila') : $bill->created_at)?->format('F d, Y') ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="stmt-inline-field">Blk: <strong>{{ $block ?: '—' }}</strong></span>
                    <span class="stmt-inline-field">Lot: <strong>{{ $lotMatch[1] ?? '—' }}</strong></span>
                    <span class="stmt-consumer-field">Street: <strong>{{ $consumer?->address_street ?: '—' }}</strong></span>
                </td>
                <td>Due Date: <strong>{{ $bill->due_date->format('F d, Y') }}</strong></td>
            </tr>
            <tr>
                <td class="stmt-consumer-field">Status: <strong>{{ $membershipLabel }}</strong></td>
                <th scope="row" colspan="2" class="stmt-period">Period Covered:</th>
                <td class="stmt-period">
                    @if ($hasReadingPeriod)
                        {{ $previousReadingDate->format('M d, Y') }} – {{ $readingDate->format('M d, Y') }}
                    @else
                        {{ $billingMonth }}
                    @endif
                </td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="4" class="stmt-band">Billing Computation</th></tr>
            <tr>
                <th scope="row" class="stmt-label-column">Present Reading</th>
                <td class="stmt-number stmt-reading-column">{{ $meterReading?->current_reading === null ? '—' : $quantity($meterReading->current_reading) }}</td>
                <td class="stmt-number stmt-unit stmt-unit-column">cu.m</td>
                <td class="stmt-amount-column"></td>
            </tr>
            <tr>
                <th scope="row">Previous Reading</th>
                <td class="stmt-number">{{ $meterReading?->previous_reading === null ? '—' : $quantity(-(float) $meterReading->previous_reading) }}</td>
                <td class="stmt-number stmt-unit">cu.m</td>
                <td></td>
            </tr>
            <tr>
                <th scope="row">Current Water Consumption</th>
                <td class="stmt-number">{{ $quantity($breakdown['total_consumption']) }}</td>
                <td class="stmt-number stmt-unit">cu.m</td>
                <td></td>
            </tr>
            <tr class="stmt-total">
                <th scope="row" colspan="3">Total Current Bill</th>
                <td class="stmt-number">{{ $money($currentCharges) }}</td>
            </tr>
            <tr>
                <th scope="row" colspan="3">ADD: Unpaid Balance Before Reading</th>
                <td class="stmt-number">{{ $money($previousUnpaidBalance) }}</td>
            </tr>
            <tr>
                <th scope="row" colspan="3" class="stmt-indent">Monthly Dues</th>
                <td class="stmt-number">—</td>
            </tr>
            @if ($penaltyAmount != 0)
                <tr>
                    <th scope="row" colspan="3">Penalty or Additional Charges</th>
                    <td class="stmt-number">{{ $signedMoney($penaltyAmount) }}</td>
                </tr>
            @endif
            @if ($otherAdjustment != 0)
                <tr>
                    <th scope="row" colspan="3">Other Charges / Adjustments</th>
                    <td class="stmt-number">{{ $signedMoney($otherAdjustment) }}</td>
                </tr>
            @endif
            <tr class="stmt-total">
                <th scope="row" colspan="3">Total Obligation</th>
                <td class="stmt-number">{{ $money($totalAmountDue) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">
                    @if ($bill->superseded_by_bill_id)
                        <strong>Superseded statement</strong>
                    @elseif ($bill->is_billing_fixture)
                        <strong>Reference statement</strong>
                    @else
                        Remarks: {{ $meterReading?->remarks ?: '—' }}
                        @if ($bill->is_demo)<strong>Demo bill · Excluded from payment collection</strong>@endif
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="3">With Meter Loan:</td>
                <td class="stmt-number">
                    @if ($loanAmount != 0)
                        {{ $signedMoney($loanAmount) }} <span class="stmt-loan-note">(included above)</span>
                    @else
                        —
                    @endif
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
                <td>Account Number: <strong>{{ $consumer?->account_number ?? 'N/A' }}</strong></td>
                <td>Meter Number: <strong>{{ $meterReading?->meter_number ?: ($consumer?->meter_number ?? 'N/A') }}</strong></td>
            </tr>
            <tr><td colspan="2">Service Address: {{ $serviceAddress ?: 'N/A' }}</td></tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Detailed Billing Breakdown</th></tr>
            <tr>
                <td>Billing Month: {{ $billingMonth }}</td>
                <td>Bill Status: {{ $isCarriedForward ? 'Carried Forward' : $bill->computedStatus() }}</td>
            </tr>
            <tr>
                <td>Base Charge: {{ $money($breakdown['base_charge']) }}</td>
                <td>Usage Charge: {{ $money($breakdown['usage_charge']) }}</td>
            </tr>
            <tr>
                <td>{{ $previousBalanceLabel }}: {{ $money($previousUnpaidBalance) }}</td>
                <td>Previous Bill Amount: {{ $money($previousBill?->amount ?? $previousBalanceAudit['source_bill_amount'] ?? 0) }}</td>
            </tr>
            <tr>
                <td>Other Charges / Adjustments: {{ $signedMoney($otherAdjustment) }}</td>
                <td>Previous Period: {{ ($previousBill?->billing_period ?? $previousBalanceAudit['source_period'] ?? null)?->format('F Y') ?? 'No previous bill' }}</td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Discounts &amp; Adjustments</th></tr>
            <tr>
                <td>Billed Unit Rate: {{ $money($billedUnitRate) }} / cu.m</td>
                <td>Standard Unit Rate: {{ $money($standardUnitRate) }} / cu.m</td>
            </tr>
            <tr>
                <td colspan="2">Member Rate Savings: {{ $money($breakdown['discounts']) }}. {{ $membershipNote }}</td>
            </tr>
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Meter Reading Details</th></tr>
            @if ($meterReading)
                <tr>
                    <td>Reading Date: {{ $readingDate?->format('F d, Y') ?? 'N/A' }}</td>
                    <td>Reading Status: {{ $meterReading->status ?? 'N/A' }}</td>
                </tr>
                <tr><td colspan="2">Encoded By: {{ $meterReading->reader?->name ?? 'N/A' }}</td></tr>
            @else
                <tr><td colspan="2">No linked meter reading record is attached to this bill.</td></tr>
            @endif
        </tbody>
        <tbody>
            <tr><th scope="rowgroup" colspan="2" class="stmt-band">Payment Summary</th></tr>
            <tr>
                <td>Verified Payments: {{ $verifiedPayments === null ? 'Not available' : $signedMoney(-$verifiedPayments) }}</td>
                <td><strong>Remaining Balance (This Bill): {{ $historicalMoney($bill->statement_balance) }}</strong></td>
            </tr>
            @if ($bill->earlier_issue_credit > 0)
                <tr><td colspan="2">Credit from earlier issue: <strong>{{ $money($bill->earlier_issue_credit) }}</strong></td></tr>
            @endif
            @if ($bill->superseded_by_bill_id)
                <tr><td colspan="2">This statement was superseded. @if ($bill->supersededBy && ! $bill->supersededBy->isNovemberProjection())<a href="{{ route('billing.show', $bill->supersededBy) }}">View its replacement</a>.@endif</td></tr>
            @elseif ($bill->is_billing_fixture)
                <tr><td colspan="2">Payment Status: <strong>{{ $bill->billingFixtureStatus() }}</strong></td></tr>
                @if (! $bill->hasBeenIssued())
                    <tr><td colspan="2">Projected balance after issue: <strong>{{ $money($bill->unsettledAmount()) }}</strong></td></tr>
                @endif
            @elseif ($isCarriedForward)
                <tr><td colspan="2"><strong>Carried forward:</strong> This historical balance moved to a later bill and is not charged twice.</td></tr>
            @else
                <tr>
                    <td colspan="2">
                        <strong>Account Payment Requirements</strong><br>
                        Payment Status: <strong>{{ $statementPaymentStatus }}</strong><br>
                        Disconnection Notice: <strong>{{ $statementHasDisconnectionNotice ? 'Active' : 'None' }}</strong><br>
                        Total Outstanding Balance: <strong>&#8369;{{ number_format($statementTotalOutstanding, 2) }}</strong><br>
                        @if ($statementHasDisconnectionNotice)
                            Required Payment Amount: <strong>&#8369;{{ number_format($statementRequiredPaymentAmount, 2) }}</strong><br>
                        @endif
                        {{ $statementPaymentRuleMessage }}
                    </td>
                </tr>
            @endif
            @unless ($bill->is_billing_fixture || $bill->superseded_by_bill_id)
            <tr><th scope="row" colspan="2" class="stmt-band">Payment Instructions</th></tr>
            <tr>
                <td colspan="2">
                    Payment Reference Number: <strong>{{ $bill->prn }}</strong><br>
                    Present this bill and the PRN at the Via-Vida Waterworks payment counter or use an authorized digital payment channel.
                    Verified payments are applied oldest-first.
                </td>
            </tr>
            @endunless
        </tbody>
        @if (! $isCarriedForward && $loanInstallments->isNotEmpty())
            <tbody>
                <tr><th scope="rowgroup" colspan="2" class="stmt-band">Loan Installments</th></tr>
                @foreach ($loanInstallments as $installment)
                    <tr>
                        <td>
                            {{ $installment->remaining_balance > 0 && $installment->bill_id !== $bill->id ? 'Unpaid ' : '' }}Month {{ $installment->installment_number }} Loan Installment ({{ $installment->status }})
                        </td>
                        <td class="stmt-number">{{ $money($installment->remaining_balance) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th scope="row">Total Loan Amount Due</th>
                    <td class="stmt-number">{{ $money($loanInstallments->sum('remaining_balance')) }}</td>
                </tr>
            </tbody>
        @endif
        @if ($previousBalanceAudit)
            <tbody>
                <tr><th scope="rowgroup" colspan="2" class="stmt-band">How the Previous Balance Was Carried Forward</th></tr>
                <tr>
                    <td colspan="2">
                        {{ $previousBalanceAudit['consumer_name'] }} had a previous bill of {{ $money($previousBalanceAudit['source_bill_amount']) }}
                        @if ($previousBalanceAudit['source_period'])
                            for {{ $previousBalanceAudit['source_period']->format('F Y') }}
                        @endif
                        and had {{ $money($previousBalanceAudit['payments_applied_before_carryover']) }} applied before
                        {{ $money($previousBalanceAudit['carried_balance']) }} was carried into the {{ $previousBalanceAudit['current_period']->format('F Y') }} bill.
                    </td>
                </tr>
                <tr>
                    <td>Previous Bill PRN: {{ $previousBalanceAudit['source_prn'] ?? 'N/A' }}</td>
                    <td>Total Verified Payments on Previous Bill: {{ $historicalMoney($previousBalanceAudit['verified_payments']) }}</td>
                </tr>
                <tr><td colspan="2">Reason: {{ $previousBalanceAudit['reason'] }}</td></tr>
            </tbody>
        @endif
    </table>
</article>
