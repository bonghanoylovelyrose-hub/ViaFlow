<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'status',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'status',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $normalizedStatus = trim((string) $status);
    $badgeClass = match ($normalizedStatus) {
        'Paid', 'Verified', 'Approved', 'Active', 'Completed', 'Encoded', 'Resolved' => 'badge-soft-success',
        'Overdue', 'Rejected', 'Needs Revisit', 'Disconnected' => 'badge-soft-danger',
        'Pending', 'Pending Review', 'Pending Verification', 'Unpaid', 'Paid with Balance', 'Ongoing', 'For Review', 'For Reconnection' => 'badge-soft-warning',
        default => 'badge-soft-primary',
    };
?>

<span <?php echo e($attributes->class(['badge', 'status-badge', $badgeClass])); ?>>
    <?php echo e($normalizedStatus !== '' ? $normalizedStatus : 'N/A'); ?>

</span>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/components/status-badge.blade.php ENDPATH**/ ?>