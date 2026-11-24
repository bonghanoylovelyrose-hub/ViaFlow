.register-step-card {
    border: 1px solid rgba(216, 237, 255, 0.95);
    border-radius: 1rem;
    padding: 1.15rem;
    min-height: 138px;
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.register-step-card:hover {
    border-color: rgba(7, 84, 200, 0.45);
    box-shadow: 0 14px 28px rgba(7, 84, 200, 0.08);
    transform: translateY(-1px);
}

.register-step-option:checked + .register-step-card {
    border-color: var(--vf-royal);
    box-shadow: 0 0 0 0.22rem rgba(7, 84, 200, 0.12);
}

.register-step-title {
    font-weight: 700;
    color: var(--vf-navy);
}

.register-step-meta {
    color: var(--vf-navy);
    font-size: 0.88rem;
    line-height: 1.5;
    margin-bottom: 0;
}
