@extends('layouts.app')

@section('title', 'Connection Request')

@php
    $serviceArea = $serviceArea ?? config('service_area');
@endphp

@push('styles')
<style>
    .role-register-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.88), rgba(216, 237, 255, 0.78)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }

    .role-register-shell {
        width: min(880px, 100%);
        min-height: 0;
        background: rgba(255, 255, 255, 0.94);
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 28px 75px rgba(50, 165, 31, 0.18);
    }

    .role-register-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.75rem;
        background: #ffffff;
    }

    .role-register-card {
        width: 100%;
    }

    .role-register-card h2 {
        font-size: clamp(1.35rem, 2.5vw, 1.7rem);
        font-weight: 750;
        margin-bottom: 0.35rem;
        color: var(--vf-navy);
        text-align: center;
    }

    .role-register-subtitle {
        color: var(--vf-navy);
        margin-bottom: 1.1rem;
        text-align: center;
    }

    .role-register-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .role-register-step-pill {
        display: flex;
        width: max-content;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: rgba(7, 84, 200, 0.1);
        color: var(--vf-royal);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin: 0 auto 0.75rem;
    }

    .role-register-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.9rem;
        border-radius: 999px;
        background: rgba(7, 84, 200, 0.1);
        color: var(--vf-royal);
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .role-register-change,
    .role-register-footer-link {
        color: var(--vf-royal);
        font-weight: 600;
        text-decoration: none;
    }

    .role-register-change:hover,
    .role-register-footer-link:hover {
        color: var(--vf-navy);
        text-decoration: underline;
    }

    .role-register-label {
        font-weight: 600;
        margin-bottom: 0.55rem;
        color: var(--vf-navy);
    }

    .role-register-input,
    .role-register-select {
        height: 2.75rem;
        border-radius: 0.85rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        padding-inline: 1rem;
        font-size: 0.9rem;
        box-shadow: none;
    }

    .role-register-textarea {
        border-radius: 0.85rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        padding: 0.9rem 1rem;
        font-size: 0.98rem;
        box-shadow: none;
    }

    .role-register-input:focus,
    .role-register-select:focus,
    .role-register-textarea:focus {
        border-color: rgba(7, 84, 200, 0.45);
        box-shadow: 0 0 0 0.22rem rgba(7, 84, 200, 0.12);
    }

    .role-register-primary {
        height: 3rem;
        border: 0;
        border-radius: 0.85rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        box-shadow: 0 14px 28px rgba(7, 84, 200, 0.22);
        transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
    }

    .role-register-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(7, 84, 200, 0.26);
        opacity: 0.98;
    }

    .role-register-secondary {
        height: 3rem;
        border-radius: 0.85rem;
        font-weight: 600;
    }

    .role-register-footer {
        text-align: center;
        margin-top: 1rem;
    }

    .role-register-section {
        padding: 0.9rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 1rem;
        background: #fbfdfe;
    }

    .role-register-section-title {
        font-weight: 700;
        color: var(--vf-navy);
        margin-bottom: 0.25rem;
    }

    .role-register-section-copy {
        color: var(--vf-navy);
        font-size: 0.84rem;
        line-height: 1.45;
        margin-bottom: 0.7rem;
    }

    .role-register-progress {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.1rem;
    }

    .role-register-progress::before {
        content: "";
        position: absolute;
        top: 1rem;
        left: calc(25% + 1rem);
        right: calc(25% + 1rem);
        height: 0.24rem;
        border-radius: 999px;
        background: rgba(216, 237, 255, 0.95);
    }

    .role-register-progress-fill {
        position: absolute;
        top: 1rem;
        left: calc(25% + 1rem);
        height: 0.24rem;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        transition: width 0.2s ease;
    }

    .role-register-progress-item {
        position: relative;
        z-index: 1;
        flex: 1 1 0;
        text-align: center;
    }

    .role-register-progress-marker {
        width: 2rem;
        height: 2rem;
        margin: 0 auto 0.65rem;
        border-radius: 999px;
        border: 2px solid rgba(216, 237, 255, 0.95);
        background: #ffffff;
        color: var(--vf-navy);
        display: grid;
        place-items: center;
        font-size: 0.92rem;
        font-weight: 700;
        transition: border-color 0.18s ease, background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
    }

    .role-register-progress-item.is-active .role-register-progress-marker,
    .role-register-progress-item.is-complete .role-register-progress-marker {
        border-color: var(--vf-royal);
        background: var(--vf-royal);
        color: #ffffff;
        box-shadow: 0 0 0 0.22rem rgba(7, 84, 200, 0.12);
    }

    .role-register-progress-title {
        display: block;
        font-weight: 700;
        color: var(--vf-navy);
        margin-bottom: 0.2rem;
    }

    .role-register-step-panel[hidden] {
        display: none !important;
    }

    .role-register-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.1rem;
    }

    .role-register-actions .btn {
        height: 3rem;
    }

    @media (max-width: 991.98px) {
        .role-register-panel {
            padding: 2rem;
        }
    }

    @media (max-width: 575.98px) {
        .role-register-page {
            padding: 1rem;
        }

        .role-register-meta {
            flex-direction: column;
            align-items: flex-start;
        }

        .role-register-progress {
            gap: 0.5rem;
        }

        .role-register-progress::before {
            left: calc(25% + 1rem);
            right: calc(25% + 1rem);
        }

        .role-register-progress-fill {
            left: calc(25% + 1rem);
        }

        .role-register-actions {
            flex-direction: column;
        }

        .role-register-panel {
            padding: 1.35rem 1rem;
        }

        .role-register-shell {
            border-radius: 1rem;
        }
    }
