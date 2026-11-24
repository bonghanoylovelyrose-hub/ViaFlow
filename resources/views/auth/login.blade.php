@extends('layouts.app')

@section('title', 'Login')

@push('styles')
<script>document.documentElement.classList.add('login-has-js');</script>
<style>
    .role-login-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.88), rgba(216, 237, 255, 0.78)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
        animation: login-page-in 0.55s ease-out both;
    }

    .role-login-shell {
        position: relative;
        width: min(920px, 100%);
        min-height: 520px;
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        background: rgba(255, 255, 255, 0.94);
        border-radius: 1.6rem;
        overflow: hidden;
        box-shadow: 0 28px 75px rgba(50, 165, 31, 0.18);
    }

    .login-welcome {
        display: none;
    }

    .login-has-js .login-welcome {
        position: absolute;
        inset: 0;
        z-index: 2;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        border: 0;
        color: #fff;
        text-align: center;
        cursor: pointer;
        background: linear-gradient(135deg, rgba(6, 43, 128, 0.76), rgba(6, 43, 128, 0.58)),
            url('https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1400&q=80') center/cover no-repeat,
            url('{{ asset('images/water-tank.jpg') }}') center/cover no-repeat,
            var(--vf-royal);
        transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .login-has-js .role-login-shell:not(.is-revealed):not(.is-returning) > .role-login-visual,
    .login-has-js .role-login-shell:not(.is-revealed):not(.is-returning) > .role-login-panel {
        visibility: hidden;
    }

    .login-welcome:focus-visible {
        outline: 3px solid #fff;
        outline-offset: -8px;
    }

    .login-welcome-logo {
        display: grid;
        place-items: center;
        width: 4.5rem;
        height: 4.5rem;
        margin-bottom: 1.25rem;
        border: 1px solid rgba(255,255,255,.45);
        border-radius: 1.35rem;
        background: rgba(255,255,255,.16);
        color: #fff;
        font-size: 2rem;
    }

    .login-welcome-title { font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 800; line-height: 1.15; }
    .login-welcome-tagline { margin-top: .6rem; font-size: 1rem; }
    .login-welcome-hint { margin-top: 2rem; padding: .65rem 1.1rem; border: 1px solid rgba(255,255,255,.65); border-radius: 999px; font-weight: 700; }
    .login-welcome:hover .login-welcome-hint { background: rgba(255,255,255,.18); }
    .login-has-js .role-login-shell.is-revealed .login-welcome { opacity: 0; transform: scale(1.025); pointer-events: none; }
    .login-has-js .role-login-shell.is-revealed > .role-login-visual,
    .login-has-js .role-login-shell.is-revealed > .role-login-panel { animation: login-reveal 0.5s ease-out both; }
    .login-has-js .login-welcome[hidden] { display: none; }

    .login-has-js .role-login-shell.is-returning > .role-login-visual,
    .login-has-js .role-login-shell.is-returning > .role-login-panel {
        opacity: 0;
        transition: opacity 0.5s ease;
        pointer-events: none;
    }

    .login-countdown {
        display: none;
        margin: 0 0 1rem;
        color: var(--vf-navy);
        font-size: 0.75rem;
        text-align: center;
    }

    .login-has-js .login-countdown { display: block; }
    .login-countdown-track { display: block; width: 5rem; height: 3px; margin: .4rem auto 0; overflow: hidden; background: var(--vf-light); border-radius: 99px; }
    .login-countdown-bar { display: block; width: 100%; height: 100%; background: var(--vf-royal); transform-origin: left; transition: transform 0.2s linear; }

    @keyframes login-reveal {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .role-login-visual {
        position: relative;
        min-height: 100%;
        background:
            linear-gradient(180deg, rgba(7, 84, 200, 0.34), rgba(7, 84, 200, 0.28)),
            url('https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=900&q=80') center/cover no-repeat;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        overflow: hidden;
        animation: water-panel-motion 14s ease-in-out infinite alternate;
    }

    .role-login-visual::before {
        content: "";
        position: absolute;
        inset: 0;
        backdrop-filter: blur(3px);
        background: linear-gradient(180deg, rgba(6, 43, 128, 0.18), rgba(6, 43, 128, 0.24));
    }

    .role-login-visual-copy {
        position: relative;
        z-index: 1;
        text-align: center;
        color: #ffffff;
        max-width: 290px;
    }

    .role-login-visual-logo {
        width: 4.5rem;
        height: 4.5rem;
        margin: 0 auto 1.25rem;
        border-radius: 1.35rem;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(8px);
        font-size: 1.9rem;
        animation: droplet-float 4.5s ease-in-out infinite;
    }

    .role-login-visual-copy h1 {
        font-size: clamp(1.8rem, 2.8vw, 2.6rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 0.65rem;
    }

    .role-login-visual-copy p {
        margin: 0;
        font-size: 0.96rem;
        line-height: 1.7;
        color: rgba(255, 255, 255, 0.88);
    }

    .role-login-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 2rem;
        background: #ffffff;
    }

    .role-login-card {
        width: min(410px, 100%);
    }

    .role-login-card h2 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.45rem;
        color: var(--vf-navy);
        text-align: center;
    }

    .role-login-subtitle {
        color: var(--vf-navy);
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .role-login-label {
        font-weight: 600;
        margin-bottom: 0.55rem;
        color: var(--vf-navy);
    }

    .role-login-input,
    .role-login-password {
        height: 3.35rem;
        border-radius: 0.85rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        padding-inline: 1rem;
        font-size: 0.98rem;
        box-shadow: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .role-login-input:focus,
    .role-login-password:focus {
        border-color: rgba(7, 84, 200, 0.45);
        box-shadow: 0 0 0 0.22rem rgba(7, 84, 200, 0.12);
    }

    .role-login-password-group {
        border-radius: 0.85rem;
    }

    .role-login-toggle {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-left: 0;
        border-radius: 0 0.85rem 0.85rem 0;
        color: var(--vf-navy);
        background: #ffffff;
    }

    .role-login-toggle:hover,
    .role-login-toggle:focus {
        color: var(--vf-royal);
        background: #f7fbfc;
    }

    .role-login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .role-login-remember {
        margin-bottom: 0;
        color: var(--vf-navy);
        font-size: 0.95rem;
        user-select: none;
    }

    .role-login-remember .form-check-input {
        cursor: pointer;
    }

    .role-login-remember .form-check-label {
        cursor: pointer;
    }

    .role-login-forgot-link {
        color: var(--vf-royal);
        font-weight: 600;
        font-size: 0.88rem;
        text-decoration: none;
        transition: color 0.15s ease-in-out;
        text-align: right;
        margin-left: auto;
    }

    .role-login-forgot-link:hover {
        color: var(--vf-navy);
        text-decoration: underline;
    }

    .role-login-primary {
        height: 3.35rem;
        border: 0;
        border-radius: 0.85rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        box-shadow: 0 14px 28px rgba(7, 84, 200, 0.22);
        transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
    }

    .role-login-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(7, 84, 200, 0.26);
        opacity: 0.98;
    }

    .role-login-footer {
        text-align: center;
        margin-top: 1rem;
    }

    .role-login-footer-link {
        color: var(--vf-royal);
        font-weight: 600;
        text-decoration: none;
    }

    .role-login-footer-link:hover {
        color: var(--vf-navy);
        text-decoration: underline;
    }

    @keyframes login-page-in {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes droplet-float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    @keyframes water-panel-motion {
        from { background-position: center 0; }
        to { background-position: center 14px; }
    }

    /* The welcome screen retains its existing artwork and click target. */
    .login-has-js .login-welcome { overflow: hidden; isolation: isolate; }
    .login-has-js .login-welcome::before {
        content: ""; position: absolute; inset: 0; z-index: -2;
        background: inherit; pointer-events: none;
        animation: welcome-water-drift 24s ease-in-out infinite alternate;
    }
    .login-has-js .login-welcome::after {
        content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none;
        background: radial-gradient(ellipse at 20% 0%, rgba(255,255,255,.10), transparent 48%);
        opacity: .55; transition: opacity .5s ease;
    }
    .login-has-js .login-welcome:hover::after { opacity: .85; }
    .login-welcome-logo {
        position: relative; overflow: hidden; isolation: isolate;
        animation: welcome-logo-in .7s ease-out backwards, welcome-logo-float 4s ease-in-out .7s infinite, welcome-logo-glow 4s ease-in-out .7s infinite;
        transition: scale .3s ease, filter .3s ease;
    }
    .login-welcome-logo::after {
        content: ""; position: absolute; inset: -85% auto -85% -65%; width: 45%;
        z-index: 1; pointer-events: none;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.19), transparent);
        transform: translateX(0) rotate(25deg);
        animation: welcome-logo-reflection 6s ease-in-out 1.5s infinite;
    }
    .login-welcome-logo:hover { scale: 1.05; filter: drop-shadow(0 0 8px rgba(22,141,255,.32)); }
    @keyframes welcome-logo-float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    @keyframes welcome-logo-glow { 0%,100% { box-shadow: 0 0 10px rgba(22,141,255,.12); } 50% { box-shadow: 0 0 18px rgba(22,141,255,.25); } }
    @keyframes welcome-logo-reflection { 0%,18% { transform: translateX(0) rotate(25deg); } 48%,100% { transform: translateX(480%) rotate(25deg); } }
    .login-welcome-title { animation: welcome-text-in .65s ease-out .15s backwards; }
    .login-welcome-tagline { animation: welcome-text-in .65s ease-out .28s backwards; }
    .login-welcome-hint {
        position: relative; overflow: hidden; isolation: isolate;
        background: linear-gradient(145deg, rgba(255,255,255,.22), rgba(255,255,255,.08));
        border-color: rgba(255,255,255,.55);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.32), 0 7px 22px rgba(6,43,128,.18);
        -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
        transition: transform .25s ease, box-shadow .25s ease, background .25s ease;
        animation: welcome-text-in .65s ease-out .42s backwards;
    }
    .login-welcome-hint::before {
        content: ""; position: absolute; inset: -90% auto -90% -65%; width: 45%; z-index: -1;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.24), transparent);
        transform: translateX(0) rotate(25deg); pointer-events: none;
        animation: welcome-glass-shine 5s ease-in-out 1.2s infinite;
    }
    .login-welcome:hover .login-welcome-hint {
        background: linear-gradient(145deg, rgba(255,255,255,.28), rgba(255,255,255,.13));
        box-shadow: inset 0 1px 0 rgba(255,255,255,.42), 0 9px 25px rgba(22,141,255,.24);
        transform: translateY(-2px);
    }
    .login-welcome:active .login-welcome-hint { transform: translateY(0); }
    @keyframes welcome-logo-in { from { opacity: 0; transform: scale(.95); } to { opacity: 1; transform: scale(1); } }
    @keyframes welcome-text-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes welcome-water-drift { from { transform: scale(1.01); } to { transform: scale(1.045); } }
    @keyframes welcome-glass-shine { 0%, 18% { transform: translateX(0) rotate(25deg); } 48%, 100% { transform: translateX(480%) rotate(25deg); } }

    /* Glass reflection on the existing Login submit button. */
    .role-login-primary {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        transition: transform .3s ease, box-shadow .3s ease, opacity .3s ease;
    }
    .role-login-primary::before {
        content: "";
        position: absolute;
        inset: -100% auto -100% -45%;
        width: 28%;
        z-index: -1;
        pointer-events: none;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.24), transparent);
        transform: translateX(0) rotate(25deg);
        animation: login-submit-shine 5s ease-in-out 1.2s infinite;
    }
    .role-login-primary:not(:disabled):not(.disabled):hover {
        transform: translateY(-2px);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.25), 0 12px 28px rgba(22,141,255,.3);
    }
    .role-login-primary:not(:disabled):not(.disabled):active { transform: translateY(0); }
    .role-login-primary:disabled::before, .role-login-primary.disabled::before { animation: none; opacity: 0; }
    @keyframes login-submit-shine {
        0%, 18% { transform: translateX(0) rotate(25deg); }
        48%, 100% { transform: translateX(600%) rotate(25deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        .login-welcome-logo::after { animation: none; opacity: 0; }
        .login-welcome-logo, .login-welcome-logo:hover { transition: none; scale: 1; filter: none; }
        .role-login-primary::before { animation: none; opacity: 0; }
        .role-login-primary:not(:disabled):not(.disabled):hover,
        .role-login-primary:not(:disabled):not(.disabled):active { transform: none; }
        .login-has-js .login-welcome::before,
        .login-welcome-logo, .login-welcome-title, .login-welcome-tagline,
        .login-welcome-hint, .login-welcome-hint::before { animation: none; }
        .login-has-js .login-welcome::after, .login-welcome-hint { transition: none; }
        .login-welcome:hover .login-welcome-hint { transform: none; }

        .role-login-page,
        .role-login-visual,
        .role-login-visual-logo {
            animation: none;
        }

        .login-has-js .role-login-shell.is-revealed > .role-login-visual,
        .login-has-js .role-login-shell.is-revealed > .role-login-panel { animation: none; }
        .login-has-js .login-welcome { transition: none; }
        .login-has-js .role-login-shell.is-returning > .role-login-visual,
        .login-has-js .role-login-shell.is-returning > .role-login-panel,
        .login-countdown-bar { transition: none; }

        .role-login-primary,
        .role-login-input,
        .role-login-password {
            transition: none;
        }
    }

    @media (max-width: 991.98px) {
        .role-login-shell {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .role-login-visual,
        .role-login-panel {
            padding: 2rem;
        }

        .role-login-visual {
            min-height: 260px;
        }
    }

    @media (max-width: 575.98px) {
        .role-login-page {
            padding: 1rem;
        }

        .role-login-card {
            padding: 1.5rem 0;
        }

        .role-login-options {
            gap: 0.5rem;
        }

        .role-login-forgot-link {
            font-size: 0.85rem;
        }
    }
</style>
@endpush

@section('body')
<div class="role-login-page">
    <div class="role-login-shell">
        <button type="button" class="login-welcome" id="loginWelcome" aria-controls="loginPanel" aria-label="ViaFlow System. Tap to sign in">
            <span class="login-welcome-logo" aria-hidden="true"><x-organization-logo size="hero" /></span>
            <span class="login-welcome-title">ViaFlow System</span>
            <span class="login-welcome-tagline">Smart and efficient water management</span>
            <span class="login-welcome-hint">Tap to Log In <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </button>
        <section class="role-login-visual">
            <div class="role-login-visual-copy">
                <div class="role-login-visual-logo">
                    <x-organization-logo size="hero" />
                </div>
                <h1>ViaFlow System</h1>
                <p>Smart and efficient water management</p>
            </div>
        </section>

        <section class="role-login-panel" id="loginPanel">
            <div class="role-login-card">
                <x-organization-logo size="auth" />
                <h2>Welcome Back</h2>
                <p class="role-login-subtitle">Sign in to continue to your account.</p>
                <div class="login-countdown" aria-live="off">Returning to welcome in <span id="loginCountdown">30</span>s
                    <span class="login-countdown-track" aria-hidden="true"><span class="login-countdown-bar" id="loginCountdownBar"></span></span>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                @if (session('success') || session('status'))
                    <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-check-circle mt-1"></i>
                        <div>{{ session('success') ?? session('status') }}</div>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
                @endif
                <form method="POST" action="{{ request()->getBaseUrl().route('login.attempt', absolute: false) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="login" class="form-label role-login-label">Username</label>
                        <input type="text" class="form-control role-login-input" id="login" name="login" value="{{ old('login') }}" placeholder="MT Number for consumers; email or username for admin or meter reader" autocomplete="username" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label role-login-label">Password</label>
                        <div class="input-group role-login-password-group">
                            <input type="password" class="form-control role-login-password" id="password" name="password" placeholder="Enter your password" required>
                            <button class="btn role-login-toggle password-toggle" type="button" data-target="password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="role-login-options">
                        <div class="form-check role-login-remember">
                            <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="{{ route('password.forgot') }}" class="role-login-forgot-link" title="Request a secure password reset link">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="btn role-login-primary text-white w-100">Login</button>
                </form>

                <p class="role-login-footer text-secondary mb-0">
                    Want to apply?
                    <a href="{{ route('register') }}" class="role-login-footer-link">Apply Now!</a>
                </p>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
@if (session('application_reference'))
<script>
    (() => {
        const tabId = sessionStorage.getItem('viaflow-application-tab');
        sessionStorage.removeItem('viaflow-application-member');
        sessionStorage.removeItem('viaflow-application-connection');
        sessionStorage.removeItem('viaflow-application-tab');
        if (!tabId || !window.indexedDB) return;
        const request = indexedDB.open('viaflow-application-drafts', 1);
        request.onupgradeneeded = () => request.result.createObjectStore('files');
        request.onsuccess = () => {
            const db = request.result;
            const tx = db.transaction('files', 'readwrite');
            tx.objectStore('files').delete(tabId + ':member');
            tx.objectStore('files').delete(tabId + ':connection');
            tx.oncomplete = () => db.close();
        };
    })();
</script>
@endif
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const welcome = document.getElementById('loginWelcome');
        const shell = welcome.closest('.role-login-shell');
        const panel = document.getElementById('loginPanel');
        const form = panel.querySelector('form');
        const countdown = document.getElementById('loginCountdown');
        const countdownBar = document.getElementById('loginCountdownBar');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const idleDuration = 30000;
        let deadline = 0;
        let timer = null;
        let returnTimer = null;
        let submitting = false;
        let composing = false;

        function stopCountdown() {
            window.clearTimeout(timer);
            timer = null;
        }

        function returnToWelcome() {
            stopCountdown();
            welcome.hidden = false;
            welcome.removeAttribute('aria-hidden');
            welcome.tabIndex = 0;
            shell.classList.add('is-returning');
            // Establish the transparent welcome state before fading it back in.
            void welcome.offsetWidth;
            shell.classList.remove('is-revealed');
            panel.inert = true;
            welcome.focus({ preventScroll: true });
            returnTimer = window.setTimeout(function () {
                shell.classList.remove('is-returning');
                returnTimer = null;
            }, reducedMotion.matches ? 0 : 500);
        }

        function tickCountdown() {
            const remaining = Math.max(0, deadline - Date.now());
            countdown.textContent = String(Math.ceil(remaining / 1000));
            countdownBar.style.transform = 'scaleX(' + (remaining / idleDuration) + ')';
            if (remaining === 0) {
                returnToWelcome();
            } else {
                timer = window.setTimeout(tickCountdown, Math.min(200, remaining));
            }
        }

        function resetCountdown() {
            if (!shell.classList.contains('is-revealed') || submitting || composing) return;
            stopCountdown();
            deadline = Date.now() + idleDuration;
            tickCountdown();
        }

        welcome.addEventListener('click', function () {
            window.clearTimeout(returnTimer);
            shell.classList.remove('is-returning');
            panel.inert = false;
            shell.classList.add('is-revealed');
            welcome.tabIndex = -1;
            welcome.setAttribute('aria-hidden', 'true');
            document.getElementById('login').focus({ preventScroll: true });
            if (reducedMotion.matches) {
                welcome.hidden = true;
            } else {
                welcome.addEventListener('transitionend', function (event) {
                    if (event.propertyName === 'opacity' && shell.classList.contains('is-revealed')) welcome.hidden = true;
                }, { once: true });
            }
            resetCountdown();
        });
        ['pointerdown', 'click', 'keydown', 'input', 'change', 'focusin'].forEach(function (eventName) {
            panel.addEventListener(eventName, resetCountdown);
        });
        panel.addEventListener('compositionstart', function () { composing = true; stopCountdown(); });
        panel.addEventListener('compositionend', function () { composing = false; resetCountdown(); });
        form.addEventListener('submit', function () { submitting = true; stopCountdown(); });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { submitting = false; resetCountdown(); }
        });
        document.querySelectorAll('.password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.dataset.target);
                const icon = button.querySelector('i');
                const showPassword = input.type === 'password';

                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                icon.className = showPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });
    });
</script>
@endpush
