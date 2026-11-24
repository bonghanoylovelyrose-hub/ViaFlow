@extends('layouts.app')

@section('title', isset($missingRequirements) ? 'Submit Connection Requirements' : 'Edit and Resubmit Application')

@php
    $editable = fn (string $field): bool => in_array($field, $correctionFields, true);
    $hasDocument = fn (string $field): bool => filled($documentUrls[$field] ?? null);
@endphp

@push('styles')
<style>
    .resubmit-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.88), rgba(216, 237, 255, 0.78)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }

    .resubmit-shell {
        width: min(820px, 100%);
        background: rgba(255, 255, 255, 0.96);
        border-radius: 1.6rem;
        box-shadow: 0 28px 75px rgba(50, 165, 31, 0.18);
        padding: 2.25rem 2rem;
    }

    .resubmit-head h1 {
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--vf-navy);
        margin-bottom: 0.35rem;
    }

    .resubmit-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.1);
        color: #b91c1c;
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 0.9rem;
    }

    .resubmit-section {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 1rem;
        background: #fbfdfe;
        padding: 1.1rem;
        margin-bottom: 1rem;
    }

    .resubmit-section-title {
        font-weight: 700;
        color: var(--vf-navy);
        margin-bottom: 0.35rem;
    }

    .resubmit-field-locked {
        border: 1px dashed rgba(7, 84, 200, 0.85);
        border-radius: 0.65rem;
        background: #f1f5f9;
        padding: 0.65rem 0.85rem;
        color: var(--vf-navy);
        overflow-wrap: anywhere;
    }

    .resubmit-needs-fix {
        border: 1px solid rgba(220, 38, 38, 0.35);
        border-radius: 0.85rem;
        background: #fff7f7;
        padding: 0.85rem;
    }

    .resubmit-required-badge {
        display: inline-flex;
        align-items: center;
        margin-left: 0.45rem;
        padding: 0.16rem 0.5rem;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .resubmit-fix-list {
        margin: 0.5rem 0 0 1.1rem;
        padding: 0;
        color: #7f1d1d;
    }

    .resubmit-fix-list li {
        margin-bottom: 0.25rem;
    }

    .resubmit-primary {
        height: 3.35rem;
        border: 0;
        border-radius: 0.85rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        box-shadow: 0 14px 28px rgba(7, 84, 200, 0.22);
    }
</style>
@endpush

@section('body')
<div class="resubmit-page">
    <div class="resubmit-shell">
        @if (isset($missingRequirements))
            <div class="resubmit-head mb-4">
                <h1>Submit Connection Requirements</h1>
                <p class="text-secondary">Application reference <strong>{{ $consumer->application_reference }}</strong></p>
            </div>
            @if ($consumer->status === \App\Models\Consumer::STATUS_PENDING_REQUIREMENTS)
                <p>Upload the remaining documents for your in-person connection application.</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ $requirementsUrl }}" enctype="multipart/form-data">
                    @csrf
                    @if ($missingRequirements['validId'])
                        <div class="resubmit-section row g-3 mx-0">
                            @if (blank($consumer->valid_id_presented))
                                @include('auth.connection-valid-id-type')
                            @endif
                            @include('auth.connection-valid-id-upload')
                        </div>
                    @endif
                    @if ($missingRequirements['membershipDocument'])
                        <div class="resubmit-section">
                            @include('auth.connection-member-documents', [
                                'membershipDocumentHeading' => 'VVTHOA Membership Document',
                                'showOtherDocument' => false,
                                'selectedDocumentType' => $consumer->membership_document_type,
                            ])
                        </div>
                    @endif
                    <button type="submit" class="btn resubmit-primary text-white w-100">Submit Requirements</button>
                </form>
            @else
                <div class="alert alert-info" role="status">
                    {{ session('success', 'This application is no longer awaiting requirements.') }}
                    <div class="mt-2">{{ $consumer->status }}</div>
                </div>
            @endif
        @elseif ($submitted)
            <div class="text-center mb-4">
                <span class="resubmit-pill" style="background: rgba(7, 84, 200, 0.12); color: var(--vf-royal);">
                    <i class="bi bi-send-check"></i> Resubmitted
                </span>
                <h1 class="h3 fw-bold text-dark">Your application is pending re-verification</h1>
                <p class="text-secondary mb-0">
                    Application Reference: <strong>{{ $consumer->application_reference }}</strong>
                </p>
            </div>

            <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-check-circle mt-1"></i>
                <div>
                    @if (session('success'))
                        {{ session('success') }}
                    @else
                        Your corrections have been submitted to the administrator for re-verification.
                    @endif
                </div>
            </div>

            @if ($consumer->status === 'Pending Verification')
                <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                            <span class="fw-semibold">Status</span>
                            <x-consumer-status-badge :consumer="$consumer" />
                        </div>
                        @if ($consumer->resubmitted_at)
                            Corrections were resubmitted on {{ $consumer->resubmitted_at->format('F d, Y g:i A') }}.
                        @endif
                        You will receive an email once the administrator makes a new decision.
                    </div>
                </div>
            @else
                <div class="alert alert-secondary d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        <div class="mb-1">This correction link is no longer active. Current application status:</div>
                        <x-consumer-status-badge :consumer="$consumer" />
                    </div>
                </div>
            @endif

            @if ($consumer->rejection_reason)
                <div class="resubmit-section">
                    <div class="resubmit-section-title">Previous Rejection Reason</div>
                    <div class="text-secondary">{{ $consumer->rejection_reason }}</div>
                </div>
            @endif

            <p class="text-center text-secondary mt-4 mb-0">
                Need help? Contact {{ config('app.name', 'ViaFlow System') }} support and mention reference
                <strong>{{ $consumer->application_reference }}</strong>.
            </p>
        @else
            <div class="resubmit-head mb-4">
                <span class="resubmit-pill"><i class="bi bi-pencil-square"></i> Application Rejected</span>
                <h1>Edit and Resubmit Application</h1>
                <p class="text-secondary mb-1">
                    Application Reference: <strong>{{ $consumer->application_reference }}</strong>
                    @if ($consumer->rejected_at)
                        &middot; Rejected on {{ $consumer->rejected_at->format('F d, Y g:i A') }}
                    @endif
                </p>
                <p class="text-secondary mb-0">
                    Correct only the requirements the administrator identified. Everything else, including your valid
                    uploaded documents, is kept unchanged.
                </p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>
                        <div class="fw-semibold">Please correct the following before resubmitting:</div>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="alert alert-danger" role="alert">
                <div class="fw-bold mb-1"><i class="bi bi-x-circle me-1"></i>Administrator's Rejection Reason</div>
                <div>{{ $consumer->rejection_reason }}</div>
                @if (! empty($correctionLabels))
                    <hr>
                    <div class="fw-bold mb-1">Fields or documents that require correction</div>
                    <ul class="resubmit-fix-list">
                        @foreach ($correctionLabels as $label)
                            <li>{{ $label }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <form method="POST" action="{{ $correctionAction }}" enctype="multipart/form-data">
                @csrf

                <div class="resubmit-section">
                    <div class="resubmit-section-title">Applicant Information</div>
                    <p class="text-secondary small mb-3">Fields marked <span class="resubmit-required-badge">Needs correction</span> are editable. All other fields are shown as-is and will be kept unchanged.</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="full_name">Full Name</label>
                            @if ($editable('full_name'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $consumer->full_name) }}" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->full_name }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">Email Address</label>
                            @if ($editable('email'))
                                <div class="resubmit-needs-fix">
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $consumer->email) }}" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->email ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="contact_number">Contact Number</label>
                            @if ($editable('contact_number'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" autocomplete="tel-national" class="form-control js-digits-only @error('contact_number') is-invalid @enderror" id="contact_number" name="contact_number" value="{{ old('contact_number', $consumer->contact_number) }}" placeholder="09XXXXXXXXX" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    <div class="form-text">Numbers only, 10 to 15 digits.</div>
                                    @error('contact_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->contact_number ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="service_purok">Purok</label>
                            @if ($editable('service_purok'))
                                <div class="resubmit-needs-fix">
                                    <select class="form-select @error('service_purok') is-invalid @enderror" id="service_purok" name="service_purok" required>
                                        <option value="">Select your Purok</option>
                                        @foreach ($serviceArea['puroks'] as $purok)
                                            <option value="{{ $purok }}" @selected(old('service_purok', $servicePurok) === $purok)>{{ $purok }}</option>
                                        @endforeach
                                    </select>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('service_purok') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $servicePurok ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="address_block">Block</label>
                            @if ($editable('address_block'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" class="form-control @error('address_block') is-invalid @enderror" id="address_block" name="address_block" value="{{ old('address_block', $consumer->address_block) }}" placeholder="e.g. Block 12" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('address_block') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->address_block ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="address_street">Street</label>
                            @if ($editable('address_street'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" class="form-control @error('address_street') is-invalid @enderror" id="address_street" name="address_street" value="{{ old('address_street', $consumer->address_street) }}" placeholder="e.g. Maharlika Street" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('address_street') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->address_street ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="resubmit-field-locked small">
                                Country: {{ $serviceArea['country'] }} &middot; City: {{ $serviceArea['city'] }}
                                &middot; Barangay/Subdivision: {{ $serviceArea['barangay_subdivision'] }}
                                &middot; Membership: {{ $consumer->requested_membership_type ?: $consumer->membership_type }}
                                &middot; Service Address: {{ $consumer->service_address ?: 'Not provided' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="resubmit-section">
                    <div class="resubmit-section-title">Connection Details</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="property_type">Type of Property</label>
                            @if ($editable('property_type'))
                                <div class="resubmit-needs-fix">
                                    <select class="form-select @error('property_type') is-invalid @enderror" id="property_type" name="property_type" required>
                                        <option value="">Select property type</option>
                                        <option value="Residential" @selected(old('property_type', $consumer->property_type) === 'Residential')>Residential</option>
                                        <option value="Commercial" @selected(old('property_type', $consumer->property_type) === 'Commercial')>Commercial</option>
                                    </select>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('property_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->property_type ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="ownership_status">Ownership Status</label>
                            @if ($editable('ownership_status'))
                                <div class="resubmit-needs-fix">
                                    <select class="form-select @error('ownership_status') is-invalid @enderror" id="ownership_status" name="ownership_status" required>
                                        <option value="">Select ownership status</option>
                                        <option value="Owner" @selected(old('ownership_status', $consumer->ownership_status) === 'Owner')>Owner</option>
                                        <option value="Tenant" @selected(old('ownership_status', $consumer->ownership_status) === 'Tenant')>Tenant</option>
                                    </select>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('ownership_status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->ownership_status ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="valid_id_presented">Valid ID Presented</label>
                            @if ($editable('valid_id_presented'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" class="form-control @error('valid_id_presented') is-invalid @enderror" id="valid_id_presented" name="valid_id_presented" value="{{ old('valid_id_presented', $consumer->valid_id_presented) }}" placeholder="Example: National ID, Driver's License" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('valid_id_presented') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->valid_id_presented ?: 'Not provided' }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="other_document_details">Other Document Details</label>
                            @if ($editable('other_document_details'))
                                <div class="resubmit-needs-fix">
                                    <input type="text" class="form-control @error('other_document_details') is-invalid @enderror" id="other_document_details" name="other_document_details" value="{{ old('other_document_details', $consumer->other_document_details) }}" maxlength="255" placeholder="Describe the document you are submitting" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('other_document_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked">{{ $consumer->other_document_details ?: 'Not provided' }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="resubmit-section">
                    <div class="resubmit-section-title">Upload Requirements</div>
                    <p class="text-secondary small mb-3">Accepted formats: JPG, JPEG, PNG, or PDF. Only the documents marked for correction can be replaced; all other uploaded documents remain on file.</p>

                    @if ($editable('membership_document_photos'))
                        @include('auth.connection-member-documents', [
                            'membershipDocumentHeading' => 'VVTHOA Membership Document Photos',
                            'showOtherDocument' => false,
                            'selectedDocumentType' => $consumer->membership_document_type,
                        ])
                    @endif

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Valid ID</label>
                            @if ($hasDocument('valid_id_file'))
                                <div class="mb-2 small">
                                    Existing file on file:
                                    <a href="{{ $documentUrls['valid_id_file'] }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> View current Valid ID</a>
                                </div>
                            @else
                                <div class="mb-2 small text-secondary fst-italic">No Valid ID on file.</div>
                            @endif
                            @if ($editable('valid_id_file'))
                                <div class="resubmit-needs-fix">
                                    <input type="file" class="form-control @error('valid_id_file') is-invalid @enderror" id="valid_id_file" name="valid_id_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    <div class="form-text">Upload a replacement copy; the previous file will be replaced.</div>
                                    @error('valid_id_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked small">This document is kept unchanged.</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Proof of Ownership/Residency</label>
                            @if ($hasDocument('proof_of_residency_file'))
                                <div class="mb-2 small">
                                    Existing file on file:
                                    <a href="{{ $documentUrls['proof_of_residency_file'] }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> View current Proof of Ownership/Residency</a>
                                </div>
                            @else
                                <div class="mb-2 small text-secondary fst-italic">No Proof of Ownership/Residency on file.</div>
                            @endif
                            @if ($editable('proof_of_residency_file'))
                                <div class="resubmit-needs-fix">
                                    <input type="file" class="form-control @error('proof_of_residency_file') is-invalid @enderror" id="proof_of_residency_file" name="proof_of_residency_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    <div class="form-text">Upload a replacement document showing you own or reside at the service address.</div>
                                    @error('proof_of_residency_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked small">This document is kept unchanged.</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Other Document</label>
                            @if ($hasDocument('other_document_file'))
                                <div class="mb-2 small">
                                    Existing file on file:
                                    <a href="{{ $documentUrls['other_document_file'] }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> View current Other Document</a>
                                </div>
                            @else
                                <div class="mb-2 small text-secondary fst-italic">No other document on file.</div>
                            @endif
                            @if ($editable('other_document_file'))
                                <div class="resubmit-needs-fix">
                                    <input type="file" class="form-control @error('other_document_file') is-invalid @enderror" id="other_document_file" name="other_document_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <span class="resubmit-required-badge mt-2">Needs correction</span>
                                    @error('other_document_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                <div class="resubmit-field-locked small">This document is kept unchanged.</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary small" role="alert">
                    <i class="bi bi-shield-check me-1"></i>
                    Fields and documents without the <strong>Needs correction</strong> tag cannot be changed. Your existing
                    application information and valid uploads are preserved.
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-light border" style="height: 3.35rem; border-radius: 0.85rem; display: inline-flex; align-items: center;">Cancel</a>
                    <button type="submit" class="btn resubmit-primary text-white flex-fill">
                        <i class="bi bi-send me-1"></i>Submit Corrections
                    </button>
                </div>
            </form>

            @if ($linkExpiresAt)
                <p class="text-center text-secondary small mt-3 mb-0">
                    For your security, this correction link expires on {{ $linkExpiresAt }}.
                </p>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-digits-only').forEach(function (input) {
            input.addEventListener('input', function () {
                const cleaned = input.value.replace(/[^0-9]/g, '').slice(0, 15);

                if (cleaned !== input.value) {
                    input.value = cleaned;
                }
            });
        });
    });
</script>
@endpush
