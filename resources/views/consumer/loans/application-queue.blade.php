<div class="loan-application-list" aria-label="Loan applications">
    @forelse ($loans as $loan)
        @php
            $applicationCategory = $loan->applicationStatusCategory();
            $fullyPaid = in_array($loan->status, array_merge(\App\Models\Loan::PAYMENT_STATUSES, ['Completed']), true) && (float) $loan->amount > 0 && $loan->balance_remaining <= 0;
            $needsCorrection = $loan->income_verification_status === 'Rejected' && in_array($loan->status, \App\Models\Loan::PENDING_STATUSES, true);
            $awaitingProofVerification = in_array($loan->status, \App\Models\Loan::PENDING_STATUSES, true) && $loan->income_verification_status !== 'Verified';
            $savedDssDecision = $awaitingProofVerification ? null : $loan->latestDssEvaluation?->recommendation;
            $dssCategory = in_array($savedDssDecision, ['Approved', 'Rejected'], true) ? $savedDssDecision : ($savedDssDecision === 'Needs Review' ? 'Pending Review' : 'Not recorded');
            $incomeStatus = $loan->income_verification_status === 'Verified' ? 'Verified' : ($loan->income_verification_status === 'Rejected' ? 'Needs Correction' : 'Pending Verification');
            $statusClass = match ($applicationCategory) { 'Approved' => 'loan-status-approved', 'Rejected' => 'loan-status-rejected', default => 'loan-status-pending' };
            $visibleReviewInformation = collect($loan->latestDssEvaluation?->missing_information ?? [])->reject(
                fn ($item) => str_starts_with(mb_strtolower((string) $item), 'ocr ')
                    || str_starts_with(mb_strtolower((string) $item), 'proof of income:')
            );
        @endphp
        <article class="loan-application-card" data-application-id="{{ $loan->id }}" data-application-status="{{ $applicationCategory }}" aria-label="Loan application {{ $loan->id }}">
            @if ($needsCorrection)
                <div class="loan-correction-notice" role="note" aria-labelledby="income-correction-title-{{ $loan->id }}">
                    <h3 id="income-correction-title-{{ $loan->id }}">Proof of Income Needs Correction</h3>
                    <p>Your loan application has NOT been rejected. Please upload a new Proof of Income document to continue the verification process.</p>
                    @if (filled($loan->income_rejection_reason ?? $loan->income_verification_notes))
                        <div class="loan-correction-reason"><strong>Reason from admin:</strong> {{ $loan->income_rejection_reason ?? $loan->income_verification_notes }}</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('loans.income-proof.replace', $loan) }}" enctype="multipart/form-data">
                    @csrf
                    @include('shared.income-proof-upload', ['uploadId' => 'replacement_proof_'.$loan->id, 'uploadLabel' => 'Upload New Proof of Income'])
                    @error('proof_of_income') <div class="small text-danger mt-1" role="alert">{{ $message }}</div> @enderror
                    <button class="btn btn-sm btn-primary mt-2" type="submit">Submit Replacement</button>
                </form>
            @endif

            <section class="loan-card-section" aria-label="Application status">
                <h3 class="loan-card-section-title">Application Status</h3>
                <div class="loan-card-grid loan-card-grid-two">
                    <div class="loan-card-field"><span class="loan-card-label">Loan Status</span><span class="loan-application-status {{ $statusClass }}">{{ $fullyPaid ? 'Fully Paid' : $applicationCategory }}</span></div>
                    <div class="loan-card-field"><span class="loan-card-label">Income Verification</span><span class="loan-card-value" aria-label="Income verification: {{ $incomeStatus }}">{{ $incomeStatus }}</span></div>
                    @if (! $needsCorrection && $savedDssDecision)
                        <div class="loan-card-field"><span class="loan-card-label">DSS Review Status</span><div class="loan-card-value loan-card-details"><span>{{ $dssCategory }}</span>@if ($loan->latestDssEvaluation?->evaluated_at)<span class="small text-secondary">{{ $loan->latestDssEvaluation->evaluated_at->format('M d, Y H:i') }}</span>@endif</div></div>
                    @endif
                    @php($installationSchedule = $loan->installationSchedule())
                    @if ($installationSchedule)
                    <div class="loan-card-field"><span class="loan-card-label">Installation Schedule</span><div class="loan-card-value loan-card-details">
                        <span>{{ $installationSchedule->format('M d, Y · g:i A') }} (Philippine Time)</span>
                        @if ($loan->installation_notes)<span class="small text-secondary">{{ $loan->installation_notes }}</span>@endif
                        @php($pendingLoanReschedule = $loan->installationRescheduleRequests()->where('status', \App\Models\InstallationRescheduleRequest::STATUS_PENDING)->latest('id')->first())
                        @if ($rescheduleUsage)<x-reschedule-usage :usage="$rescheduleUsage" />@endif
                        @if ($rescheduleUsage && ! $rescheduleUsage['available'])
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Reschedule Installation</button>
                        @elseif ($pendingLoanReschedule)
                            <span class="badge bg-warning text-dark">Reschedule Pending</span>
                        @elseif ($loan->installation_status !== 'Completed' && $loan->isApprovedForInstallation())
                            <a href="{{ route('installation.reschedule.loan', $loan) }}" class="btn btn-sm btn-outline-primary">Request Reschedule</a>
                        @endif
                        <span class="small text-secondary">Installation: {{ $loan->installation_status ?? 'Not Scheduled' }}</span>
                    </div></div>
                    @endif
                </div>
            </section>
            <div class="loan-application-message">
                @if (! $needsCorrection && $applicationCategory !== 'Pending Review')
                <span>
                    @if ($fullyPaid)
                        Your loan has been fully paid. @if ($loan->status === 'Ready for Scheduling')Installation scheduling remains in progress.@endif
                    @elseif ($applicationCategory === 'Approved' && $loan->status === 'Ready for Scheduling')
                        Your loan application has been approved. Please wait for the admin to schedule the next step.
                    @elseif ($applicationCategory === 'Approved')
                        Your loan application has been approved.
                    @elseif ($applicationCategory === 'Rejected')
                        Your loan application was rejected. Review the decision details below.
                    @endif
                </span>
                @endif
                <button type="button" class="btn btn-sm btn-outline-primary loan-breakdown-button" data-bs-toggle="modal" data-bs-target="#loanBreakdownModal" data-breakdown-url="{{ route('loans.payment-breakdown', $loan) }}" aria-label="View details and payment breakdown for loan {{ $loan->id }}"><i class="bi bi-calculator me-1" aria-hidden="true"></i>View Details</button>
            </div>
            @if ($loan->agreement)
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('loans.agreement.show', $loan) }}" target="_blank" rel="noopener">View Loan Agreement</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('loans.agreement.show', ['loan' => $loan, 'download' => 1]) }}">Download PDF</a>
                </div>
            @endif
            @if ($savedDssDecision === 'Needs Review' && $visibleReviewInformation->isNotEmpty())
                <details class="small mt-2"><summary>Review status details</summary><ul class="mt-1 mb-0 ps-3">@foreach ($visibleReviewInformation as $missing)<li>{{ $missing }}</li>@endforeach</ul></details>
            @endif
            @if ($savedDssDecision === 'Approved' && $loan->latestDssEvaluation?->displayableApprovalReasons())
                <details class="small mt-2"><summary>View DSS approval reasons</summary><ul class="mt-1 mb-0 ps-3">@foreach ($loan->latestDssEvaluation->displayableApprovalReasons() as $reason)<li>{{ $reason }}</li>@endforeach</ul></details>
            @endif
            @if ($savedDssDecision === 'Rejected' && $loan->latestDssEvaluation?->displayableFailedCriteria())
                <details class="small mt-2"><summary>View DSS rejection reasons</summary><ul class="mt-1 mb-0 ps-3">@foreach ($loan->latestDssEvaluation->displayableFailedCriteria() as $criterion)<li><strong>{{ $criterion['label'] }}</strong>: {{ $criterion['explanation'] }}</li>@endforeach</ul></details>
                @if (! $loan->review_requested_at)
                    <details class="mt-2"><summary class="text-primary">Request review of incorrect information</summary>
                        <form method="POST" action="{{ route('loans.request-review', $loan) }}" class="mt-2">
                            @csrf
                            <label class="form-label small" for="review-reason-{{ $loan->id }}">Explain what information is incorrect</label>
                            <textarea class="form-control form-control-sm mb-2" id="review-reason-{{ $loan->id }}" name="review_request_reason" minlength="10" maxlength="1500" required></textarea>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Send Review Request</button>
                        </form>
                    </details>
                @else
                    <div class="small text-secondary mt-2">Review requested {{ $loan->review_requested_at->format('M d, Y') }}.</div>
                @endif
            @endif
        </article>
    @empty
        <div class="text-center py-3 text-secondary">{{ $filters['status'] !== '' || $filters['risk'] !== '' || $filters['search'] !== '' ? 'No loan applications match the selected filters.' : 'No loan applications yet.' }}</div>
    @endforelse
</div>
