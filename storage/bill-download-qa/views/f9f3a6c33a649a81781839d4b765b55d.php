<?php $__env->startPush('styles'); ?>
    <style>
        <?php echo $__env->make('shared.billing.statement-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </style>
<?php $__env->stopPush(); ?>

<div class="app-shell d-lg-flex stmt-page">
    <?php echo $__env->make('layouts.sidebar', ['activePage' => 'billing-water'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <main class="main-content flex-grow-1">
        <?php echo $__env->make('layouts.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="content-wrapper">
            <div class="page-heading stmt-screen-only">
                <div>
                    <h1>Billing Statement</h1>
                    <p class="mb-0">Payment Reference Number: <strong><?php echo e($bill->prn); ?></strong></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo e(route('billing.index', array_filter(['billing_period' => ($selectedBillingPeriod ?? '') ?: null]))); ?>" class="btn btn-light border">
                        <i class="bi bi-arrow-left me-2"></i>Back to Billing
                    </a>
                    <a href="<?php echo e(route('billing.statement.pdf', ['bill' => $bill])); ?>" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download Bill
                    </a>
                </div>
            </div>

            <?php if(session('success')): ?>
                <div class="alert alert-success d-flex align-items-start gap-2 stmt-screen-only" role="alert">
                    <i class="bi bi-check-circle mt-1"></i>
                    <div><?php echo e(session('success')); ?></div>
                </div>
            <?php endif; ?>

            <?php echo $__env->make('shared.billing.statement-document', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </main>
</div>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/shared/billing/statement-page.blade.php ENDPATH**/ ?>