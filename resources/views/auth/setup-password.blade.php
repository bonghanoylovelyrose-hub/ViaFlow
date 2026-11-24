@extends('layouts.app')
@section('title', 'Set Your Password')
@push('styles')
<style>
    .setup-password-field { position: relative; }
    .setup-password-field .form-control { padding-right: 3rem; }
    .setup-password-toggle { position: absolute; inset: 0 0 0 auto; width: 2.75rem; display: flex; align-items: center; justify-content: center; padding: 0; border: 0; border-radius: .375rem; background: transparent; color: var(--vf-navy); }
    .setup-password-toggle:hover { color: var(--vf-royal); }
    .setup-password-toggle:focus-visible { outline: 2px solid var(--vf-royal); outline-offset: -3px; }
</style>
@endpush
@section('body')
<main class="container py-5" style="max-width: 560px;">
    <div class="panel-card p-4">
        <h1 class="h3">Set Your Password</h1>
        <p>Choose a password with at least 12 characters, uppercase and lowercase letters, and numbers.</p>
        @if (session('warning'))
            <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ $formAction }}">
            @csrf
            <label for="setup-password" class="form-label">New Password</label>
            <div class="setup-password-field mb-3">
                <input id="setup-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="12" required>
                <button class="setup-password-toggle" type="button" aria-label="Show new password" aria-controls="setup-password" aria-pressed="false" data-password-name="new password"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
            <label for="setup-confirmation" class="form-label">Confirm Password</label>
            <div class="setup-password-field mb-3">
                <input id="setup-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="12" required>
                <button class="setup-password-toggle" type="button" aria-label="Show confirm password" aria-controls="setup-confirmation" aria-pressed="false" data-password-name="confirm password"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
            <button class="btn btn-primary" type="submit">Save Password</button>
        </form>
    </div>
</main>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('.setup-password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(showing));
            button.setAttribute('aria-label', (showing ? 'Hide ' : 'Show ') + button.dataset.passwordName);
            button.querySelector('i').className = showing ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
</script>
@endpush
