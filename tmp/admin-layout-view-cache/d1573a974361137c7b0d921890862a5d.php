<?php ($eligibility = $reconnectionEligibility[$consumer->id]); ?>
<button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#consumerReconnectModal"
    data-reconnect-url="<?php echo e(route('consumers.reconnect', $consumer)); ?>" data-reconnect-name="<?php echo e($consumer->full_name); ?>"
    data-reconnect-account="<?php echo e($consumer->account_number); ?>" data-reconnect-meter="<?php echo e($consumer->meter_number); ?>"
    data-reconnect-balance="<?php echo e(number_format($eligibility['outstanding'], 2)); ?>" <?php if(! $eligibility['eligible']): echo 'disabled'; endif; ?>>
    <i class="bi bi-plug me-1"></i>Reconnect
</button>
<?php if(! $eligibility['eligible']): ?>
    <div class="small text-secondary mt-2"><?php echo e($eligibility['reason']); ?></div>
    <a href="<?php echo e(route('payments.index', ['consumer_id' => $consumer->id])); ?>" class="small">Record / verify payment in Payments</a>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/consumers/reconnect-action.blade.php ENDPATH**/ ?>