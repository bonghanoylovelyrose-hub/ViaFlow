.record-filter-form {
    display: grid;
    grid-template-columns: minmax(13rem, 1.3fr) repeat(2, minmax(11rem, 1fr)) auto;
    align-items: end;
    gap: 0.75rem;
    width: 100%;
    margin-bottom: 1rem;
}

.record-filter-field label {
    display: block;
    margin-bottom: 0.35rem;
    color: color-mix(in srgb, var(--vf-navy) 62%, #fff);
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
}

.record-filter-form .form-control {
    background: #fff !important;
    color: var(--wb-text) !important;
    font-size: 0.88rem;
}

.record-filter-form .form-control::placeholder {
    color: var(--wb-muted) !important;
}

.record-filter-form .form-select option {
    background: #fff;
    color: var(--wb-text);
}

.record-filter-form :is(.form-control, .form-select) {
    min-height: 2.75rem;
    height: 2.75rem;
    border-radius: 0.8rem;
    border-color: color-mix(in srgb, var(--vf-royal) 20%, #fff) !important;
    font-weight: 600;
}

.record-filter-form :is(.form-control, .form-select):focus {
    border-color: var(--vf-royal) !important;
    box-shadow: none;
}

.record-filter-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

.record-filter-actions .btn {
    min-height: 2.55rem;
    white-space: nowrap;
}

.record-filter-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.75rem;
    padding: 0.4rem 0.8rem;
    border: 1px solid color-mix(in srgb, var(--vf-royal) 30%, #fff);
    border-radius: 0.8rem;
    background: #fff;
    color: var(--vf-royal);
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.record-filter-clear:hover {
    color: var(--vf-royal);
    background: color-mix(in srgb, var(--vf-royal) 8%, #fff);
}

@media (max-width: 1199.98px) {
    .record-filter-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .record-filter-search,
    .record-filter-actions {
        grid-column: 1 / -1;
    }

    .record-filter-actions {
        justify-content: flex-start;
    }
}

@media (max-width: 575.98px) {
    .record-filter-form {
        grid-template-columns: 1fr;
    }

    .record-filter-search,
    .record-filter-actions {
        grid-column: auto;
    }

    .record-filter-actions {
        justify-content: stretch;
    }

    .record-filter-actions .btn {
        flex: 1 1 auto;
    }
}
