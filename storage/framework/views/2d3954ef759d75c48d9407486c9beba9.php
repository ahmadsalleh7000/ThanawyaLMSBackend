<?php $__env->startSection('title', 'نظرة عامة'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $pendingTaskCount = collect($tasks)->sum('count');
    $unreadNotificationCount = $recentNotifications->whereNull('read_at')->count();
?>

<header class="page-header dashboard-hero">
    <div class="dashboard-hero-copy">
        <span class="dashboard-hero-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></span>
        <div>
            <div class="eyebrow"><?php echo e($roleLabel); ?></div>
            <h1>مرحباً، <?php echo e(explode(' ', auth()->user()->name)[0]); ?></h1>
            <p>ابدأ بالأعمال ذات الأولوية، ثم انتقل إلى الأدوات المتاحة وفق صلاحياتك.</p>
        </div>
    </div>
    <div class="dashboard-hero-meta">
        <span class="dashboard-workload <?php echo e($pendingTaskCount ? 'has-work' : ''); ?>"><b><?php echo e(number_format($pendingTaskCount)); ?></b><small>عناصر معلقة</small></span>
        <span class="dashboard-workload"><b><?php echo e(number_format($unreadNotificationCount)); ?></b><small>إشعارات جديدة</small></span>
        <time class="dashboard-date" datetime="<?php echo e(now()->toDateString()); ?>"><strong><?php echo e(now()->translatedFormat('l')); ?></strong><span><?php echo e(now()->translatedFormat('j F Y')); ?></span></time>
    </div>
</header>

<?php if(count($stats)): ?>
<section class="dashboard-overview" aria-labelledby="dashboard-overview-title">
    <div class="dashboard-section-heading"><div><div class="eyebrow">لمحة سريعة</div><h2 id="dashboard-overview-title">مؤشرات مساحة العمل</h2></div><small>بيانات محدثة الآن</small></div>
    <div class="grid dashboard-stats">
        <?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="card dashboard-stat stat-tone-<?php echo e($stat['tone']); ?>">
                <span class="dashboard-stat-icon" aria-hidden="true">
                    <?php switch($stat['key']):
                        case ('students'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v2M17 8h5M19.5 5.5v5"/></svg><?php break; ?>
                        <?php case ('parents'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M14 15.5c.7-.9 1.8-1.5 3-1.5h1a4 4 0 0 1 4 4v2"/></svg><?php break; ?>
                        <?php case ('contact_messages'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M8 9h8M8 13h5"/></svg><?php break; ?>
                        <?php case ('courses'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5v14Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg><?php break; ?>
                        <?php case ('drafts'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 15h8M8 11h3"/></svg><?php break; ?>
                        <?php case ('enrollments'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a6 6 0 0 1 6-6h2a6 6 0 0 1 5 2.7M16 19l2 2 4-5"/></svg><?php break; ?>
                        <?php case ('revenue'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M16 15h2"/></svg><?php break; ?>
                        <?php case ('payments'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 7h11l-3-3M17 17H6l3 3M18 7l-3 3M6 17l3-3"/></svg><?php break; ?>
                        <?php case ('incidents'): ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg><?php break; ?>
                        <?php default: ?><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>
                    <?php endswitch; ?>
                </span>
                <div><strong><?php echo e(number_format($stat['value'], 0)); ?><?php if($stat['currency']): ?> <small>ج.م</small><?php endif; ?></strong><span><?php echo e($stat['label']); ?></span><small><?php echo e($stat['context']); ?></small></div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>
<?php endif; ?>

<section class="dashboard-priority-grid">
    <div class="card dashboard-priorities">
        <div class="section-heading"><div><div class="eyebrow">الأولوية الآن</div><h2>أعمال تحتاج انتباهك</h2></div><span class="queue-total"><?php echo e(number_format($pendingTaskCount)); ?> عناصر</span></div>
        <div class="priority-list">
            <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a class="priority-item priority-<?php echo e($task['tone']); ?>" href="<?php echo e($task['url']); ?>">
                    <span class="priority-count"><?php echo e(number_format($task['count'])); ?></span>
                    <span class="priority-copy"><strong><?php echo e($task['title']); ?></strong><small><?php echo e($task['description']); ?></small></span>
                    <span class="priority-arrow" aria-hidden="true">←</span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="dashboard-clear-state"><span aria-hidden="true">✓</span><div><strong>لا توجد أعمال معلقة</strong><small>كل القوائم ضمن صلاحياتك محدثة حالياً.</small></div></div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="card dashboard-actions">
        <div class="section-heading"><div><div class="eyebrow">وصول سريع</div><h2>ابدأ مهمة</h2></div></div>
        <div class="quick-action-list">
            <?php $__currentLoopData = $quickActions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($action['url']); ?>"><span class="quick-action-icon" aria-hidden="true"><?php switch($action['key']):case ('course'): ?>＋<?php break; ?> <?php case ('payment'): ?>↔<?php break; ?> <?php case ('enrollment'): ?>✓<?php break; ?> <?php case ('support'): ?>◎<?php break; ?> <?php case ('analytics'): ?>⌁<?php break; ?> <?php default: ?>⌘<?php endswitch; ?></span><span><strong><?php echo e($action['label']); ?></strong><small><?php echo e($action['description']); ?></small></span><b aria-hidden="true">←</b></a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </aside>
</section>

<section class="dashboard-lower-grid">
    <?php if($recentNotifications->isNotEmpty()): ?>
    <div class="card dashboard-notifications" aria-labelledby="dashboard-notifications-title">
        <div class="section-heading"><div><div class="eyebrow">التنبيهات</div><h2 id="dashboard-notifications-title">آخر التحديثات</h2></div><span class="queue-total"><?php echo e($unreadNotificationCount); ?> جديدة</span></div>
        <div class="dashboard-compact-list">
            <?php $__currentLoopData = $recentNotifications->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <form method="post" action="<?php echo e(route('admin.notifications.read', $notification)); ?>"><?php echo csrf_field(); ?>
                    <button class="dashboard-notification-row <?php echo e($notification->read_at ? '' : 'is-unread'); ?>" type="submit"><span class="activity-marker" aria-hidden="true"></span><span class="activity-copy"><strong><?php echo e($notification->title_ar ?: $notification->title); ?></strong><small><?php echo e(str($notification->message_ar ?: $notification->message)->limit(72)); ?></small><time datetime="<?php echo e($notification->created_at->toISOString()); ?>"><?php echo e($notification->created_at->diffForHumans()); ?></time></span><?php if(!$notification->read_at): ?><i aria-label="جديد"></i><?php endif; ?></button>
                </form>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if(auth()->user()->hasAdminPermission('content.manage')): ?>
    <div class="card dashboard-courses">
        <div class="section-heading"><div><div class="eyebrow">المحتوى</div><h2>أحدث الدورات</h2></div><a href="<?php echo e(route('admin.courses.index')); ?>">عرض الكل ←</a></div>
        <div class="dashboard-compact-list">
            <?php $__empty_1 = true; $__currentLoopData = $recentCourses->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a class="dashboard-course-row" href="<?php echo e(route('admin.courses.edit', $course)); ?>"><span class="course-row-thumb" aria-hidden="true"><?php if($course->image_url): ?><img src="<?php echo e($course->image_url); ?>" alt=""><?php else: ?>&lt;/&gt;<?php endif; ?></span><span><strong><?php echo e($course->title); ?></strong><small><?php echo e($course->lessons_count); ?> دروس · <?php echo e($course->title_en); ?></small></span><span class="badge <?php echo e($course->archived_at ? 'archived' : ($course->is_published ? 'published' : 'draft')); ?>"><?php echo e($course->archived_at ? 'مؤرشفة' : ($course->is_published ? 'منشورة' : 'مسودة')); ?></span></a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا توجد دورات بعد.</div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card dashboard-activity">
        <div class="section-heading"><div><div class="eyebrow">النشاط</div><h2><?php echo e(auth()->user()->hasAdminPermission('system.manage') ? 'آخر نشاطات الإدارة' : 'آخر إجراءاتك'); ?></h2></div></div>
        <div class="dashboard-compact-list">
            <?php $__empty_1 = true; $__currentLoopData = $recentActivity->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="dashboard-activity-row"><span class="activity-marker" aria-hidden="true"></span><span><strong><?php echo e(str_replace('.', ' · ', $item->action)); ?></strong><small><?php echo e($item->user?->name ?? 'النظام'); ?> · <?php echo e($item->created_at->diffForHumans()); ?></small></span></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">ستظهر الإجراءات الإدارية هنا.</div><?php endif; ?>
        </div>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>