<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['display' => 'table']));

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

foreach (array_filter((['display' => 'table']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<nav class="view-switcher" aria-label="طريقة العرض">
    <a href="<?php echo e(url()->current().'?'.http_build_query(array_merge(request()->except('page', 'view'), ['view' => 'table']))); ?>" class="<?php echo e($display === 'table' ? 'active' : ''); ?>" <?php if($display === 'table'): ?> aria-current="true" <?php endif; ?>>
        <span aria-hidden="true">☷</span> جدول
    </a>
    <a href="<?php echo e(url()->current().'?'.http_build_query(array_merge(request()->except('page', 'view'), ['view' => 'grid']))); ?>" class="<?php echo e($display === 'grid' ? 'active' : ''); ?>" <?php if($display === 'grid'): ?> aria-current="true" <?php endif; ?>>
        <span aria-hidden="true">▦</span> شبكة
    </a>
</nav>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/components/admin/view-switcher.blade.php ENDPATH**/ ?>