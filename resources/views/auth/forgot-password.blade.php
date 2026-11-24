@extends('layouts.app')

@section('title', 'Consumer Forgot Password')

@push('styles')
<style>
    .consumer-password-page {
        position: relative;
        isolation: isolate;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: 3.8rem 1rem 2rem;
        background: linear-gradient(135deg, rgba(240, 248, 255, .84), rgba(216, 237, 255, .76)), url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }
    .consumer-password-page::before {
        content: '';
        position: absolute;
        z-index: -1;
        inset: auto -20% -20% -20%;
        height: 45%;
        border-radius: 50% 50% 0 0;
        background: rgba(7, 84, 200, .09);
        pointer-events: none;
        animation: recovery-wave 14s ease-in-out infinite alternate;
    }
    .consumer-password-card {
        position: relative;
        width: min(100%, 690px);
        min-width: 0;
        padding: 3.4rem 3.75rem 2rem;
        border: 1px solid rgba(255, 255, 255, .8);
        border-radius: 30px;
        background: linear-gradient(145deg, rgba(255,255,255,.98), rgba(244,251,252,.97));
        box-shadow: 0 22px 60px rgba(7, 84, 200, .2);
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
        overflow-wrap: anywhere;
        animation: recovery-enter .55s ease-out both;
    }
    .consumer-password-card > * { position: relative; z-index: 1; }
    .consumer-password-card > .recovery-corner-waves { position: absolute; z-index: 0; inset: 0 0 auto auto; width: 245px; height: 115px; border-radius: 0 30px 0 0; overflow: hidden; pointer-events: none; }
    .recovery-corner-waves span { position: absolute; width: 260px; height: 120px; right: -75px; top: -70px; border-radius: 45% 0 55% 55%; transform: rotate(-20deg); background: rgba(22, 141, 255, .16); }
    .recovery-corner-waves span:nth-child(2) { top: -51px; right: -98px; background: rgba(7, 84, 200, .15); }
    .recovery-corner-waves span:nth-child(3) { top: -34px; right: -120px; background: rgba(7, 84, 200, .12); }
    .consumer-password-page::after { content: ''; position: absolute; z-index: -1; pointer-events: none; inset: auto -10% -160px; height: 280px; border-radius: 50% 55% 0 0; background: linear-gradient(165deg, rgba(7, 84, 200, .18) 0 35%, rgba(7, 84, 200, .16) 36% 65%, rgba(7, 84, 200, .12) 66%); }
    .recovery-heading { text-align: center; margin-bottom: 1.1rem; }
    .recovery-account-badge { display: inline-block; color: var(--vf-navy); background: var(--vf-light); border: 1px solid var(--vf-light); border-radius: 999px; padding: .3rem .85rem; font-size: .75rem; font-weight: 700; }
    .consumer-password-icon {
        width: 4.5rem;
        height: 4.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .9rem;
        color: #fff !important;
        background: linear-gradient(135deg, var(--vf-navy), var(--vf-royal)) !important;
        box-shadow: 0 9px 25px rgba(7, 84, 200, .36) !important;
        margin: 0;
    }
    .consumer-password-card > .consumer-password-icon { position: absolute; top: -2.25rem; left: 50%; transform: translateX(-50%); z-index: 2; }
    .consumer-password-icon svg { width: 1.8rem; height: 1.8rem; }
    .consumer-password-card h1 { color: var(--vf-navy); font-size: 1.65rem; font-weight: 800; letter-spacing: -.025em; }
    .consumer-password-card .lead-copy { color: var(--vf-navy); font-size: .91rem; line-height: 1.5; text-align: center; margin-bottom: 1.25rem; padding-bottom: 1.15rem; border-bottom: 1px solid var(--vf-light); }
    .consumer-password-card .form-label { color: var(--vf-navy); font-size: .91rem; font-weight: 700; margin-bottom: .45rem; }
    .consumer-password-card .form-label i { color: var(--vf-royal); margin-right: .45rem; }
    .consumer-password-card .form-control {
        width: 100%;
        min-width: 0;
        min-height: 3rem;
        border: 1px solid var(--vf-light);
        border-radius: .75rem;
        background: rgba(255, 255, 255, .95);
        color: var(--vf-navy);
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .consumer-password-card .form-control:focus { border-color: var(--vf-royal); box-shadow: 0 0 0 .22rem rgba(7, 84, 200, .17); }
    .consumer-password-card .form-text { color: var(--vf-navy); font-size: .82rem; line-height: 1.45; }
    .consumer-password-card .recovery-help { display: flex; align-items: flex-start; gap: .55rem; color: var(--vf-navy); background: var(--vf-background); border: 1px solid var(--vf-light); border-radius: .7rem; padding: .7rem .8rem; }
    .consumer-password-card .recovery-help i { color: var(--vf-royal); flex-shrink: 0; font-size: 1.25rem; line-height: 1; }
    .consumer-password-card .recovery-submit {
        min-height: 3rem;
        border: 0;
        border-radius: .75rem;
        background: linear-gradient(120deg, var(--vf-navy), var(--vf-royal), var(--vf-navy));
        background-size: 200% 100%;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 5px 0 rgba(6, 43, 128, .18), 0 8px 16px rgba(6, 43, 128, .15);
        transition: background-position .25s ease, transform .2s ease, box-shadow .2s ease;
    }
    .consumer-password-card .recovery-submit:hover:not(:disabled) { background-position: 100% 0; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(7, 84, 200, .25); }
    .consumer-password-card .recovery-submit:focus-visible { outline: 3px solid rgba(7, 84, 200, .35); outline-offset: 3px; }
    .consumer-password-note { display: flex; gap: .6rem; border: 1px solid var(--vf-light); background: #f0f5f8; border-radius: .8rem; padding: .8rem .9rem; color: var(--vf-navy); font-size: .85rem; line-height: 1.45; }
    .consumer-password-note i { color: var(--vf-royal); flex-shrink: 0; }
    .recovery-return { color: var(--vf-royal); }
    .recovery-return:hover { color: var(--vf-navy); }
    .recovery-return-wrap { border-top: 1px solid var(--vf-light); padding-top: 1.15rem; }
    @keyframes recovery-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes recovery-wave { from { transform: translateX(-2%) translateY(0); } to { transform: translateX(2%) translateY(-10px); } }
    @media (max-width: 575.98px) {
        .consumer-password-page { padding: 3.5rem 1rem 1rem; }
        .consumer-password-card { padding: 3rem 1.2rem 1.4rem; border-radius: 1.1rem; }
        .consumer-password-card h1 { font-size: 1.4rem; }
        .consumer-password-card .lead-copy { font-size: .9rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .consumer-password-card, .consumer-password-page::before { animation: none; }
        .consumer-password-card .form-control, .consumer-password-card .recovery-submit { transition: none; }
        .consumer-password-card .recovery-submit:hover:not(:disabled) { transform: none; }
    }
</style>
@endpush

@section('body')
<main class="consumer-password-page">
    <div class="consumer-password-card">
        <div class="recovery-corner-waves" aria-hidden="true"><span></span><span></span><span></span></div>
        <span class="consumer-password-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-3.5 7-9V5l-7-3-7 3v8c0 5.5 7 9 7 9Z"/><rect x="9" y="10.5" width="6" height="5" rx="1"/><path d="M10.5 10.5V9a1.5 1.5 0 0 1 3 0v1.5"/></svg></span>
        <div class="recovery-heading">
            <x-organization-logo size="auth" />
            <h1 class="mb-2">Forgot Password</h1>
            <span class="recovery-account-badge">Consumer accounts only</span>
        </div>

        <p class="lead-copy">Enter your registered email address and assigned meter number to request single-use password reset instructions.</p>

        @if (session('status'))
            <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                <i class="bi bi-check-circle mt-1" aria-hidden="true"></i><div>{{ session('status') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-circle mt-1" aria-hidden="true"></i><div>{{ $errors->first() }}</div>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
        @endif

        <form method="POST" action="{{ request()->getBaseUrl().route('password.forgot.send', absolute: false) }}" id="consumerPasswordRecoveryForm">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label"><i class="bi bi-envelope" aria-hidden="true"></i>Registered Email</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required autofocus>
            </div>
            <div class="mb-4">
                <label for="meter_number" class="form-label"><i class="bi bi-speedometer2" aria-hidden="true"></i>Meter Number (MT Number)</label>
                <input type="text" class="form-control" id="meter_number" name="meter_number" value="{{ old('meter_number') }}" placeholder="Enter the meter number assigned to your account" autocomplete="off" required aria-describedby="recovery-meter-help">
                <div id="recovery-meter-help" class="form-text recovery-help mt-2"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Use the details registered on your approved consumer account.</span></div>
            </div>
            <button type="submit" class="btn recovery-submit w-100" id="consumerPasswordRecoverySubmit">
                <span class="recovery-button-label"><i class="bi bi-send me-2" aria-hidden="true"></i>Send Password Reset Email</span>
                <span class="recovery-button-loading" hidden><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending...</span>
            </button>
        </form>

        <div class="consumer-password-note mt-4"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Admin and meter reader accounts are managed by the administrator and cannot use this form.</span></div>
        <p class="recovery-return-wrap text-center mt-4 mb-0"><a href="{{ route('login') }}" class="recovery-return fw-semibold text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Return to Login</a></p>
    </div>
</main>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('consumerPasswordRecoveryForm');
        const button = document.getElementById('consumerPasswordRecoverySubmit');
        const label = button.querySelector('.recovery-button-label');
        const loading = button.querySelector('.recovery-button-loading');
        let submitting = false;
        form.addEventListener('submit', (event) => {
            if (submitting) { event.preventDefault(); return; }
            submitting = true;
            button.disabled = true;
            form.setAttribute('aria-busy', 'true');
            label.hidden = true;
            loading.hidden = false;
        });
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) return;
            submitting = false;
            button.disabled = false;
            form.removeAttribute('aria-busy');
            label.hidden = false;
            loading.hidden = true;
        });
    })();
</script>
@endpush
