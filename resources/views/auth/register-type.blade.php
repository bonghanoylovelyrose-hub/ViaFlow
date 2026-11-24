@extends('layouts.app')

@section('title', 'New Connection Application')

@push('styles')
<style>
    .register-step-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.88), rgba(216, 237, 255, 0.78)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }

    .register-step-shell {
        width: min(880px, 100%);
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1.25rem;
        box-shadow: 0 28px 75px rgba(50, 165, 31, 0.18);
        padding: 1.75rem;
    }

    .register-step-head {
        text-align: center;
        margin-bottom: 1.35rem;
    }

    .register-step-pill {
        display: inline-flex;
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
        margin-bottom: 0.9rem;
    }

    .register-step-head h1 {
        font-size: clamp(1.35rem, 2.5vw, 1.7rem);
        font-weight: 750;
        margin-bottom: 0.35rem;
        color: var(--vf-navy);
    }

    .register-step-copy {
        color: var(--vf-navy);
        margin-bottom: 0;
    }

    @include('auth.connection-membership-choice-styles')
    .register-step-primary {
        height: 3rem;
        border: 0;
        border-radius: 0.85rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        box-shadow: 0 14px 28px rgba(7, 84, 200, 0.22);
    }

    .register-step-secondary {
        height: 3rem;
        border: 1px solid rgba(7, 84, 200, 0.3);
        border-radius: 0.85rem;
        font-weight: 600;
        color: var(--vf-royal);
        background: #ffffff;
        transition: color 0.18s ease, background 0.18s ease, border-color 0.18s ease;
    }

    .register-step-notice {
        padding: 0.7rem 0.85rem;
        margin-bottom: 1.25rem;
        border-radius: 0.75rem;
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .register-step-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.25rem;
    }

    .register-step-actions .btn {
        flex: 1 1 0;
    }

    @media (max-width: 575.98px) {
        .register-step-shell {
            padding: 1.35rem 1rem;
            border-radius: 1rem;
        }

        .register-step-actions {
            flex-direction: column-reverse;
        }
    }

    .register-step-secondary:hover,
    .register-step-secondary:focus {
        color: var(--vf-navy);
        background: rgba(7, 84, 200, 0.06);
        border-color: rgba(7, 84, 200, 0.5);
    }
</style>
@include('auth.application-styles')
@endpush

@section('body')
@component('auth.application-shell', ['pageClass' => 'register-step-page', 'shellClass' => 'register-step-shell'])
        <div class="register-step-head">
            <span class="register-step-pill">
                <i class="bi bi-signpost-split"></i>
                Step 1 of 3
            </span>
            <h1>Are you a VVTHOA member?</h1>
            <p class="register-step-copy">Choose an option to continue your connection application.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-circle mt-1"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('register.type.store') }}">
            @csrf
            @include('auth.connection-membership-choice', ['membershipAnswer' => session('register_connection_flow.is_viohoa_member', 'yes')])
            <div class="register-step-actions">
                <button
                    type="button"
                    class="btn register-step-secondary"
                    onclick="window.location.href = '{{ route('login') }}'"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back
                </button>
                <button type="submit" class="btn register-step-primary text-white">Continue</button>
            </div>
        </form>
@endcomponent
@endsection
