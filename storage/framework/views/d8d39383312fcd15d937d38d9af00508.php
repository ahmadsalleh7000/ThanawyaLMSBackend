<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['href', 'active' => false, 'icon', 'label']));

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

foreach (array_filter((['href', 'active' => false, 'icon', 'label']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<a href="<?php echo e($href); ?>" class="<?php echo e($active ? 'active' : ''); ?>" <?php if($active): ?> aria-current="page" <?php endif; ?>>
    <span class="nav-icon" aria-hidden="true">
        <?php switch($icon):
            case ('home'): ?><svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6"/></svg><?php break; ?>
            <?php case ('course'): ?><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16M8 7h8"/></svg><?php break; ?>
            <?php case ('question'): ?><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM9 8h6M9 12h4M16 16h.01"/></svg><?php break; ?>
            <?php case ('media'): ?><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 15 3-3 3 3 2-2 3 3M8 8h.01"/></svg><?php break; ?>
            <?php case ('teacher'): ?><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg><?php break; ?>
            <?php case ('users'): ?><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2M17 11h5"/></svg><?php break; ?>
            <?php case ('support'): ?><svg viewBox="0 0 24 24"><path d="M12 21a9 9 0 1 0-9-9M3 21v-6h6M8 12h8"/></svg><?php break; ?>
            <?php case ('enrollment'): ?><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h12"/><circle cx="19" cy="18" r="2"/></svg><?php break; ?>
            <?php case ('orders'): ?><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/></svg><?php break; ?>
            <?php case ('payment'): ?><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM4 9h16M8 14h4m3 1 2 2 3-4"/></svg><?php break; ?>
            <?php case ('reconcile'): ?><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7m5-1 2 2 4-5"/></svg><?php break; ?>
            <?php case ('analytics'): ?><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><?php break; ?>
            <?php case ('trust'): ?><svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6zm-3 9 2 2 4-5"/></svg><?php break; ?>
            <?php case ('announce'): ?><svg viewBox="0 0 24 24"><path d="M4 13V9a2 2 0 0 1 2-2h3l7-4v16l-7-4H6a2 2 0 0 1-2-2Z"/></svg><?php break; ?>
            <?php case ('staff'): ?><svg viewBox="0 0 24 24"><circle cx="8.5" cy="7" r="4"/><path d="M1 21v-2a5 5 0 0 1 5-5h5a5 5 0 0 1 5 5v2m2-13v6m-3-3h6"/></svg><?php break; ?>
            <?php default: ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/></svg>
        <?php endswitch; ?>
    </span>
    <span><?php echo e($label); ?></span>
</a>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/components/admin/nav-link.blade.php ENDPATH**/ ?>