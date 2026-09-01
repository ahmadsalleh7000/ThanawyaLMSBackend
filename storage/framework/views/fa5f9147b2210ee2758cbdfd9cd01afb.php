<?php $__env->startSection('title', 'دعم ومتابعة الطلاب'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header community-page-hero support-page-hero">
    <div><div class="eyebrow">تدخل مبكر قائم على البيانات</div><h1>دعم ومتابعة الطلاب</h1><p>حوّل إشارات التعثر إلى خطوات متابعة واضحة وقابلة للقياس.</p></div>
    <form method="post" action="<?php echo e(route('admin.support.scan')); ?>"><?php echo csrf_field(); ?><button class="button button-primary"><span aria-hidden="true">⌁</span> فحص المخاطر الآن</button></form>
</header>

<section class="community-stats support-page-stats" aria-label="ملخص حالات المتابعة">
    <a href="<?php echo e(route('admin.support.index',['status'=>'open'])); ?>"><span class="community-stat-icon">○</span><div><strong><?php echo e($counts['open']); ?></strong><small>حالات مفتوحة</small></div><em>تحتاج قراراً</em></a>
    <a href="<?php echo e(route('admin.support.index',['risk'=>'high'])); ?>" class="<?php echo e($counts['high'] ? 'attention' : ''); ?>"><span class="community-stat-icon">!</span><div><strong><?php echo e($counts['high']); ?></strong><small>مخاطر مرتفعة</small></div><em>أولوية المتابعة</em></a>
    <article><span class="community-stat-icon">✓</span><div><strong><?php echo e($counts['tasks']); ?></strong><small>مهام معلقة</small></div><em>بانتظار الإكمال</em></article>
</section>

<section class="grid content-columns support-layout community-workspace">
    <main class="stack">
        <div class="community-controlbar">
            <nav class="filter-pills community-filter-pills" aria-label="تصفية حالات المتابعة">
                <?php $__currentLoopData = [''=>'الكل','open'=>'مفتوحة','monitoring'=>'تحت المتابعة','resolved'=>'مغلقة']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="<?php echo e(request('status','')===$value?'active':''); ?>" href="<?php echo e(route('admin.support.index',array_filter(['status'=>$value,'risk'=>request('risk')]))); ?>"><?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <a class="priority-filter <?php echo e(request('risk')==='high'?'active':''); ?>" href="<?php echo e(route('admin.support.index',['risk'=>'high'])); ?>"><span aria-hidden="true">!</span> مرتفعة فقط</a>
            </nav>
            <div class="community-results"><strong><?php echo e($cases->total()); ?> حالة</strong><span>صفحة <?php echo e($cases->currentPage()); ?> من <?php echo e($cases->lastPage()); ?></span></div>
        </div>

        <div class="support-case-grid">
            <?php $__empty_1 = true; $__currentLoopData = $cases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $case): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <a class="card support-case support-case-<?php echo e($case->risk_level); ?>" href="<?php echo e(route('admin.support.show',$case)); ?>">
                <header class="support-case-head"><span class="risk-score <?php echo e($case->risk_level); ?>"><?php echo e($case->risk_score); ?></span><div><strong><?php echo e($case->student->name); ?></strong><small class="latin"><?php echo e($case->student->email); ?></small></div><span class="badge <?php echo e($case->status==='resolved'?'published':($case->risk_level==='high'?'inactive':'draft')); ?>"><?php echo e(['open'=>'مفتوحة','monitoring'=>'متابعة','resolved'=>'مغلقة'][$case->status]); ?></span></header>
                <div class="risk-signals"><?php $__currentLoopData = $case->displayRiskSignals(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($signal['label']); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                <div class="support-case-meta"><span><small>المسؤول</small><strong><?php echo e($case->assignee?->name ?? 'غير مسندة'); ?></strong></span><span><small>المتابعة القادمة</small><strong><?php echo e($case->next_follow_up_at?->format('Y/m/d H:i') ?? 'غير محددة'); ?></strong></span></div>
                <footer><span><?php echo e($case->pending_tasks_count); ?> مهام معلقة</span><span><?php echo e($case->activities_count); ?> أنشطة</span><b aria-hidden="true">←</b></footer>
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="card empty-state community-empty"><span class="empty-state-icon" aria-hidden="true">✓</span><strong>لا توجد حالات مطابقة</strong><span>لا توجد إشارات متابعة ضمن الفلتر الحالي.</span></div><?php endif; ?>
        </div>
        <div class="pagination"><?php echo e($cases->links()); ?></div>
    </main>

    <aside id="create-support-case"><details class="card community-side-card support-create" <?php if($errors->any()): ?> open <?php endif; ?>><summary><span><small>تدخل يدوي</small><strong>فتح حالة متابعة</strong></span><b aria-hidden="true">＋</b></summary><p>استخدمها عندما تلاحظ مشكلة لا تظهر في مؤشرات النظام.</p><form class="stack" method="post" action="<?php echo e(route('admin.support.store')); ?>"><?php echo csrf_field(); ?><label>الطالب<select name="student_id" required><option value="">اختر الطالب</option><?php $__currentLoopData = $students; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $student): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($student->id); ?>" <?php if(old('student_id')==$student->id): echo 'selected'; endif; ?>><?php echo e($student->name); ?> · <?php echo e($student->email); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label>درجة الخطورة<select name="risk_level"><option value="medium" <?php if(old('risk_level')==='medium'): echo 'selected'; endif; ?>>متوسطة</option><option value="high" <?php if(old('risk_level')==='high'): echo 'selected'; endif; ?>>مرتفعة</option></select></label><label>سبب المتابعة<textarea name="reason" required minlength="10" maxlength="2000" placeholder="اكتب ملاحظة واضحة تساعد فريق المتابعة..."><?php echo e(old('reason')); ?></textarea></label><div class="community-form-note"><span aria-hidden="true">i</span><small>ستظهر الحالة مباشرة في قائمة الأولويات.</small></div><button class="button button-primary">فتح الحالة</button></form></details></aside>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/support/index.blade.php ENDPATH**/ ?>