<?php $__env->startSection('title', 'ملف الطالب'); ?>
<?php $__env->startSection('content'); ?>
<div class="student-hero">
    <span class="student-avatar" aria-hidden="true"><?php echo e(mb_substr($student->name, 0, 1)); ?></span>
    <div class="student-identity">
        <div class="eyebrow" style="color:#93c5fd">ملف المتعلم</div>
        <h1><?php echo e($student->name); ?></h1>
        <p><?php echo e($student->email); ?></p>
        <div class="student-meta"><span><?php echo e($student->is_active ? 'حساب نشط' : 'حساب معطل'); ?></span><span>انضم <?php echo e($student->created_at->diffForHumans()); ?></span><span><?php echo e($parents->count()); ?> أولياء أمور مرتبطون</span></div>
    </div>
    <div class="student-hero-actions">
        <a class="button button-quiet" href="<?php echo e(route('admin.users.index')); ?>">العودة للحسابات</a>
        <form method="post" action="<?php echo e(route('admin.users.toggle', $student)); ?>"><?php echo csrf_field(); ?><button class="button <?php echo e($student->is_active ? 'button-danger' : 'button-primary'); ?>"><?php echo e($student->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب'); ?></button></form>
    </div>
</div>

<section class="grid student-stats" aria-label="ملخص تعلم الطالب">
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">◫</span><div><strong><?php echo e($summary['courses']); ?></strong><span>دورات مسجلة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">✓</span><div><strong><?php echo e($summary['completedLessons']); ?></strong><span>دروس مكتملة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">↗</span><div><strong><?php echo e($summary['averageProgress']); ?>%</strong><span>متوسط التقدم</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">★</span><div><strong><?php echo e($summary['passedAssessments']); ?></strong><span>تقييمات ناجحة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">ج</span><div><strong><?php echo e(number_format($summary['totalPaid'])); ?></strong><span>مدفوعات مؤكدة ج.م</span></div></article>
</section>

<section class="grid content-columns">
    <div class="stack">
        <section class="card">
            <div class="section-heading"><div><div class="eyebrow">التقدم الدراسي</div><h2>الدورات الحالية</h2></div><span class="badge draft"><?php echo e($courses->count()); ?> دورات</span></div>
            <div class="grid">
                <?php $__empty_1 = true; $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <article class="course-progress-card list-row" style="display:grid">
                        <div class="course-progress-head"><div><h3><?php echo e($item['enrollment']->course->title); ?></h3><p><?php echo e($item['enrollment']->course->title_en); ?> · التحق <?php echo e($item['enrollment']->enrolled_at->diffForHumans()); ?></p></div><strong><?php echo e($item['progress']); ?>%</strong></div>
                        <div class="progress-track" role="progressbar" aria-label="تقدم <?php echo e($item['enrollment']->course->title); ?>" aria-valuenow="<?php echo e($item['progress']); ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo e($item['progress']); ?>%"></span></div>
                        <div class="progress-label"><span><?php echo e($item['completed']); ?> من <?php echo e($item['total']); ?> دروس</span><span><?php echo e($item['progress'] === 100 ? 'مكتملة' : 'قيد التعلم'); ?></span></div>
                        <div class="course-signals"><span class="signal">آخر تقييم: <?php echo e($item['attempt'] ? $item['attempt']->score.'%' : 'لا يوجد'); ?></span><span class="signal"><?php echo e($item['attempt']?->passed ? 'اجتاز التقييم' : 'لم يجتز بعد'); ?></span><span class="signal"><?php echo e($item['certificate'] ? 'صدرت الشهادة' : 'لا توجد شهادة'); ?></span></div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="empty-state">لم يلتحق الطالب بأي دورة حتى الآن.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card table-wrap">
            <div class="section-heading" style="padding:0 0 4px"><div><div class="eyebrow">النتائج</div><h2>محاولات التقييم</h2></div></div>
            <table><thead><tr><th>الدورة</th><th>النتيجة</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>
            <?php $__empty_1 = true; $__currentLoopData = $attempts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attempt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($attempt->assessment->course->title); ?></td><td><strong><?php echo e($attempt->score); ?>%</strong></td><td><span class="badge <?php echo e($attempt->passed ? 'published' : 'inactive'); ?>"><?php echo e($attempt->passed ? 'ناجح' : 'لم يجتز'); ?></span></td><td><?php echo e($attempt->submitted_at->format('Y/m/d')); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4"><div class="empty-state">لا توجد محاولات تقييم.</div></td></tr><?php endif; ?>
            </tbody></table>
        </section>

        <section class="card table-wrap">
            <div class="section-heading" style="padding:0 0 4px"><div><div class="eyebrow">الجانب المالي</div><h2>الطلبات والمدفوعات</h2></div></div>
            <table><thead><tr><th>المحتوى</th><th>المبلغ</th><th>الحالة</th><th>المزود</th><th>التاريخ</th></tr></thead><tbody>
            <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php ($productTitle = $order->subscriptionPlan?->name_ar ?? $order->bundle?->name ?? $order->course?->title ?? 'اشتراك أو طلب عام'); ?>
                <tr><td><?php echo e($productTitle); ?></td><td><?php echo e(number_format((float)$order->amount)); ?> <?php echo e($order->currency); ?></td><td><span class="badge <?php echo e($order->status === 'completed' ? 'published' : ($order->status === 'failed' ? 'inactive' : 'draft')); ?>"><?php echo e($order->status); ?></span></td><td class="latin"><?php echo e($order->payment_provider); ?></td><td><?php echo e(($order->paid_at ?? $order->created_at)->format('Y/m/d')); ?></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5"><div class="empty-state">لا توجد طلبات دفع لهذا الطالب.</div></td></tr><?php endif; ?>
            </tbody></table>
        </section>
    </div>

    <aside class="stack profile-sidebar">
        <section class="card"><div class="section-heading"><div><div class="eyebrow">الأسرة</div><h2>أولياء الأمور</h2></div></div><div class="detail-list"><?php $__empty_1 = true; $__currentLoopData = $parents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong><?php echo e($parent->name); ?></strong><small class="latin"><?php echo e($parent->email); ?></small><small><?php echo e($parent->pivot->grade ?? 'الصف غير محدد'); ?></small></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا يوجد ولي أمر مرتبط.</div><?php endif; ?></div></section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">آخر نشاط</div><h2>الدروس المكتملة</h2></div></div><div class="detail-list"><?php $__empty_1 = true; $__currentLoopData = $recentCompletions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $completion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong><?php echo e($completion->lesson->title); ?></strong><small><?php echo e($completion->lesson->title_en); ?></small><small><?php echo e($completion->completed_at->diffForHumans()); ?></small></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا يوجد نشاط تعلّم بعد.</div><?php endif; ?></div></section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">الإنجاز</div><h2>الشهادات</h2></div></div><div class="detail-list"><?php $__empty_1 = true; $__currentLoopData = $certificates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $certificate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong><?php echo e($certificate->course->title); ?></strong><small class="latin"><?php echo e($certificate->public_id); ?></small><small>صدرت <?php echo e($certificate->issued_at->diffForHumans()); ?></small></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لم تصدر شهادات بعد.</div><?php endif; ?></div></section>
    </aside>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/users/show.blade.php ENDPATH**/ ?>