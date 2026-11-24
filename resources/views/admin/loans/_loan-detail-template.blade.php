@php
    $money = static fn ($value) => $value === null ? 'Not recorded' : "\u{20B1}".number_format((float) $value, 2);
    $approvedAmount = $decision === 'Approved' ? $money($application->amount) : 'Not approved';
    $decisionAt = $evaluation?->evaluated_at
        ?? ($decision === 'Approved' ? ($application->approved_at ?? $application->decision_at) : $application->decision_at);
    $incomeStatus = $application->income_verification_status ?: ($evaluation?->income_verification_status ?: 'Not recorded');
    $installationStatus = $application->installation_status ?: 'Not recorded';
    $incomeBadge = match ($application->income_verification_status) {
        'Verified' => ['Approved', 'bg-success'],
        'Rejected' => ['Rejected', 'bg-danger'],
        default => ['Pending Verification', 'bg-warning text-dark'],
    };
    $visibleReviewInformation = collect($evaluation?->missing_information ?? [])->reject(
        fn ($item) => str_starts_with(mb_strtolower((string) $item), 'ocr ')
            || str_starts_with(mb_strtolower((string) $item), 'proof of income:')
    );
    $displayableFailedCriteria = $evaluation?->displayableFailedCriteria() ?? [];
    $displayableApprovalReasons = $evaluation?->displayableApprovalReasons() ?? [];
@endphp

<div class="dss-detail-section">
    <h3>Applicant Information</h3>
    <div class="dss-detail-grid">
        <div><span class="dss-detail-label">Applicant Name</span><strong class="dss-detail-value">{{ $application->consumer?->full_name ?? 'Consumer record unavailable' }}</strong></div>
        <div><span class="dss-detail-label">Account Number</span><strong class="dss-detail-value">{{ $application->consumer?->account_number ?? 'Not recorded' }}</strong></div>
        @if ($application->applied_at)
            <div><span class="dss-detail-label">Application Date</span><strong class="dss-detail-value">{{ $application->applied_at->format('M j, Y g:i A') }}</strong></div>
        @endif
    </div>
</div>

<div class="dss-detail-section">
    <h3>Loan Financial Details</h3>
    <div class="dss-detail-subheading">Installation Cost Breakdown</div>
    <dl class="dss-detail-financial">
        <div><dt>Approved Loan Amount</dt><dd>{{ $approvedAmount }}</dd></div>
        <div><dt>Water Meter</dt><dd>{{ $money($application->material_meter_cost) }}</dd></div>
        <div><dt>Hose</dt><dd>{{ $money($application->material_hose_cost) }}</dd></div>
        <div><dt>Other Materials</dt><dd>{{ $money($application->material_other_cost) }}</dd></div>
        <div class="dss-detail-total"><dt>Total Installation Cost</dt><dd>{{ $money($application->installation_cost_total) }}</dd></div>
        <div><dt>Repayment Term</dt><dd>{{ filled($application->term_months) ? $application->term_months.' months' : 'Not recorded' }}</dd></div>
        <div><dt>Monthly Installment</dt><dd>{{ $money($application->monthly_installment) }}</dd></div>
        <div><dt>Amount Paid</dt><dd>{{ $money($application->total_repaid) }}</dd></div>
        <div><dt>Remaining Balance</dt><dd>{{ $money($application->balance_remaining) }}</dd></div>
    </dl>
</div>

