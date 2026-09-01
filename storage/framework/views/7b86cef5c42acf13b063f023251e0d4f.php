<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1739">
    <meta name="color-scheme" content="light">
    <title><?php echo $__env->yieldContent('title', 'الإدارة'); ?> | Ahmed LMS</title>
    <link rel="icon" href="<?php echo e(asset('icons/icon.svg')); ?>?v=<?php echo e(filemtime(public_path('icons/icon.svg'))); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>?v=<?php echo e(filemtime(public_path('css/admin.css'))); ?>">
    <script defer src="<?php echo e(asset('js/admin.js')); ?>?v=<?php echo e(filemtime(public_path('js/admin.js'))); ?>"></script>
</head>
<body>
<a class="skip-link" href="#admin-content">انتقل إلى المحتوى</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="التنقل الإداري">
        <div class="sidebar-head">
            <a class="brand" href="<?php echo e(route('admin.dashboard')); ?>">
                <span class="brand-mark" aria-hidden="true">A</span>
                <span class="brand-copy"><strong>Ahmed LMS</strong><small>مركز إدارة الأكاديمية</small></span>
            </a>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="إغلاق قائمة الإدارة">×</button>
        </div>
        <?php echo $__env->make('admin.partials.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="admin-account">
            <span class="account-avatar" aria-hidden="true"><?php echo e(mb_substr(auth()->user()->name, 0, 1)); ?></span>
            <span class="account-copy"><strong><?php echo e(auth()->user()->name); ?></strong><small><?php echo e(config('admin_roles.roles.'.auth()->user()->adminRole().'.label')); ?></small></span>
            <form method="post" action="<?php echo e(route('admin.logout')); ?>"><?php echo csrf_field(); ?>
                <button class="logout-button" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h4a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-4"/></svg>
                </button>
            </form>
        </div>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close tabindex="-1" aria-label="إغلاق قائمة الإدارة"></button>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div class="topbar-start">
                <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="فتح قائمة الإدارة"><span></span><span></span><span></span></button>
                <div class="breadcrumb"><span>Ahmed LMS</span><span aria-hidden="true">/</span><strong><?php echo $__env->yieldContent('title', 'الإدارة'); ?></strong></div>
            </div>
            <div class="topbar-meta">
                <details class="admin-notifications">
                    <summary aria-label="الإشعارات<?php echo e($adminUnreadNotificationCount ? '، '.$adminUnreadNotificationCount.' غير مقروءة' : ''); ?>">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                        <?php if($adminUnreadNotificationCount): ?><span class="notification-count"><?php echo e($adminUnreadNotificationCount > 99 ? '99+' : $adminUnreadNotificationCount); ?></span><?php endif; ?>
                    </summary>
                    <div class="admin-notification-menu">
                        <header>
                            <div><strong>الإشعارات</strong><small><?php echo e($adminUnreadNotificationCount ? $adminUnreadNotificationCount.' تحتاج انتباهك' : 'لا توجد إشعارات جديدة'); ?></small></div>
                            <?php if($adminUnreadNotificationCount): ?>
                                <form method="post" action="<?php echo e(route('admin.notifications.read-all')); ?>"><?php echo csrf_field(); ?><button type="submit">تعليم الكل كمقروء</button></form>
                            <?php endif; ?>
                        </header>
                        <div class="admin-notification-list">
                            <?php $__empty_1 = true; $__currentLoopData = $adminHeaderNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <form method="post" action="<?php echo e(route('admin.notifications.read', $notification)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button class="admin-notification-item <?php echo e($notification->read_at ? '' : 'is-unread'); ?>" type="submit">
                                        <span class="notification-item-icon" aria-hidden="true">
                                            <?php if($notification->type === 'student_registration'): ?>
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                                            <?php else: ?>
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                                            <?php endif; ?>
                                        </span>
                                        <span class="notification-item-copy"><strong><?php echo e($notification->title_ar ?: $notification->title); ?></strong><small><?php echo e(str($notification->message_ar ?: $notification->message)->limit(88)); ?></small><time datetime="<?php echo e($notification->created_at->toISOString()); ?>"><?php echo e($notification->created_at->diffForHumans()); ?></time></span>
                                        <?php if(!$notification->read_at): ?><i aria-label="غير مقروء"></i><?php endif; ?>
                                    </button>
                                </form>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="admin-notification-empty"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><strong>كل شيء هادئ</strong><span>ستظهر طلبات التسجيل والتنبيهات المهمة هنا.</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
                <span class="secure-pill"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>جلسة إدارية محمية</span>
            </div>
        </header>
        <main class="admin-main" id="admin-content" tabindex="-1">
            <?php echo $__env->make('admin.partials.background-art', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php if(session('success')): ?><div class="flash" role="status"><strong>تم بنجاح</strong><span><?php echo e(session('success')); ?></span></div><?php endif; ?>
            <?php if(session('error')): ?><div class="errors" role="alert"><div><strong>تعذر تنفيذ العملية</strong><ul><li><?php echo e(session('error')); ?></li></ul></div></div><?php endif; ?>
            <?php if($errors->any()): ?><div class="errors" role="alert"><div><strong>راجع البيانات المدخلة</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div></div><?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
</div>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/layout.blade.php ENDPATH**/ ?>