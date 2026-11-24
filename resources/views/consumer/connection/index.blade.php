@extends('layouts.app')

@php
    $activePage = 'connection';
@endphp

@section('title', 'My Connection Application')

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>My Connection Application</h1>
                    <p>Track the status of your water connection request.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle"></i>
                    <div><span class="fw-bold">Success:</span> {{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @if ($consumer)
                <div class="panel-card p-4 mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="feature-icon rounded-3">
                            <i class="bi bi-droplet"></i>
                        </span>
                        <div>
                            <h3 class="mb-0">{{ $consumer->full_name }}</h3>
                            <span class="text-secondary small">{{ $consumer->email }}</span>
                        </div>
                        <div class="ms-auto">
                            <x-consumer-status-badge :consumer="$consumer" />
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Membership Type</label>
                                <div>
                                    {{ $consumer->membership_type }}
                                    @if ($consumer->hasPendingMembershipVerification())
                                        <span class="badge bg-warning text-dark ms-1">Verification pending</span>
                                    @elseif ($consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_VERIFIED)
                                        <span class="badge bg-success ms-1">Verified</span>
                                    @elseif ($consumer->membership_verification_status === \App\Models\Consumer::MEMBERSHIP_VERIFICATION_REJECTED)
                                        <span class="badge bg-danger ms-1">Claim rejected</span>
                                    @endif
                                </div>
                                @if ($consumer->hasPendingMembershipVerification())
                                    <div class="small text-secondary mt-1">
                                        Your existing VVTHOA membership is being verified with your water connection request. Member rates apply after the administrator approves it.
                                    </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Property Type</label>
                                <div>{{ $consumer->property_type ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Ownership Status</label>
                                <div>{{ $consumer->ownership_status ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Block</label>
                                <div>{{ $consumer->address_block ?? 'N/A' }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Street</label>
                                <div>{{ $consumer->address_street ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            @if ($consumer->account_number)
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Account Number</label>
                                <div class="fw-bold fs-5">{{ $consumer->account_number }}</div>
                            </div>
                            @endif
                            @if ($consumer->meter_number)
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Meter Number</label>
                                <div class="fw-bold fs-5">{{ $consumer->meter_number }}</div>
                            </div>
                            @endif
                            @if ($consumer->installation_date)
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Installation Date</label>
                                <div>{{ $consumer->installation_date->format('F d, Y') }}</div>
                            </div>
                            @endif
                            <div class="mb-3">
                                <label class="fw-semibold text-secondary small text-uppercase">Submitted On</label>
                                <div>{{ $consumer->created_at->format('F d, Y \a\t h:i A') }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($consumer->status === 'Pending Verification')
                    <div class="alert alert-info d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-info-circle mt-1"></i>
                        <div>Your application is awaiting admin review. You will receive a notification once a decision has been made.</div>
                    </div>
                    @endif

                    @if ($consumer->status === 'Pending Verification' && $consumer->resubmitted_at)
                    <div class="alert alert-info d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-arrow-repeat mt-1"></i>
                        <div>
                            <strong>Corrections submitted.</strong>
                            You resubmitted your application on
                            <strong>{{ $consumer->resubmitted_at->format('F d, Y \a\t h:i A') }}</strong>
                            and it is pending re-verification by the administrator.
                            @if ($consumer->rejection_reason)
                                <div class="small mt-1">Previous rejection reason: {{ $consumer->rejection_reason }}</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if ($consumer->reconnection_pending && $latestReconnection)
                        <div class="alert alert-info mt-3">
                            <h4 class="h6">Pending Reconnection</h4>
                            <p class="mb-2">Scheduled Reconnection Date &amp; Time: <strong>{{ \App\Support\ReconnectionSchedule::format($latestReconnection->scheduledAt($consumer)) }}</strong> (Philippine time)</p>
                            <p class="mb-2">Your water service will be marked Connected after the association confirms the physical reconnection.</p>
                            <x-reschedule-usage :usage="$reconnectionRescheduleUsage" type="reconnection" />
                            @if ($reconnectionRescheduleUsage['available'])
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('reconnection.reschedule', $latestReconnection) }}">Request Reschedule</a>
                            @endif
                        </div>
                    @elseif ($latestReconnection?->completed_at)
                        <div class="alert alert-success mt-3">
                            Reconnection completed on <strong>{{ \App\Support\ReconnectionSchedule::format($latestReconnection->completed_at) }}</strong> (Philippine time).
                        </div>
                    @elseif ($consumer->status === 'Active' && $consumer->installation_date)
                    @php
                        $pendingConnReschedule = $consumer->installationRescheduleRequests()
                            ->where('status', \App\Models\InstallationRescheduleRequest::STATUS_PENDING)
                            ->where('reschedule_type', \App\Models\InstallationRescheduleRequest::TYPE_CONNECTION)
                            ->latest('id')
                            ->first();
                        $connRescheduleHistory = $consumer->installationRescheduleRequests()
                            ->where('reschedule_type', \App\Models\InstallationRescheduleRequest::TYPE_CONNECTION)
                            ->latest('id')
                            ->get();
                        $rescheduleUsage = app(\App\Services\InstallationRescheduleService::class)->usage($consumer);
                    @endphp
                    <div class="alert alert-success d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-check-circle mt-1"></i>
                        <div class="flex-grow-1">
                            <div>
                                <strong>Installation Scheduled:</strong> Your water meter installation is scheduled for <strong>{{ $consumer->installation_date->format('F d, Y') }}</strong>.
                                @if ($consumer->installation_time)
                                    Time: {{ \Illuminate\Support\Carbon::parse($consumer->installation_time)->format('g:i A') }}.
                                @endif
                                Your account details and secure password setup link were emailed to your registered address.
                            </div>
                            <x-reschedule-usage :usage="$rescheduleUsage" />
                            @if (! $rescheduleUsage['available'])
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Reschedule Installation</button>
                            @elseif ($pendingConnReschedule)
                                <div class="mt-2 pt-2 border-top border-success-subtle">
                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Reschedule Request Pending Admin Review</span>
                                    <div class="small mt-1 text-secondary">
                                        Submitted {{ $pendingConnReschedule->created_at->format('M d, Y g:i A') }} &middot; Reason: "{{ $pendingConnReschedule->reason }}"
                                    </div>
                                </div>
                            @else
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#consumerConnectionRescheduleModal">
                                        <i class="bi bi-calendar2-range me-1"></i>Request for Reschedule
                                    </button>
                                </div>
                            @endif

                            @if ($connRescheduleHistory->count() > ($pendingConnReschedule ? 1 : 0))
                                <div class="mt-3 pt-2 border-top border-success-subtle">
                                    <a class="small text-decoration-none fw-semibold" data-bs-toggle="collapse" href="#connRescheduleHistoryCollapse" role="button">
                                        <i class="bi bi-clock-history me-1"></i>View Reschedule History ({{ $connRescheduleHistory->count() }})
                                    </a>
                                    <div class="collapse mt-2" id="connRescheduleHistoryCollapse">
                                        <div class="list-group list-group-flush border rounded-3 bg-white small">
                                            @foreach ($connRescheduleHistory as $historyItem)
                                                <div class="list-group-item py-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge {{ match($historyItem->status) {
                                                            'approved', 'assigned' => 'bg-success',
                                                            'rejected' => 'bg-danger',
                                                            default => 'bg-warning text-dark'
                                                        } }}">{{ ucfirst($historyItem->status) }}</span>
                                                        <span class="text-muted">{{ $historyItem->created_at->format('M d, Y') }}</span>
                                                    </div>
                                                    <div class="mt-1">Original: {{ $historyItem->formattedOriginalSchedule() }} @if ($historyItem->isApproved() && $historyItem->new_installation_date) &rarr; <span class="text-success fw-semibold">New: {{ $historyItem->formattedNewSchedule() }}</span> @endif</div>
                                                    <div class="text-muted">Reason: {{ $historyItem->reason }}</div>
                                                    @if ($historyItem->admin_notes)
                                                        <div class="text-secondary">Admin notes: {{ $historyItem->admin_notes }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="modal fade" id="consumerConnectionRescheduleModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 rounded-4">
                                <form method="POST" action="{{ route('installation.reschedule.connection.store', $consumer) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Request Installation Reschedule</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-light border small mb-3">
                                            <strong>Current Schedule:</strong> {{ $consumer->installation_date->format('F d, Y') }}<br>
                                            <span class="text-secondary">Only administrators can assign the new installation date. Please provide your reason for requesting a reschedule.</span>
                                        </div>
                                        <div class="mb-3">
                                            <label for="conn_reschedule_reason" class="form-label fw-semibold">Reason for Reschedule <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="conn_reschedule_reason" name="reason" rows="3" required maxlength="1000" placeholder="e.g. Unavailable on this date, work schedule conflict..." @disabled(! $rescheduleUsage['available'])></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary" style="background-color: var(--vf-royal); border-color: var(--vf-royal);" @disabled(! $rescheduleUsage['available'])>Submit Request</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if ($consumer->status === 'Rejected' && $consumer->rejection_reason)
                    <div class="alert alert-danger d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-x-circle mt-1"></i>
                        <div>
                            <strong>Application Rejected:</strong> {{ $consumer->rejection_reason }}
                        </div>
                    </div>
                    @endif

                    <x-loan-disconnection-notices :consumer="$consumer" />

                    @if ($hasBillingScenario ?? false)
                        <a href="{{ route('connection.status', $billingScenario ? ['ledger' => 'recorded'] : []) }}" class="btn btn-sm btn-outline-primary mt-3 mb-3">
                            {{ $billingScenario ? 'Recorded connection' : 'Scenario connection' }}
                        </a>
                    @endif

                    @if ($billingScenario ?? null)
                        @include('consumer.billing.scenario-notice', ['notice' => $billingScenario['notice']])
                    @elseif ($hasActiveDisconnectionNotice)
                    <div class="alert alert-danger d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-exclamation-triangle mt-1"></i>
                        <div>
                            <strong>Disconnection Notice.</strong> This account has
                            <strong>{{ $monthsUnpaid }} consecutive unpaid billing month(s)</strong>,
                            each past its due date and the {{ $graceDays }}-day grace period.
                            A disconnection notice is issued after {{ $monthsRequired }} consecutive unpaid months.
                            Outstanding balance:
                            <strong>&#8369;{{ number_format($outstandingBalance, 2) }}</strong>.
                            @if ($activeDisconnectionNotice?->deadline_at)
                                <div class="mt-1">Payment deadline <strong>{{ $activeDisconnectionNotice->deadline_at->format('F d, Y g:i A') }}</strong></div>
                                <div>{{ $activeDisconnectionNotice->paymentWindowHasElapsed() ? 'The payment deadline has passed.' : 'Please settle the full balance by this deadline.' }}</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if (in_array($consumer->status, ['Disconnected', 'For Reconnection'], true) && $paymentVerified)
                    <div class="alert alert-success d-flex align-items-start gap-2 mt-3">
                        <i class="bi bi-check-circle mt-1"></i>
                        <div>
                            <strong>Payment verified.</strong> Your full payment was verified and approved on
                            <strong>{{ $fullPaymentVerifiedAt->format('F d, Y') }}</strong>.
                            You may now submit a reconnection request.
                        </div>
                    </div>
                    @endif
                </div>

                @if (in_array($consumer->status, ['Disconnected', 'For Reconnection'], true))
                <div class="panel-card p-4 mb-4">
                    <h5 class="fw-bold mb-3">Request Reconnection</h5>

                    @if ($activeRequest)
                        <div class="alert alert-warning d-flex align-items-start gap-2">
                            <i class="bi bi-hourglass-split mt-1"></i>
                            <div>
                                <strong>Request already pending.</strong> Your reconnection request submitted on
                                {{ $activeRequest->requested_at?->format('F d, Y \a\t h:i A') }} is awaiting administrator review.
                                Please wait for the decision.
                            </div>
                        </div>
                    @elseif ($outstandingBalance > 0)
                        <div class="alert alert-secondary d-flex align-items-start gap-2">
                            <i class="bi bi-lock mt-1"></i>
                            <div>
                                <strong>Payment required first.</strong> You must fully settle your outstanding balance of
                                <strong>&#8369;{{ number_format($outstandingBalance, 2) }}</strong>
                                before a reconnection request can be submitted.
                            </div>
                        </div>
                    @elseif (! $paymentVerified)
                        <div class="alert alert-warning d-flex align-items-start gap-2">
                            <i class="bi bi-hourglass-split mt-1"></i>
                            <div>
                                <strong>Awaiting verification.</strong> Your balance is settled, but an administrator
                                must verify and approve the payment before you can request reconnection.
                            </div>
                        </div>
                    @else
                        <p class="text-secondary">
                            Your account balance is fully settled. You may submit a reconnection request.
                            Submitting a request does not restore the water supply automatically &mdash; reconnection follows the association's authorised procedure.
                        </p>
                        <button
                            type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#reconnectionRequestModal"
                        >
                            <i class="bi bi-arrow-repeat me-1"></i>Request Reconnection
                        </button>
                    @endif

                </div>
                @endif

                @if ($reconnectionHistory->isNotEmpty())
                    <section class="panel-card p-4 mb-4">
                        <h2 class="h5 fw-bold mb-3">Reconnection Request History</h2>
                        <div class="table-responsive mt-4">
                            <table class="table align-middle">
                                <thead><tr><th>Submitted</th><th>Type</th><th>Status</th><th>Proof</th><th>Reviewed</th></tr></thead>
                                <tbody>
                                    @foreach ($reconnectionHistory as $history)
                                        <tr>
                                            <td>{{ $history->requested_at?->format('F d, Y') ?? 'N/A' }}</td>
                                            <td>{{ $history->request_type }}</td>
                                            <td>
                                                <span class="badge {{ match ($history->status) {
                                                    'Pending' => 'badge-soft-warning',
                                                    'Approved' => 'badge-soft-success',
                                                    'Completed' => 'badge-soft-success',
                                                    'Rejected' => 'badge-soft-danger',
                                                    default => 'badge-soft-primary',
                                                } }}">{{ $history->status }}</span>
                                            </td>
                                            <td>
                                                @if ($history->proof_path)
                                                    <a href="{{ route('billing.requests.proof', $history) }}" target="_blank" rel="noopener">
                                                        <i class="bi bi-paperclip"></i> View proof
                                                    </a>
                                                @else
                                                    <span class="text-secondary small">N/A</span>
                                                @endif
                                            </td>
                                            <td>{{ $history->reviewed_at?->format('F d, Y') ?? 'Pending review' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if ($canRequestReconnection)
                <div class="modal fade" id="reconnectionRequestModal" tabindex="-1" aria-hidden="true" aria-labelledby="reconnectionRequestModalLabel">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0">
                            <form method="POST" action="{{ route('billing.requests.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="reconnectionRequestModalLabel">Request Reconnection</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    @if ($errors->any())
                                        <div class="alert alert-danger d-flex align-items-start gap-2">
                                            <i class="bi bi-exclamation-circle mt-1"></i>
                                            <div>{{ $errors->first() }}</div>
                                        </div>
                                    @endif
                                    <p class="text-secondary small">
                                        This form is populated from your existing account information.
                                        Submitting the request does not automatically reconnect your water supply.
                                    </p>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_name">Full Name</label>
                                            <input type="text" class="form-control" id="reconnection_name" value="{{ $consumer->full_name }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_account">Account Number</label>
                                            <input type="text" class="form-control" id="reconnection_account" value="{{ $consumer->account_number }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_meter">Meter Number</label>
                                            <input type="text" class="form-control" id="reconnection_meter" value="{{ $consumer->meter_number }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_contact">Contact Number</label>
                                            <input type="text" class="form-control" id="reconnection_contact" value="{{ $consumer->contact_number }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_block">Block</label>
                                            <input type="text" class="form-control" id="reconnection_block" value="{{ $consumer->address_block }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="reconnection_street">Street</label>
                                            <input type="text" class="form-control" id="reconnection_street" value="{{ $consumer->address_street }}" readonly>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="reconnection_information">
                                                Requested Information <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control @error('information') is-invalid @enderror" id="reconnection_information" name="information" rows="3" maxlength="1000" required placeholder="Describe your request, e.g. the date you received the reconnection confirmation and who issued it.">{{ old('information') }}</textarea>
                                            @error('information')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="reconnection_proof">
                                                Proof of Reconnection Confirmation <span class="text-danger">*</span>
                                            </label>
                                            <input type="file" class="form-control @error('proof') is-invalid @enderror" id="reconnection_proof" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                            <div class="form-text">
                                                Upload a screenshot or PDF of the reconnection confirmation email sent by the association.
                                                Accepted formats: JPG, JPEG, PNG, WEBP, PDF. Maximum size: 5 MB.
                                            </div>
                                            @error('proof')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="reconnection_notes">Notes (optional)</label>
                                            <textarea class="form-control" id="reconnection_notes" name="notes" rows="2" maxlength="1000" placeholder="Add any details for the administrator.">{{ old('notes') }}</textarea>
                                        </div>
                                        <input type="hidden" name="request_type" value="Reconnection">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @if ($errors->any())
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var el = document.getElementById('reconnectionRequestModal');
                            if (el && window.bootstrap) {
                                window.bootstrap.Modal.getOrCreateInstance(el).show();
                            }
                        });
                    </script>
                @endif
                @endif

                @if ($meterHistory->isNotEmpty())
                <div class="panel-card p-4 mb-4">
                    <h5 class="fw-bold mb-3">Meter History</h5>
                    <p class="text-secondary small">
                        Each meter ever issued to your account is kept on record. Readings are recorded against the meter
                        that was active at the time, and new readings use your current meter number
                        <strong>{{ $consumer->meter_number ?? 'N/A' }}</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Meter Number</th><th>Assigned</th><th>Status</th><th>Reason</th></tr></thead>
                            <tbody>
                                @foreach ($meterHistory as $assignment)
                                    <tr class="{{ $assignment->isActive() ? 'table-active' : '' }}">
                                        <td class="fw-semibold">{{ $assignment->meter_number }}</td>
                                        <td>{{ $assignment->assigned_at?->format('F d, Y') ?? 'N/A' }}</td>
                                        <td>
                                            @if ($assignment->isActive())
                                                <span class="badge badge-soft-success">Active</span>
                                            @else
                                                <span class="badge badge-soft-secondary">Retired {{ $assignment->retired_at?->format('F d, Y') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $assignment->reason ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @if ($consumer->valid_id_path || $consumer->proof_of_residency_path || $consumer->other_document_path)
                <div class="panel-card p-4">
                    <h5 class="fw-bold mb-3">Submitted Requirements</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($consumer->valid_id_path)
                                <tr>
                                    <td>Valid ID</td>
                                    <td><span class="badge badge-soft-success">Submitted</span></td>
                                </tr>
                                @endif
                                @if ($consumer->proof_of_residency_path)
                                <tr>
                                    <td>Proof of Residency</td>
                                    <td><span class="badge badge-soft-success">Submitted</span></td>
                                </tr>
                                @endif
                                @if ($consumer->other_document_path)
                                <tr>
                                    <td>{{ $consumer->other_document_details ?? 'Other Document' }}</td>
                                    <td><span class="badge badge-soft-success">Submitted</span></td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            @else
                <div class="panel-card p-5 text-center">
                    <div class="py-5">
                        <i class="bi bi-folder-plus" style="font-size: 4rem; color: var(--wb-muted);"></i>
                        <h4 class="mt-3">No Connection Application</h4>
                        <p class="text-secondary">You have not submitted any water connection application yet.</p>
                        <a href="{{ route('register') }}" class="btn btn-primary mt-2">Apply Now</a>
                    </div>
                </div>
            @endif
        </div>
    </main>
</div>
@endsection
