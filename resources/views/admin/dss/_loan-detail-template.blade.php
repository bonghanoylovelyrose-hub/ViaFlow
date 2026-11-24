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

@if (($manageInstallation ?? false) && $decision === 'Approved' && $application->consumer)
    <div class="dss-detail-section">
        <h3>Assign Meter Number</h3>
        @if ((string) session('meter_assignment_loan_id') === (string) $application->id && $errors->any())
            <div class="alert alert-danger py-2" role="alert">Please correct the meter assignment details below.</div>
        @endif
        <form method="POST" action="{{ route('consumers.assign-meter', $application->consumer) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="return_to_loans" value="1">
            <input type="hidden" name="loan_id" value="{{ $application->id }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label" for="meter-number-{{ $application->id }}">Meter Number</label>
                    <input class="form-control @if ((string) session('meter_assignment_loan_id') === (string) $application->id && $errors->has('meter_number')) is-invalid @endif" id="meter-number-{{ $application->id }}" name="meter_number" type="text" maxlength="50" value="{{ old('meter_number', $application->consumer->meter_number) }}" required>
                    @if ((string) session('meter_assignment_loan_id') === (string) $application->id)
                        @error('meter_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="meter-reason-{{ $application->id }}">Reason (optional)</label>
                    <input class="form-control @if ((string) session('meter_assignment_loan_id') === (string) $application->id && $errors->has('reason')) is-invalid @endif" id="meter-reason-{{ $application->id }}" name="reason" type="text" maxlength="255" value="{{ old('reason') }}">
                    @if ((string) session('meter_assignment_loan_id') === (string) $application->id)
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Save Meter Number</button></div>
            </div>
        </form>
    </div>
@endif

@if (($manageInstallation ?? false) && $decision === 'Approved')
    @php($pendingReschedule = $application->installationRescheduleRequests()->pending()->latest('id')->first())
    @if ($pendingReschedule)
        <div class="dss-detail-section">
            <h3>Rescheduling Request</h3>
            <p>{{ $pendingReschedule->reason }}</p>
            <form method="POST" action="{{ route('installation-reschedules.assign', $pendingReschedule) }}">
                @csrf @method('PATCH')
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label" for="reschedule-date-{{ $application->id }}">New installation date</label><input class="form-control" type="date" id="reschedule-date-{{ $application->id }}" name="new_installation_date" required></div>
                    <div class="col-sm-6"><label class="form-label" for="reschedule-time-{{ $application->id }}">New installation time</label><input class="form-control" type="time" id="reschedule-time-{{ $application->id }}" name="new_installation_time" required></div>
                    <div class="col-12"><label class="form-label" for="reschedule-notes-{{ $application->id }}">Notes</label><textarea class="form-control" id="reschedule-notes-{{ $application->id }}" name="admin_notes" maxlength="1000"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Assign New Schedule</button></div>
                </div>
            </form>
        </div>
    @endif
    @if ($application->installation_date && $application->installation_time && $application->installation_status !== 'Completed' && ! $pendingReschedule)
        <div class="dss-detail-section">
            <a class="btn btn-outline-primary" href="{{ route('loans.index', ['loan' => $application->id]) }}">Manage Installation</a>
        </div>
    @endif
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

@if (($manageInstallation ?? false) && $decision === 'Approved' && $application->status === 'Ready for Scheduling')
    <div class="dss-detail-section">
        <h3>Installation schedule</h3>
        <form method="POST" action="{{ route('loans.installation-schedule.update', $application) }}">
            @csrf
            @method('PATCH')
            
            <div class="row g-3 mb-3">
                <div class="col-sm-6"><label class="form-label" for="schedule-date-{{ $application->id }}">Installation date</label><input class="form-control" type="date" id="schedule-date-{{ $application->id }}" name="installation_date" value="{{ $application->installation_date?->format('Y-m-d') }}" required></div>
                <div class="col-sm-6"><label class="form-label" for="schedule-time-{{ $application->id }}">Installation time</label><input class="form-control" type="time" id="schedule-time-{{ $application->id }}" name="installation_time" value="{{ $application->installation_time ? substr((string) $application->installation_time, 0, 5) : '' }}" required></div>
                <div class="col-12"><label class="form-label" for="schedule-notes-{{ $application->id }}">Notes</label><textarea class="form-control" id="schedule-notes-{{ $application->id }}" name="installation_notes" maxlength="1000">{{ $application->installation_notes }}</textarea></div>
            </div>
            <button class="btn btn-primary" type="submit">{{ $application->installation_status === 'Scheduled' ? 'Update Schedule' : 'Schedule Installation' }}</button>
        </form>
    </div>
@endif
