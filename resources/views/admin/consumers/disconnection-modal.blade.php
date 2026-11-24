<div class="modal fade" id="consumerDisconnectionModal" tabindex="-1" aria-labelledby="consumerDisconnectionTitle" aria-describedby="consumerDisconnectionMessage" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" class="modal-content bg-white" id="consumerDisconnectionForm">
            @csrf
            <div class="modal-header border-0 gap-2 px-4 pt-4 pb-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center p-2 rounded-3 bg-danger-subtle text-danger-emphasis" aria-hidden="true">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </span>
                    <h2 class="modal-title fs-5" id="consumerDisconnectionTitle">Confirm Account Disconnection?</h2>
                </div>
                <button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-0 pb-3">
                <p class="text-secondary mb-3" id="consumerDisconnectionMessage">Please confirm that the consumer's water service has already been physically disconnected. Once confirmed, the account will be transferred to Inactive Accounts.</p>
                <dl class="row g-2 small bg-light border rounded-3 p-3 mx-0 mt-0 mb-3">
                    <dt class="col-5">Consumer Name</dt><dd class="col-7 fw-semibold text-break mb-0" data-disconnection-field="name"></dd>
                    <dt class="col-5">Account Number</dt><dd class="col-7 text-break mb-0" data-disconnection-field="account"></dd>
                    <dt class="col-5">Meter Number</dt><dd class="col-7 text-break mb-0" data-disconnection-field="meter"></dd>
                    <dt class="col-5">Outstanding Balance</dt><dd class="col-7 fw-semibold mb-0">&#8369;<span data-disconnection-field="balance"></span></dd>
                    <dt class="col-5">Current Status</dt><dd class="col-7 fw-semibold text-danger-emphasis mb-0" data-disconnection-field="status"></dd>
                </dl>
                <label for="consumerDisconnectionReason" class="form-label fw-semibold">Disconnection reason <span class="text-secondary fw-normal">(optional)</span></label>
                <input class="form-control" id="consumerDisconnectionReason" type="text" name="disconnection_reason" maxlength="1000">
            </div>
            <div class="modal-footer border-0 px-3 px-sm-4 pt-0 pb-4 flex-nowrap">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger text-nowrap" disabled>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                    <span aria-live="polite">Confirm Disconnection</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('consumerDisconnectionModal');
            const form = document.getElementById('consumerDisconnectionForm');
            const confirmButton = form.querySelector('[type="submit"]');
            const spinner = confirmButton.querySelector('.spinner-border');
            const label = confirmButton.querySelector('[aria-live]');
            const dismissButtons = modal.querySelectorAll('[data-bs-dismiss="modal"]');
            let submitting = false;

            function setSubmitting(value) {
                submitting = value;
                confirmButton.disabled = value || !form.hasAttribute('action');
                dismissButtons.forEach(button => { button.disabled = value; });
                spinner.classList.toggle('d-none', !value);
                label.textContent = value ? 'Confirming…' : 'Confirm Disconnection';
            }

            modal.addEventListener('show.bs.modal', function (event) {
                const details = event.relatedTarget?.dataset;
                if (!details?.disconnectionUrl) {
                    event.preventDefault();
                    return;
                }

                form.reset();
                form.action = details.disconnectionUrl;
                modal.querySelector('[data-disconnection-field="name"]').textContent = details.disconnectionName;
                modal.querySelector('[data-disconnection-field="account"]').textContent = details.disconnectionAccount;
                modal.querySelector('[data-disconnection-field="meter"]').textContent = details.disconnectionMeter;
                modal.querySelector('[data-disconnection-field="balance"]').textContent = details.disconnectionBalance;
                modal.querySelector('[data-disconnection-field="status"]').textContent = details.disconnectionStatus;
                setSubmitting(false);
            });

            form.addEventListener('submit', function (event) {
                if (submitting || !form.hasAttribute('action')) {
                    event.preventDefault();
                    return;
                }

                setSubmitting(true);
            });

            modal.addEventListener('hide.bs.modal', function (event) {
                if (submitting) event.preventDefault();
            });

            modal.addEventListener('hidden.bs.modal', function () {
                form.removeAttribute('action');
                setSubmitting(false);
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted && submitting) {
                    setSubmitting(false);
                    bootstrap.Modal.getInstance(modal)?.hide();
                }
            });
        });
    </script>
@endpush