</style>
@include('auth.application-styles')
@endpush

@section('body')
@component('auth.application-shell', ['pageClass' => 'role-register-page', 'shellClass' => 'role-register-shell'])
        <section class="role-register-panel">
            <div class="role-register-card">
                <div class="role-register-step-pill">
                    <i class="bi bi-signpost-split"></i>
                    {{ $selectedMembershipType === 'Member' ? 'Step 3 of 3' : 'Step 2 of 2' }}
                </div>
                <h2>Request Connection</h2>
                <p class="role-register-subtitle">Fill out the details below so the admin can review your new water connection request.</p>

                <div class="role-register-meta">
                    <span class="role-register-badge">
                        <i class="bi bi-person-badge"></i>
                        {{ strtoupper($selectedMembershipType) }} NEW CONNECTION
                    </span>
                    <a href="{{ route('register') }}" class="role-register-change">Change selection</a>
                </div>

                <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>Submit your new connection request. Once approved, you will receive an email with your installation schedule.</div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" data-registration-draft="connection">
                    @csrf
                    <div class="role-register-progress">
                        <div class="role-register-progress-fill" data-progress-fill></div>
                        <div class="role-register-progress-item is-active" data-progress-step="1">
                            <span class="role-register-progress-marker">1</span>
                            <span class="role-register-progress-title">Applicant Information</span>
                        </div>
                        <div class="role-register-progress-item" data-progress-step="2">
                            <span class="role-register-progress-marker">2</span>
                            <span class="role-register-progress-title">Connection Details</span>
                        </div>
                    </div>

                    <div class="role-register-step-panel" data-step-panel="1">
                        <div class="role-register-section mb-3">
                            <div class="role-register-section-title">Applicant Information</div>
                            <div class="role-register-section-copy">Use your current contact details so the admin can reach you once your request is reviewed.</div>
                            @include('auth.connection-applicant-fields')
                        </div>
                    </div>

                    <div class="role-register-step-panel" data-step-panel="2" hidden>
                        <div class="role-register-section mb-3">
                            <div class="role-register-section-title">Connection Details</div>
                            <div class="role-register-section-copy">Tell us the property type and what ID you will present for verification.</div>
                            <div class="row g-3">
                                @include('auth.connection-property-fields')
                                @include('auth.connection-valid-id-type')
                                <div class="col-md-6">
                                    <label class="form-label role-register-label">Membership Type</label>
                                    <input type="text" class="form-control role-register-input" value="{{ $selectedMembershipType }}" readonly>
                                </div>
                                @include('auth.connection-valid-id-upload')
                            </div>
                        </div>

                        @if ($selectedMembershipType === 'Non-Member')
                            @include('auth.connection-nonmember-acknowledgment')
                        @else
                            <div class="alert alert-info mb-4">
                                Your existing VVTHOA membership details were submitted for verification in the previous step.
                                Submit this form to continue your new connection request.
                                Member rates and benefits apply only after the administrator verifies the membership.
                            </div>
                        @endif
                    </div>

                    <div class="role-register-actions">
                        <button type="button" class="btn btn-light border role-register-secondary flex-fill" data-step-action="back" hidden>Back</button>
                        <button type="button" class="btn role-register-primary text-white flex-fill" data-step-action="next">Continue</button>
                        <button type="submit" class="btn role-register-primary text-white flex-fill" data-step-action="submit" hidden>Request Connection</button>
                    </div>
                </form>

                <p class="role-register-footer text-secondary mb-0">
                    Already registered?
                    <a href="{{ route('login') }}" class="role-register-footer-link">Return to login</a>
                </p>
            </div>
        </section>
