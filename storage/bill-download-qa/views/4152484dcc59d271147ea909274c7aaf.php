<section class="panel-card p-4 mt-4" aria-labelledby="disconnectionHistoryTitle">
    <h2 class="section-title mb-3" id="disconnectionHistoryTitle">Disconnection History</h2>
    <p class="small text-secondary">Each row retains the account status and recorded dates for that notice or disconnection cycle.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Disconnection Date</th>
                    <th>Reason</th>
                    <th>Outstanding Balance at Disconnection</th>
                    <th>Payment Settlement Date</th>
                    <th>Reconnection Date</th>
                    <th>Account Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $disconnectionHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cycle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <?php echo e($cycle['disconnection_date']?->format('M d, Y g:i A') ?? 'Not recorded / not disconnected'); ?>

                            <?php if($cycle['notice_date']): ?>
                                <div class="small text-secondary">Notice: <?php echo e($cycle['notice_date']->format('M d, Y g:i A')); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($cycle['reason'] ?: 'Not recorded'); ?></td>
                        <td><?php echo e($cycle['outstanding_balance'] === null ? 'Not recorded' : 'PHP '.number_format((float) $cycle['outstanding_balance'], 2)); ?></td>
                        <td><?php echo e($cycle['payment_settlement_date']?->format('M d, Y g:i A') ?? 'Not recorded'); ?></td>
                        <td>
                            <?php echo e($cycle['reconnection_date']?->format('M d, Y') ?? 'Not recorded'); ?>

                            <?php if($cycle['activated_at']): ?>
                                <div class="small text-secondary">Activated: <?php echo e($cycle['activated_at']->format('M d, Y g:i A')); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($cycle['account_status']); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">No disconnection history on record.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/consumer/account/disconnection-history.blade.php ENDPATH**/ ?>