<div class="dss-detail-section">
    <h3>Application Status</h3>
    <div class="dss-detail-grid">
        <div><span class="dss-detail-label">Approval or Rejection Status</span><strong class="dss-detail-value">{{ $decision }}</strong></div>
        @if ($evaluation?->recommendation)
            <div><span class="dss-detail-label">DSS Review Status</span><strong class="dss-detail-value">{{ $evaluation->recommendation }}</strong></div>
        @endif
        <div><span class="dss-detail-label">Income Verification Status</span><strong class="dss-detail-value">{{ $incomeStatus }}</strong></div>
        @if ($decisionAt)
            <div><span class="dss-detail-label">{{ $decision === 'Rejected' ? 'Rejection Date' : 'Approval Date' }}</span><strong class="dss-detail-value">{{ $decisionAt->format('M j, Y g:i A') }}</strong></div>
        @endif
        @if ($decision === 'Rejected')
            <div class="dss-detail-reason"><span class="dss-detail-label">Rejection Reason</span>
                @if ($displayableFailedCriteria !== [])
                    <ul class="dss-detail-list">@foreach ($displayableFailedCriteria as $criterion)<li><strong>{{ $criterion['label'] }}</strong>: {{ $criterion['explanation'] }}</li>@endforeach</ul>
                @elseif (filled($application->review_notes) || filled($application->recommendation_notes))
                    <span class="dss-detail-value">{{ $application->review_notes ?: $application->recommendation_notes }}</span>
                @else
                    <span class="dss-detail-value">Not recorded</span>
                @endif
            </div>
        @endif
        @if ($evaluation?->recommendation === 'Approved' && $displayableApprovalReasons !== [])
            <div class="dss-detail-reason"><span class="dss-detail-label">DSS Approval Reasons</span><ul class="dss-detail-list">@foreach ($displayableApprovalReasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>
        @endif
    </div>
</div>

@include('shared.loans.dss-payment-evidence')

<div class="dss-detail-section">
    <h3>Installation Information</h3>
    <div class="dss-detail-grid">
        <div><span class="dss-detail-label">Assigned Meter Number</span><strong class="dss-detail-value">{{ $application->consumer?->meter_number ?: 'Not assigned' }}</strong></div>
        @if ($application->installation_date)
            <div><span class="dss-detail-label">Installation Date</span><strong class="dss-detail-value">{{ $application->installation_date->format('M j, Y') }}</strong></div>
        @endif
        @if ($application->installation_time)
            <div><span class="dss-detail-label">Installation Time</span><strong class="dss-detail-value">{{ \Illuminate\Support\Carbon::createFromFormat('H:i:s', strlen((string) $application->installation_time) === 5 ? $application->installation_time.':00' : (string) $application->installation_time)->format('g:i A') }}</strong></div>
        @endif
        <div><span class="dss-detail-label">Installation Status</span><strong class="dss-detail-value">{{ $installationStatus }}</strong></div>
        @if (filled($application->installation_notes))
            <div><span class="dss-detail-label">Installation Notes</span><strong class="dss-detail-value">{{ $application->installation_notes }}</strong></div>
        @endif
    </div>
</div>

@if (($manageInstallation ?? false) && ($application->agreement || ($application->approved_at && $application->applicationStatusCategory() === 'Approved')))
    <div class="dss-detail-section">
        <h3>Installment Schedule and Loan Agreement</h3>
        @if ($application->agreement)
            <p>Schedule released {{ $application->agreement->issued_at->format('M j, Y g:i A') }}.</p>
            <p>{{ match ($application->agreement->email_status) {
                \App\Models\LoanAgreement::SENT => 'Agreement email sent.',
                \App\Models\LoanAgreement::FAILED => 'Agreement email delivery failed.',
                \App\Models\LoanAgreement::SENDING => 'Agreement email delivery in progress.',
                default => 'Agreement email pending.',
            } }}</p>
            @if ($application->agreement->email_status === \App\Models\LoanAgreement::FAILED)
                <form method="POST" action="{{ route('loans.agreement.retry-email', $application) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">Retry Agreement Email</button>
                </form>
            @elseif ($application->agreement->email_status === \App\Models\LoanAgreement::PENDING)
                <form method="POST" action="{{ route('loans.agreement.release', $application) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">Send Agreement Email</button>
                </form>
            @endif
        @elseif ($application->terms_agreed_at && filled($application->terms_version) && filled($application->terms_text))
            <p>Release the saved repayment schedule and email it with the accepted agreement.</p>
            <form method="POST" action="{{ route('loans.agreement.release', $application) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Release Installment Schedule</button>
            </form>
        @else
            <p>No saved agreement acceptance is available for this loan.</p>
        @endif
    </div>
@endif

@if ($visibleReviewInformation->isNotEmpty())
    <div class="dss-detail-section">
        <h3>Information requiring review</h3>
        <ul class="dss-detail-list">@foreach ($visibleReviewInformation as $missing)<li>{{ $missing }}</li>@endforeach</ul>
    </div>
@endif


<div class="dss-detail-section">
    <h3>Proof of Income</h3>
    @if (filled($application->proof_of_income_path))
        <div class="dss-proof-preview mb-3">
            @if (strtolower(pathinfo($application->proof_of_income_path, PATHINFO_EXTENSION)) === 'pdf')
                <iframe src="{{ route('loans.income-proof', $application) }}" title="Proof of income PDF" style="display:block;width:100%;height:260px;border:1px solid #dee2e6;border-radius:.5rem"></iframe>
                <a href="{{ route('loans.income-proof', $application) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2">Open original</a>
            @else
                <a href="{{ route('loans.income-proof', $application) }}" target="_blank" rel="noopener" aria-label="Open original proof of income"><img src="{{ route('loans.income-proof', $application) }}" alt="Uploaded proof of income"></a>
                <a href="{{ route('loans.income-proof', $application) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2">Open original</a>
            @endif
        </div>
    @else
        <p class="small text-secondary mb-3">No uploaded proof of income is available.</p>
    @endif
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="dss-detail-label">Verification Status:</span>
        <span class="badge {{ $incomeBadge[1] }}">{{ $incomeBadge[0] }}</span>
    </div>
</div>