@endcomponent

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form[action="{{ route('register.store') }}"]');

        if (! form) {
            return;
        }

        const panels = Array.from(form.querySelectorAll('[data-step-panel]'));
        const progressItems = Array.from(form.querySelectorAll('[data-progress-step]'));
        const progressFill = form.querySelector('[data-progress-fill]');
        const backButton = form.querySelector('[data-step-action="back"]');
        const nextButton = form.querySelector('[data-step-action="next"]');
        const submitButton = form.querySelector('[data-step-action="submit"]');
        let activeStep = 1;

        const fieldGroups = {
            1: ['first_name', 'last_name', 'service_country', 'service_city', 'service_barangay_subdivision', 'service_purok', 'address_block', 'address_street', 'contact_number', 'email'],
            2: ['property_type', 'ownership_status', 'valid_id_presented', 'acknowledgment_accepted'],
        };

        // The contact number must accept numeric digits only.
        form.querySelectorAll('.js-digits-only').forEach(function (input) {
            input.addEventListener('input', function () {
                const cleaned = input.value.replace(/[^0-9]/g, '').slice(0, 15);

                if (cleaned !== input.value) {
                    input.value = cleaned;
                }
            });
        });

        function setStep(step) {
            activeStep = step;

            panels.forEach(function (panel) {
                panel.hidden = Number(panel.dataset.stepPanel) !== step;
            });

            progressItems.forEach(function (item) {
                const itemStep = Number(item.dataset.progressStep);

                item.classList.toggle('is-active', itemStep === step);
                item.classList.toggle('is-complete', itemStep < step);
            });

            if (progressFill) {
                progressFill.style.width = step === 1 ? '0%' : 'calc(50% - 2rem)';
            }

            backButton.hidden = false;
            nextButton.hidden = step === panels.length;
            submitButton.hidden = step !== panels.length;
        }

        let emailValidating = false;

        function validateStep(step) {
            const fields = fieldGroups[step] ?? [];

            for (const fieldName of fields) {
                const elements = Array.from(form.querySelectorAll(`[name="${fieldName}"]`));

                if (elements.length === 0) {
                    continue;
                }

                if (elements[0].type === 'checkbox') {
                    const checkbox = elements[0];

                    if (checkbox.required && ! checkbox.checked) {
                        checkbox.reportValidity();
                        return false;
                    }

                    continue;
                }

                const firstElement = elements[0];

                if (firstElement.type === 'radio') {
                    const isRequired = elements.some(function (element) {
                        return element.required;
                    });

                    if (! isRequired) {
                        continue;
                    }

                    const hasChecked = elements.some(function (element) {
                        return element.checked;
                    });

                    if (! hasChecked) {
                        elements[0].reportValidity();
                        return false;
                    }

                    continue;
                }

                if (! firstElement.checkValidity()) {
                    firstElement.reportValidity();
                    return false;
                }
            }

            return true;
        }

        function showEmailError(message) {
            var existing = document.getElementById('email-validation-alert');
            if (existing) {
                existing.querySelector('div').textContent = message;
                existing.hidden = false;
                return;
            }
            var alert = document.createElement('div');
            alert.id = 'email-validation-alert';
            alert.className = 'alert alert-danger d-flex align-items-start gap-2';
            alert.role = 'alert';
            alert.innerHTML = '<i class="bi bi-exclamation-circle mt-1"></i><div>' + message + '</div>';
            var card = document.querySelector('.role-register-card');
            if (card) {
                card.insertBefore(alert, card.querySelector('form'));
            }
        }

        function validateEmailStep(callback) {
            const emailInput = document.getElementById('email');
            const email = emailInput ? emailInput.value.trim() : '';

            if (! email) {
                emailInput.reportValidity();
                callback(false);
                return;
            }

            if (! emailInput.checkValidity()) {
                emailInput.reportValidity();
                callback(false);
                return;
            }

            if (emailValidating) {
                callback(false);
                return;
            }

            emailValidating = true;

            var formData = new FormData();
            formData.append('email', email);

            fetch('{{ route("auth.validate-email") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData,
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                }, function () {
                    return { status: response.status, data: { valid: false, message: 'The email is not existing or invalid.' } };
                });
            })
            .then(function (result) {
                if (result.status === 200 && result.data.valid) {
                    callback(true);
                } else {
                    showEmailError(result.data.message || 'The email is not existing or invalid. Please use a valid email address.');
                    emailInput.focus();
                    callback(false);
                }
            })
            .catch(function () {
                callback(true);
            })
            .finally(function () {
                emailValidating = false;
            });
        }

        nextButton.addEventListener('click', function () {
            if (! validateStep(activeStep)) {
                return;
            }

            if (activeStep === 1) {
                nextButton.disabled = true;
                nextButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Validating...';

                validateEmailStep(function (valid) {
                    nextButton.disabled = false;
                    nextButton.innerHTML = 'Continue';

                    if (valid) {
                        setStep(Math.min(activeStep + 1, panels.length));
                    }
                });

                return;
            }

            setStep(Math.min(activeStep + 1, panels.length));
        });

        backButton.addEventListener('click', async function () {
            if (activeStep > 1) {
                setStep(activeStep - 1);
                return;
            }
            if (window.saveRegistrationDraft) await window.saveRegistrationDraft();
            window.location.href = @json($selectedMembershipType === 'Member' ? route('register.member-details') : route('register'));
        });

        form.addEventListener('submit', function (event) {
            if (! validateStep(activeStep)) {
                event.preventDefault();
            }
        });

        if (@json($errors->has('property_type') || $errors->has('ownership_status') || $errors->has('valid_id_presented') || $errors->has('acknowledgment_accepted'))) {
            setStep(2);
            return;
        }

        setStep(1);
    });
</script>
@endpush

@push('scripts')
    @include('auth.registration-draft')
@endpush
