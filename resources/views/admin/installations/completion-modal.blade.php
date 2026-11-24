@push('styles')
<style>
    .consumer-installation-details {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        min-width: 0;
        white-space: nowrap;
        overflow-wrap: normal;
        word-break: normal;
    }

    .consumer-installation-details .badge,
    .consumer-installation-date,
    .consumer-installation-details .btn {
        white-space: nowrap;
        word-break: keep-all;
    }

    .consumer-installation-date {
        color: var(--bs-secondary-color);
        font-size: 0.8rem;
        line-height: 1.2;
    }

    .consumer-installation-details form {
        margin: 0;
    }
</style>
@endpush

<div class="modal fade" id="installationCompletionModal" tabindex="-1" aria-labelledby="installationCompletionTitle" aria-describedby="installationCompletionPrompt" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-2">
                <div>
                    <h2 class="modal-title fs-5 fw-bold" id="installationCompletionTitle">Complete Installation?</h2>
                    <p class="small text-secondary mb-0" id="installationCompletionPrompt">Confirm that the meter has been physically installed.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Consumer Name</dt><dd class="col-7 fw-semibold" id="installationCompletionConsumer"></dd>
                    <dt class="col-5 text-secondary">Account Number</dt><dd class="col-7" id="installationCompletionAccount"></dd>
                    <dt class="col-5 text-secondary">Scheduled Date / Time</dt><dd class="col-7" id="installationCompletionDate"></dd>
                    <dt class="col-5 text-secondary">Current Status</dt><dd class="col-7" id="installationCompletionStatus"></dd>
                </dl>
            </div>
            <div class="modal-footer border-0 pt-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="installationCompletionCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="installationCompletionConfirm">
                    <span class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span>
                    <span>Confirm Complete</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('installationCompletionModal');
    if (!modalElement || !window.bootstrap) return;

    document.body.appendChild(modalElement);
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const confirmButton = document.getElementById('installationCompletionConfirm');
    let selectedForm;
    let trigger;

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-installation-complete-trigger]');
        if (!button || confirmButton.disabled) return;

        selectedForm = button.closest('.installation-completion-form');
        trigger = button;
        const isReconnection = selectedForm.hasAttribute('data-reconnection');
        document.getElementById('installationCompletionTitle').textContent = isReconnection ? 'Complete Reconnection?' : 'Complete Installation?';
        document.getElementById('installationCompletionPrompt').textContent = isReconnection
            ? 'Confirm that water service has been physically reconnected.'
            : 'Confirm that the meter has been physically installed.';
        document.getElementById('installationCompletionConsumer').textContent = selectedForm.dataset.consumerName;
        document.getElementById('installationCompletionAccount').textContent = selectedForm.dataset.accountNumber;
        document.getElementById('installationCompletionDate').textContent = selectedForm.dataset.scheduledDate;
        document.getElementById('installationCompletionStatus').textContent = selectedForm.dataset.installationStatus;
        modal.show(button);
    });

    confirmButton.addEventListener('click', function () {
        if (!selectedForm || confirmButton.disabled) return;

        const confirmation = selectedForm.elements.namedItem('physical_installation_confirmed');
        if (confirmation) confirmation.value = '1';
        confirmButton.disabled = true;
        confirmButton.querySelector('.spinner-border').classList.remove('d-none');
        confirmButton.querySelector('span:last-child').textContent = 'Saving…';
        selectedForm.requestSubmit();
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        if (confirmButton.disabled) return;
        selectedForm = null;
        trigger?.focus();
    });
});
</script>
@endpush
