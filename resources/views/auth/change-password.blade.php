@extends('layouts.app')

@section('title', 'Change Temporary Password')

@push('styles')
<style>
    .consumer-password-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.9), rgba(216, 237, 255, 0.84)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }

    .consumer-password-card {
        width: min(560px, 100%);
        background: rgba(255, 255, 255, 0.97);
        border-radius: 1.35rem;
        padding: 2.25rem;
        box-shadow: 0 24px 65px rgba(50, 165, 31, 0.16);
    }

    .consumer-password-card h1 {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--vf-navy);
        margin-bottom: 0.45rem;
    }

    .consumer-password-card .lead-copy {
        color: var(--vf-navy);
        margin-bottom: 1.5rem;
    }

    .consumer-password-card .form-label {
        color: var(--vf-navy);
        font-weight: 600;
    }

    .consumer-password-card .form-control {
        min-height: 3.1rem;
        border-radius: 0.8rem;
    }

    .consumer-password-card .btn-primary {
        min-height: 3.1rem;
        border: 0;
        border-radius: 0.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
    }

    .consumer-account-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        background: rgba(7, 84, 200, 0.08);
        color: var(--vf-royal);
        padding: 0.45rem 0.85rem;
        font-weight: 700;
        font-size: 0.92rem;
    }
</style>
@endpush

@section('body')
<div class="consumer-password-page">
    <div class="consumer-password-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width: 3rem; height: 3rem; background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));">
                <i class="bi bi-key fs-4"></i>
            </span>
            <div>
                <h1 class="mb-0">Change Temporary Password</h1>
                <div class="text-secondary small">Required before you continue</div>
            </div>
        </div>

        @if (session('warning'))
            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-triangle mt-1"></i>
                <div>{{ session('warning') }}</div>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-circle mt-1"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <p class="lead-copy">
            You signed in with a temporary password. Replace it with your own password before using the rest of ViaFlow.
        </p>

        <div class="mb-4">
            <span class="consumer-account-chip">
                <i class="bi bi-person-badge"></i>
                Meter Number: {{ auth()->user()->consumer?->meter_number ?? auth()->user()->username ?? 'Not assigned' }}
            </span>
        </div>

        <form method="POST" action="{{ request()->getBaseUrl().route('consumer.password.update', absolute: false) }}">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label for="current_password" class="form-label">Current Temporary Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" required>
                <div class="form-text mt-2">Use at least 8 characters.</div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary text-white w-100">
                <i class="bi bi-shield-check me-2"></i>Save New Password
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-link w-100 text-secondary text-decoration-none">
                Sign out instead
            </button>
        </form>
    </div>
</div>
@endsection
