<?php ($activePage = 'billing'); ?>

<?php $__env->startSection('title', 'Billing Statement'); ?>

<?php $__env->startSection('body'); ?>
    <?php echo $__env->make('shared.billing.statement-page', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/admin/billing/statement.blade.php ENDPATH**/ ?>