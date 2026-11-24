<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['usage']));

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

foreach (array_filter((['usage']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="small mt-2 mb-3" data-reschedule-used="<?php echo e($usage['used']); ?>" data-reschedule-remaining="<?php echo e($usage['remaining']); ?>">
    <strong><?php echo e($usage['used']); ?> of <?php echo e($usage['limit']); ?> used</strong>
    &middot; <?php echo e($usage['remaining']); ?> reschedule attempt<?php echo e($usage['remaining'] === 1 ? '' : 's'); ?> remaining.
    <?php if(! $usage['available']): ?>
        <div class="fw-semibold">Reschedule limit reached. No further installation rescheduling is allowed.</div>
    <?php endif; ?>
</div>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/components/reschedule-usage.blade.php ENDPATH**/ ?>