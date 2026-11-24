<div class="modal fade" id="consumerReconnectModal" tabindex="-1" aria-labelledby="consumerReconnectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="consumerReconnectForm" onsubmit="return confirm('Activate this account and mark the water service Active and Connected?');">
            <?php echo csrf_field(); ?>
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="consumerReconnectTitle">Confirm Reconnection and Activation</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong id="reconnectConsumerName"></strong> &middot; Account <span id="reconnectAccountNumber"></span></p>
                <p>Meter: <span id="reconnectMeterNumber"></span> &middot; Status: Inactive</p>
                <p>Outstanding Balance: &#8369;<strong id="reconnectOutstandingBalance"></strong></p>
                <p>Activation requires a zero outstanding balance. Approval will mark the account Active and Connected and email the consumer the schedule.</p>
                <div class="mb-3">
                    <label class="form-label" for="reconnectDate">Reconnection Date</label>
                    <input type="date" id="reconnectDate" name="reconnection_date" class="form-control" value="<?php echo e(today()->toDateString()); ?>" min="<?php echo e(today()->toDateString()); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="reconnectSchedule">Reconnection Date and Time</label>
                    <input type="datetime-local" id="reconnectSchedule" name="installation_scheduled_at" class="form-control" min="<?php echo e(now()->format('Y-m-d\TH:i')); ?>" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="reconnectPaymentConfirmed" name="confirm_full_payment" value="1" required>
                    <label class="form-check-label" for="reconnectPaymentConfirmed">I confirm the account has no outstanding balance and approve activation.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Activate Account</button>
            </div>
        </form>
    </div>
</div>
<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('consumerReconnectModal').addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            const form = document.getElementById('consumerReconnectForm');
            form.reset();
            form.action = trigger.dataset.reconnectUrl;
            document.getElementById('reconnectConsumerName').textContent = trigger.dataset.reconnectName;
            document.getElementById('reconnectAccountNumber').textContent = trigger.dataset.reconnectAccount;
            document.getElementById('reconnectMeterNumber').textContent = trigger.dataset.reconnectMeter;
            document.getElementById('reconnectOutstandingBalance').textContent = trigger.dataset.reconnectBalance;
        });
    });
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/consumers/reconnect-modal.blade.php ENDPATH**/ ?>