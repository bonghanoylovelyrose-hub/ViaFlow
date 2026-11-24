@extends('layouts.app')

@php
    $activePage = 'profile';
    $user = auth()->user();
@endphp

@section('title', 'My Profile')

@push('styles')
<style>
    .password-indicator {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.95rem;
        padding: 0.9rem;
        background: #fbfdfe;
    }

    .password-indicator[hidden] {
        display: none !important;
    }

    .password-indicator-bar {
        height: 0.45rem;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(216, 237, 255, 0.95);
    }

    .password-indicator-fill {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: var(--wb-danger);
        transition: width 0.18s ease, background 0.18s ease;
    }

    .password-indicator-fill.is-fair {
        background: var(--wb-warning);
    }

    .password-indicator-fill.is-good,
    .password-indicator-fill.is-strong {
        background: var(--wb-success);
    }

    .password-rule {
        color: var(--wb-muted);
        font-size: 0.88rem;
    }

    .password-rule i {
        color: var(--wb-muted);
    }

    .password-rule.is-valid {
        color: var(--wb-success);
    }

    .password-rule.is-valid i {
        color: var(--wb-success);
    }

    .password-match-feedback {
        display: none;
        font-size: 0.88rem;
        font-weight: 600;
        margin-top: 0.45rem;
    }

    .password-match-feedback.is-visible {
        display: block;
    }

    .password-match-feedback.is-invalid {
        color: var(--wb-danger);
    }

    .password-match-feedback.is-valid {
        color: var(--wb-success);
    }

    .password-toggle {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-left: 0;
        background: #ffffff;
        color: var(--vf-navy);
    }

    .password-toggle:hover,
    .password-toggle:focus {
        color: var(--vf-royal);
        background: #f7fbfc;
        border-color: rgba(7, 84, 200, 0.45);
    }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>My Profile</h1>
                    <p>Review your registered account details and update your login password.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2 panel-card border-0 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle mt-1"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @if ($user->isConsumer())
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="panel-card p-4 h-100">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                <div class="section-title mb-0">Account Details</div>
                                <x-consumer-status-badge :consumer="$consumer" />
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Full Name</div>
                                <div class="fw-semibold">{{ $consumer?->full_name ?? $user->name }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Membership Status</div>
                                <div>{{ $consumer?->membership_type ?: 'Not available' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Account Number</div>
                                <div>{{ $consumer?->account_number ?? 'Not assigned yet' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Meter Number</div>
                                <div>{{ $consumer?->meter_number ?? 'Not assigned yet' }}</div>
                            </div>
                            <div class="mb-0">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Service Address</div>
                                <div>{{ $consumer?->service_address ?? 'Not provided' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="panel-card p-4 h-100">
                            <div class="section-title mb-3">Login Details</div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Username</div>
                                <div class="fw-semibold">{{ $consumer?->meter_number ?? 'Not assigned yet' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Registered Email</div>
                                <div>{{ $user->email }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        @include('consumer.profile.partials.password-form')
                    </div>
                </div>
            @else
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="panel-card p-4 h-100">
                            <div class="section-title mb-3">Account Details</div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Full Name</div>
                                <div class="fw-semibold">{{ $user->name }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Email / Username</div>
                                <div>{{ $user->email }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Role</div>
                                <div>{{ str($user->role)->replace('_', ' ')->title() }}</div>
                            </div>
                            <div class="mb-0">
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">User ID</div>
                                <div>{{ $user->id }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        @include('consumer.profile.partials.password-form')
                    </div>
                </div>
            @endif
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');
        const passwordIndicator = document.getElementById('passwordIndicator');
        const passwordMatchFeedback = document.getElementById('passwordMatchFeedback');
        const strengthFill = document.querySelector('[data-password-strength-fill]');
        const strengthLabel = document.querySelector('[data-password-strength-label]');
        const ruleElements = document.querySelectorAll('[data-password-rule]');

        if (! passwordInput || ! passwordConfirmationInput || ! passwordIndicator || ! passwordMatchFeedback || ! strengthFill || ! strengthLabel || ruleElements.length === 0) {
            return;
        }

        const ruleChecks = {
            length: function (value) {
                return value.length >= 8;
            },
            lowercase: function (value) {
                return /[a-z]/.test(value);
            },
            uppercase: function (value) {
                return /[A-Z]/.test(value);
            },
            number: function (value) {
                return /\d/.test(value);
            },
        };

        const strengthStates = [
            { label: 'Enter password', width: '0%', className: '' },
            { label: 'Weak', width: '25%', className: '' },
            { label: 'Fair', width: '50%', className: 'is-fair' },
            { label: 'Good', width: '75%', className: 'is-good' },
            { label: 'Strong', width: '100%', className: 'is-strong' },
        ];

        function updatePasswordIndicator() {
            const value = passwordInput.value;
            let passedRules = 0;

            passwordIndicator.hidden = value.length === 0;

            ruleElements.forEach(function (ruleElement) {
                const rule = ruleElement.dataset.passwordRule;
                const isValid = ruleChecks[rule]?.(value) ?? false;
                const icon = ruleElement.querySelector('i');

                if (isValid) {
                    passedRules += 1;
                }

                ruleElement.classList.toggle('is-valid', isValid);

                if (icon) {
                    icon.className = isValid ? 'bi bi-check-circle-fill me-1' : 'bi bi-circle me-1';
                }
            });

            const state = value.length === 0 ? strengthStates[0] : strengthStates[passedRules];
            strengthFill.className = ['password-indicator-fill', state.className].filter(Boolean).join(' ');
            strengthFill.style.width = state.width;
            strengthLabel.textContent = state.label;
        }

        function updatePasswordMatchIndicator() {
            const password = passwordInput.value;
            const confirmation = passwordConfirmationInput.value;
            const hasConfirmation = confirmation.length > 0;
            const matches = hasConfirmation && confirmation === password;

            passwordConfirmationInput.classList.toggle('is-invalid', hasConfirmation && ! matches);
            passwordConfirmationInput.classList.toggle('is-valid', matches);
            passwordMatchFeedback.classList.toggle('is-visible', hasConfirmation);
            passwordMatchFeedback.classList.toggle('is-invalid', hasConfirmation && ! matches);
            passwordMatchFeedback.classList.toggle('is-valid', matches);
            passwordMatchFeedback.textContent = matches ? 'Passwords match.' : 'Passwords do not match.';
        }

        passwordInput.addEventListener('input', updatePasswordIndicator);
        passwordInput.addEventListener('input', updatePasswordMatchIndicator);
        passwordConfirmationInput.addEventListener('input', updatePasswordMatchIndicator);
        updatePasswordIndicator();
        updatePasswordMatchIndicator();

        document.querySelectorAll('.password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.dataset.target;
                const input = document.getElementById(targetId);
                if (!input) return;

                const icon = button.querySelector('i');
                const isPassword = input.type === 'password';

                input.type = isPassword ? 'text' : 'password';
                button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                if (icon) {
                    icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
                }
            });
        });
    });
</script>
@endpush
