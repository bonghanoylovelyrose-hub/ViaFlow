<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['size' => 'sidebar']));

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

foreach (array_filter((['size' => 'sidebar']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php ($variant = in_array($size, ['sidebar', 'auth', 'hero'], true) ? $size : 'sidebar'); ?>
<img src="<?php echo e(asset('images/vvthoa-logo.png')); ?>" alt="VVTHOA Water System Services" class="organization-logo organization-logo--<?php echo e($variant); ?>" width="64" height="64">
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/components/organization-logo.blade.php ENDPATH**/ ?>