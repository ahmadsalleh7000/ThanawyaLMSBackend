<section class="management-list courses-list-table course-hierarchy-list" aria-label="قائمة الدورات التعليمية">
    <?php if($courses->isNotEmpty()): ?>
        <div class="course-list-header">
            <a href="<?php echo e(route('admin.courses.index', array_merge(request()->query(), ['sort' => 'title']))); ?>" <?php if(request('sort')==='title'): ?> aria-current="true" <?php endif; ?>>الدورة <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4-4 4 4M12 6v12"/></svg></a>
            <a href="<?php echo e(route('admin.courses.index', array_merge(request()->query(), ['sort' => 'content']))); ?>" <?php if(request('sort')==='content'): ?> aria-current="true" <?php endif; ?>>المحتوى <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4-4 4 4M12 6v12"/></svg></a>
            <span>جاهزية المحتوى</span>
            <span>الإجراءات</span>
        </div>
    <?php endif; ?>
    <?php $__empty_1 = true; $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $status = $course->archived_at ? 'archived' : ($course->is_published ? 'published' : 'draft');
            $term = $course->academicSubject?->term;
            $context = $course->isGeneral() ? 'دورة عامة · جميع الطلاب' : collect([$term?->academicYear?->name_ar, $term?->grade?->name_ar, $term?->name_ar])->filter()->join(' · ');
            $compactContext = $course->isGeneral() ? 'دورة عامة' : collect([$term?->grade?->name_ar, $term?->name_ar])->filter()->join(' · ');
            $draftLessons = max(0, $course->lessons_count - $course->published_lessons_count);
            $readinessHint = $course->units_count === 0 ? 'لا توجد وحدات بعد' : ($course->lessons_count === 0 ? 'أضف دروس الدورة' : ($draftLessons ? $draftLessons.' درس مسودة' : ($course->list_readiness_score >= 80 ? 'جاهزة للمراجعة والنشر' : 'تحتاج استكمال المحتوى')));
        ?>
        <article class="management-row course-hierarchy-row course-row-status-<?php echo e($status); ?>">
            <div class="course-hierarchy-identity course-identity-tooltip" tabindex="0" aria-describedby="course-meta-<?php echo e($loop->index); ?>">
                <div class="course-identity-visible"><strong class="course-identity-name"><?php echo e($course->title); ?></strong><?php if($compactContext): ?><small>(<?php echo e($compactContext); ?>)</small><?php endif; ?></div>
                <div class="course-identity-popover" id="course-meta-<?php echo e($loop->index); ?>" role="tooltip">
                    <div><strong><?php echo e($course->title); ?></strong><span class="badge <?php echo e($status); ?>"><?php echo e($status === 'archived' ? 'مؤرشفة' : ($status === 'published' ? 'منشورة' : 'مسودة')); ?></span></div>
                    <small lang="en" dir="ltr"><?php echo e($course->title_en); ?></small>
                    <p><?php echo e($context ?: 'لم يكتمل السياق الأكاديمي'); ?></p>
                </div>
            </div>
            <div class="course-hierarchy-counts" aria-label="ملخص محتوى الدورة">
                <span><b><?php echo e($course->units_count); ?></b><small>وحدات</small></span><span><b><?php echo e($course->lessons_count); ?></b><small>دروس</small></span><span><b><?php echo e($course->resources_count); ?></b><small>موارد</small></span><span><b><?php echo e($course->content_trainings_count); ?></b><small>تدريبات</small></span>
            </div>
            <div class="course-hierarchy-readiness">
                <div><span>جاهزية المحتوى</span><b><?php echo e($course->list_readiness_score); ?>%</b></div><div class="progress-track" role="progressbar" aria-label="جاهزية محتوى <?php echo e($course->title); ?>" aria-valuenow="<?php echo e($course->list_readiness_score); ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo e($course->list_readiness_score); ?>%"></span></div><small><?php echo e($readinessHint); ?></small>
            </div>
            <div class="management-actions course-hierarchy-actions">
                <a class="primary" href="<?php echo e(route('admin.learning-paths.courses.units',$course)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/></svg> إدارة الوحدات</a>
                <details class="learning-actions-menu course-table-actions"><summary aria-label="المزيد من إجراءات <?php echo e($course->title); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg><span>المزيد</span></summary><div>
                    <span class="course-menu-label">إدارة الدورة</span>
                    <a href="<?php echo e(route('admin.courses.edit',$course)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.5-1 10-10-3.5-3.5-10 10zM13.5 6.5 17 10"/></svg><span>تحرير الدورة</span></a>
                    <a href="<?php echo e(route('admin.courses.preview',$course)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/></svg><span>معاينة الدورة</span></a>
                    <a href="<?php echo e(route('admin.courses.readiness',$course)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg><span>فحص الجاهزية</span></a>
                    <span class="course-menu-label course-menu-divider">النشر والحالة</span>
                    <form method="post" action="<?php echo e(route('admin.courses.state',[$course,$course->archived_at?'restore':($course->is_published?'unpublish':'publish')])); ?>"><?php echo csrf_field(); ?><button><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M5 14v6h14v-6"/></svg><span><?php echo e($course->archived_at?'استعادة الدورة':($course->is_published?'إلغاء النشر':'نشر الدورة')); ?></span></button></form>
                    <?php if (! ($course->archived_at)): ?><form method="post" action="<?php echo e(route('admin.courses.state',[$course,'archive'])); ?>"><?php echo csrf_field(); ?><button class="danger-text"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 3h18v4H3zM9 11h6"/></svg><span>أرشفة الدورة</span></button></form><?php endif; ?>
                </div></details>
            </div>
        </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="empty-state academic-empty-state course-table-empty"><img class="academic-empty-illustration" src="<?php echo e(asset('images/admin/academic-empty-state.webp')); ?>" alt="" aria-hidden="true"><div><strong>لا توجد دورات مطابقة</strong><span>غيّر عوامل التصفية أو أنشئ دورة تعليمية جديدة.</span></div><a class="button button-primary academic-empty-action" href="<?php echo e(route('admin.courses.create')); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إنشاء دورة</a></div>
    <?php endif; ?>
</section>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/courses/table.blade.php ENDPATH**/ ?>