<style>
    .application-page {
        position: relative;
        isolation: isolate;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-x: clip;
        padding: 2rem 1rem;
        background: linear-gradient(135deg, rgba(240, 248, 255, .84), rgba(216, 237, 255, .76)), url('{{ asset('images/BGfinal.jpg') }}') center/cover fixed no-repeat !important;
    }
    .application-page::before, .application-page::after { content: ''; position: absolute; z-index: -1; pointer-events: none; inset: auto -15% -130px; height: 260px; border-radius: 50% 55% 0 0; background: rgba(7, 84, 200, .1); filter: none; transform: none; }
    .application-page::after { bottom: -175px; background: rgba(7, 84, 200, .11); transform: rotate(-2deg); }
    .application-shell {
        position: relative;
        width: min(880px, 100%);
        min-width: 0;
        height: auto;
        overflow: visible;
        padding: 2rem;
        border: 1px solid rgba(255,255,255,.85) !important;
        border-radius: 30px !important;
        background: linear-gradient(145deg, rgba(255,255,255,.98), rgba(244,251,252,.97)) !important;
        box-shadow: 0 22px 60px rgba(7, 84, 200, .2) !important;
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
        animation: application-enter .45s ease-out both;
    }
    .application-shell-content { position: relative; z-index: 1; min-width: 0; }
    .application-waves { position: absolute; z-index: 0; inset: 0 0 auto auto; width: 210px; height: 100px; border-radius: 0 30px 0 0; overflow: hidden; pointer-events: none; }
    .application-waves span { position: absolute; width: 250px; height: 120px; right: -80px; top: -70px; border-radius: 45% 0 55% 55%; transform: rotate(-20deg); background: rgba(22, 141, 255, .16); }
    .application-waves span:nth-child(2) { top: -51px; right: -100px; background: rgba(7, 84, 200, .15); }
    .application-waves span:nth-child(3) { top: -34px; right: -120px; background: rgba(7, 84, 200, .12); }
    .application-shell .role-register-panel { padding: 0; background: transparent !important; }
    .application-shell :is(.register-step-head h1, .role-register-card h2, .register-section-title, .role-register-section-title, .role-register-label, .register-step-title) { color: var(--vf-navy); }
    .application-shell :is(.register-step-copy, .register-section-copy, .role-register-subtitle, .role-register-section-copy, .register-step-meta) { color: var(--vf-navy); }
    .application-shell :is(.register-step-pill, .role-register-step-pill, .role-register-badge) { background: var(--vf-light) !important; color: var(--vf-navy) !important; border: 1px solid var(--vf-light) !important; border-radius: 999px !important; }
    .application-shell :is(.register-section, .role-register-section) { border: 1px solid var(--vf-light) !important; border-radius: .9rem !important; background: #f8fcfd !important; }
    .application-shell :is(.form-control, .form-select, .role-register-input, .role-register-select, .role-register-textarea) { min-width: 0; border: 1px solid var(--vf-light); border-radius: .75rem; color: var(--vf-navy); transition: border-color .2s ease, box-shadow .2s ease; }
    .application-shell :is(.form-control, .form-select, .role-register-input, .role-register-select, .role-register-textarea):focus { border-color: var(--vf-royal) !important; box-shadow: 0 0 0 .22rem rgba(7, 84, 200, .17) !important; outline: 0; }
    .application-shell :is(.register-step-primary, .role-register-primary) { border: 0; background: linear-gradient(120deg, var(--vf-navy), var(--vf-royal)) !important; color: #fff !important; border-radius: .75rem; box-shadow: 0 5px 0 rgba(6, 43, 128, .18), 0 8px 16px rgba(6, 43, 128, .15); transition: transform .2s ease, box-shadow .2s ease; }
    .application-shell :is(.register-step-primary, .role-register-primary):hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(7, 84, 200, .25); }
    .application-shell :is(.register-step-secondary, .role-register-secondary) { border-radius: .75rem; border-color: var(--vf-light); color: var(--vf-royal); }
    .application-shell :is(.register-step-card, .register-choice-card) { background: #fff; border-color: var(--vf-light); transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease; }
    .application-shell :is(.register-step-option:checked + .register-step-card, .register-choice-input:checked + .register-choice-card) { border-color: var(--vf-royal); background: var(--vf-background); box-shadow: 0 0 0 .2rem rgba(7, 84, 200, .16); }
    .application-shell :is(.register-step-option:focus-visible + .register-step-card, .register-choice-input:focus-visible + .register-choice-card, button:focus-visible, a:focus-visible) { outline: 3px solid rgba(7, 84, 200, .55); outline-offset: 2px; }
    .application-shell .alert-info { background: var(--vf-background) !important; border: 1px solid var(--vf-light) !important; color: var(--vf-navy) !important; border-radius: .8rem; }
    @keyframes application-enter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 767.98px) { .application-shell { padding: 1.5rem 1rem; border-radius: 1.2rem !important; } .application-waves { width: 140px; height: 75px; border-radius: 0 1.2rem 0 0; } }
    @media (prefers-reduced-motion: reduce) { .application-shell { animation: none; } .application-shell :is(.form-control, .form-select, .register-step-primary, .role-register-primary, .register-step-card, .register-choice-card) { transition: none; } .application-shell :is(.register-step-primary, .role-register-primary):hover { transform: none; } }
</style>
