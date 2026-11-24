<div class="modal fade" id="consumerReconnectModal" tabindex="-1" aria-labelledby="consumerReconnectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="consumerReconnectForm">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="consumerReconnectTitle">Confirm Reconnection and Activation</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong id="reconnectConsumerName"></strong> &middot; Account <span id="reconnectAccountNumber"></span></p>
                <p>Meter: <span id="reconnectMeterNumber"></span> &middot; Status: Inactive</p>
                <p>Outstanding Balance: &#8369;<strong id="reconnectOutstandingBalance"></strong></p>
                <p>Activation requires a zero outstanding balance. The account will become Active with Pending Reconnection, and the consumer will receive the schedule by email.</p>
                <div class="mb-3">
                    <label class="form-label" for="reconnectOriginalInstallationDate">Original Installation Date</label>
                    <input type="text" id="reconnectOriginalInstallationDate" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="reconnectSchedule">Scheduled Reconnection Date &amp; Time</label>
                    <input type="datetime-local" id="reconnectSchedule" name="installation_scheduled_at" class="form-control" step="1" min="{{ now(\App\Support\ReconnectionSchedule::TIMEZONE)->format('Y-m-d\TH:i:s') }}" aria-describedby="reconnectTimeZone" required>
                    <div class="form-text" id="reconnectTimeZone">Philippine local time (Asia/Manila)</div>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="reconnectPaymentConfirmed" name="confirm_full_payment" value="1" required>
                    <label class="form-check-label" for="reconnectPaymentConfirmed">I confirm that the outstanding balance is zero and approve the reconnection schedule.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <span data-activation-idle>Activate Account</span>
                    <span data-activation-loading hidden><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Activating…</span>
                </button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('consumerReconnectModal');
        const form = document.getElementById('consumerReconnectForm');
        const schedule = document.getElementById('reconnectSchedule');
        const submitButton = form.querySelector('button[type="submit"]');
        const dismissButtons = form.querySelectorAll('[data-bs-dismiss="modal"]');
        const idleLabel = submitButton.querySelector('[data-activation-idle]');
        const loadingLabel = submitButton.querySelector('[data-activation-loading]');
        const philippineTime = new Intl.DateTimeFormat('en', {
            timeZone: @json(\App\Support\ReconnectionSchedule::TIMEZONE),
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
        });

        function refreshMinimum() {
            const parts = Object.fromEntries(philippineTime.formatToParts(new Date()).map(part => [part.type, part.value]));
            schedule.min = parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute + ':' + parts.second;
        }

        function setSubmitting(submitting) {
            submitButton.disabled = submitting;
            dismissButtons.forEach(button => { button.disabled = submitting; });
            form.setAttribute('aria-busy', String(submitting));
            idleLabel.hidden = submitting;
            loadingLabel.hidden = !submitting;
        }

        modal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            form.reset();
            refreshMinimum();
            form.action = trigger.dataset.reconnectUrl;
            document.getElementById('reconnectConsumerName').textContent = trigger.dataset.reconnectName;
            document.getElementById('reconnectAccountNumber').textContent = trigger.dataset.reconnectAccount;
            document.getElementById('reconnectMeterNumber').textContent = trigger.dataset.reconnectMeter;
            document.getElementById('reconnectOutstandingBalance').textContent = trigger.dataset.reconnectBalance;
            document.getElementById('reconnectOriginalInstallationDate').value = trigger.dataset.originalInstallationDate;
        });

        schedule.addEventListener('focus', refreshMinimum);
        schedule.addEventListener('input', refreshMinimum);

        form.addEventListener('submit', function (event) {
            refreshMinimum();
            if (submitButton.disabled || !schedule.reportValidity()) {
                event.preventDefault();
                return;
            }

            setSubmitting(true);
        });

        modal.addEventListener('hide.bs.modal', function (event) {
            if (submitButton.disabled) event.preventDefault();
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) setSubmitting(false);
        });
    });
</script>
@endpush
