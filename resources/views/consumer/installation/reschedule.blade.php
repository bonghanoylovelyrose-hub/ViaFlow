@extends('layouts.app')

@section('title', 'Request Installation Reschedule')

@push('styles')
<style>
    .reschedule-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        background: linear-gradient(135deg, rgba(245, 250, 252, 0.92), rgba(216, 237, 255, 0.85));
    }
    .reschedule-card {
        width: min(720px, 100%);
        background: #ffffff;
        border-radius: 1.25rem;
        box-shadow: 0 20px 45px rgba(7, 84, 200, 0.12);
        border: 1px solid rgba(7, 84, 200, 0.15);
        padding: 2.25rem 2rem;
    }
    .reschedule-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        background: rgba(7, 84, 200, 0.12);
        color: var(--vf-royal);
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
</style>
@endpush

@section('body')
<div class="reschedule-page">
    <div class="reschedule-card">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <span class="reschedule-badge">
                    <i class="bi bi-calendar-event"></i>
                    {{ $isLoan ? 'Loan Installation' : 'Connection Installation' }}
                </span>
                <h1 class="h3 fw-bold mt-2 mb-1" style="color: var(--vf-navy);">Request for Reschedule</h1>
                <p class="text-secondary small mb-0">Submit a request to reschedule your upcoming installation.</p>
            </div>
            @guest
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </a>
            @endguest
        </div>

        @if ($returnToEmail)
            <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="status">
                <i class="bi bi-envelope-check fs-5 mt-1"></i>
                <div>
                    <strong>Please return to your email inbox.</strong>
                    <p class="small mb-0">
                        Your request has been saved. This page cannot reopen your email application or tab, so please switch back to your email to wait for the administrator's decision.
                    </p>
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle fs-5"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card bg-light border-0 rounded-3 p-3 mb-4">
            <div class="row g-2">
                <div class="col-sm-6">
                    <span class="text-secondary small d-block">Consumer Name</span>
                    <strong class="text-dark">{{ $consumer->full_name }}</strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-secondary small d-block">Account / Reference</span>
                    <strong class="text-dark">{{ $consumer->account_number ?: $consumer->application_reference }}</strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-secondary small d-block">{{ $isLoan ? 'Scheduled Installation Date and Time' : 'Current Scheduled Date' }}</span>
                    <strong class="text-primary fs-6">{{ $currentSchedule }}</strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-secondary small d-block">Service Address</span>
                    <span class="text-dark small">{{ $consumer->service_address ?: 'Registered service address' }}</span>
                </div>
            </div>
        </div>

        <x-reschedule-usage :usage="$rescheduleUsage" />
        @if ($loanRescheduleUnavailableReason ?? null)
            <p class="alert alert-info mb-4">{{ $loanRescheduleUnavailableReason }}</p>
        @elseif (! $rescheduleUsage['available'])
            <button type="button" class="btn btn-secondary mb-4" disabled>Reschedule Installation</button>
        @elseif ($pendingRequest)
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                <i class="bi bi-clock-history fs-5 mt-1"></i>
                <div>
                    <h6 class="fw-bold mb-1">Reschedule Request Pending Administrator Review</h6>
                    <p class="small mb-2">
                        You submitted a reschedule request on <strong>{{ $pendingRequest->created_at->format('M d, Y g:i A') }}</strong>.
                        To prevent duplicate requests, you cannot submit another request until the administrator reviews your pending submission.
                    </p>
                    <div class="bg-white rounded p-2 border border-warning small">
                        <strong>Reason provided:</strong> {{ $pendingRequest->reason }}
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ $formAction }}" class="mb-4" data-reschedule-form>
                @csrf
                <input type="hidden" name="origin" value="{{ $openedFromEmail ? 'email' : 'portal' }}">
                <div class="mb-3">
                    <label for="reschedule_reason" class="form-label fw-semibold">
                        Reason for Reschedule Request <span class="text-danger">*</span>
                    </label>
                    <textarea
                        class="form-control @error('reason') is-invalid @enderror"
                        id="reschedule_reason"
                        name="reason"
                        rows="4"
                        placeholder="Please explain why you cannot make the currently scheduled installation date (e.g. out of town, work conflict, emergency)..."
                        required
                        maxlength="1000"
                    >{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Please provide a specific reason. The administrator will review your request and set a new installation date for you.
                    </div>
                </div>

                <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        <strong>Note:</strong> Consumers cannot set their own installation date. Once submitted, only the administrator can assign and confirm the new date.
                    </span>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    @auth
                        <a href="{{ $isLoan ? route('loans.index') : route('connection.status') }}" class="btn btn-light border">Cancel</a>
                    @endauth
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" style="background-color: var(--vf-royal); border-color: var(--vf-royal);" data-reschedule-submit>
                        <i class="bi bi-send me-1"></i>Submit Reschedule Request
                    </button>
                </div>
            </form>
        @endif

        @if ($history->isNotEmpty())
            <hr class="my-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>Request History</h6>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                @foreach ($history as $req)
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1 flex-wrap gap-2">
                            <div>
                                <span class="badge {{ match($req->status) {
                                    'approved', 'assigned' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    default => 'bg-warning text-dark'
                                } }}">
                                    {{ ucfirst($req->status) }}
                                </span>
                                <span class="small text-secondary ms-2">Submitted {{ $req->created_at->format('M d, Y g:i A') }}</span>
                            </div>
                            @if ($req->reviewed_at)
                                <span class="small text-muted">Reviewed {{ $req->reviewed_at->format('M d, Y') }}</span>
                            @endif
                        </div>
                        <div class="small mt-2">
                            <strong>Original Schedule:</strong> {{ $req->formattedOriginalSchedule() }}
                        </div>
                        @if ($req->isApproved() && $req->new_installation_date)
                            <div class="small text-success">
                                <strong>Updated Schedule:</strong> {{ $req->formattedNewSchedule() }}
                            </div>
                        @endif
                        <div class="small mt-1 text-secondary">
                            <strong>Reason:</strong> {{ $req->reason }}
                        </div>
                        @if ($req->admin_notes)
                            <div class="small mt-1 text-muted">
                                <strong>Admin Notes:</strong> {{ $req->admin_notes }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@php($confirmation = session('reschedule_confirmation'))

@if ($confirmation)
    <div class="modal fade" id="rescheduleConfirmationModal" tabindex="-1" aria-labelledby="rescheduleConfirmationModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="rescheduleConfirmationModalLabel">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        Reschedule Request Submitted
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ $confirmation['message'] }}</p>
                    @if (($confirmation['origin'] ?? 'portal') === 'email')
                        <p class="small text-secondary mt-3 mb-0">
                            After clicking OK, we will show you how to return to your email. This page cannot reopen your email application or tab automatically.
                        </p>
                    @endif
                </div>
                <div class="modal-footer border-0 pt-0">
                    <a href="{{ $confirmation['destination'] }}" class="btn btn-primary px-4 fw-semibold">OK</a>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const confirmationModal = document.getElementById('rescheduleConfirmationModal');

        if (confirmationModal && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(confirmationModal, {
                backdrop: 'static',
                keyboard: false,
            }).show();
        }

        document.querySelectorAll('form[data-reschedule-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.submitting === '1') {
                    event.preventDefault();

                    return;
                }

                const submitButton = form.querySelector('[data-reschedule-submit]');

                if (!submitButton) {
                    return;
                }

                form.dataset.submitting = '1';
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Submitting...';
            });
        });
    });
</script>
@endpush
@endsection
