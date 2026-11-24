<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'consumer',
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
    'consumer',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $display = app(\App\Services\ConsumerStatusService::class)->present($consumer);
?>

<span <?php echo e($attributes->class(['consumer-service-status'])->merge(['title' => $display['description']])); ?>>
    <span class="badge consumer-status-badge <?php echo e($display['badge_class']); ?>">
        <i class="bi <?php echo e($display['icon']); ?>" aria-hidden="true"></i>
        <span><?php echo e($display['label']); ?></span>
    </span>
    <span class="consumer-status-sub <?php echo e($display['sub_class']); ?>"><?php echo e($display['sub_label']); ?></span>
</span>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/components/consumer-status-badge.blade.php ENDPATH**/ ?>