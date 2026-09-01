<?php $__env->startSection('title', 'الدورات'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header course-catalog-hero">
    <div class="course-catalog-hero-copy"><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16M8 7h8"/></svg></span><div><div class="eyebrow">استوديو المحتوى</div><h1>الدورات التعليمية</h1><p>راقب الجاهزية، نظّم المحتوى، وانشر تجربة تعليمية مكتملة.</p></div></div>
    <a class="button button-primary" href="<?php echo e(route('admin.courses.create')); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إنشاء دورة</a>
</header>

<section class="course-catalog-stats" aria-label="ملخص الدورات">
    <a href="<?php echo e(route('admin.courses.index')); ?>" class="<?php echo e(request('status') ? '' : 'selected'); ?>"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16"/></svg></span><div><strong><?php echo e($stats['total']); ?></strong><small>كل الدورات</small></div></a>
    <a href="<?php echo e(route('admin.courses.index',['status'=>'published'])); ?>" class="<?php echo e(request('status')==='published' ? 'selected' : ''); ?>"><span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></span><div><strong><?php echo e($stats['published']); ?></strong><small>منشورة</small></div></a>
    <a href="<?php echo e(route('admin.courses.index',['status'=>'draft'])); ?>" class="<?php echo e(request('status')==='draft' ? 'selected' : ''); ?>"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6"/></svg></span><div><strong><?php echo e($stats['drafts']); ?></strong><small>مسودات</small></div></a>
    <a href="<?php echo e(route('admin.courses.index',['status'=>'archived'])); ?>" class="<?php echo e(request('status')==='archived' ? 'selected' : ''); ?>"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 3h18v4H3zM9 11h6"/></svg></span><div><strong><?php echo e($stats['archived']); ?></strong><small>مؤرشفة</small></div></a>
</section>

<?php ($advancedFilterCount = collect(['scope','year','grade','semester','category'])->filter(fn ($key) => filled(request($key)))->count()); ?>
<form class="card course-catalog-filter course-catalog-filter-refined" method="get" role="search">
    <input type="hidden" name="view" value="<?php echo e($display); ?>">
    <label class="search-field course-filter-search"><span>البحث</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="<?php echo e($search); ?>" placeholder="اسم الدورة أو المعرّف..."></label>
    <label class="course-filter-field"><span>الحالة</span><select name="status"><option value="">كل الحالات</option><option value="published" <?php if(request('status')==='published'): echo 'selected'; endif; ?>>منشورة</option><option value="draft" <?php if(request('status')==='draft'): echo 'selected'; endif; ?>>مسودة</option><option value="archived" <?php if(request('status')==='archived'): echo 'selected'; endif; ?>>مؤرشفة</option></select></label>
    <label class="course-filter-field"><span>الترتيب</span><select name="sort"><option value="latest" <?php if(request('sort','latest')==='latest'): echo 'selected'; endif; ?>>الأحدث</option><option value="title" <?php if(request('sort')==='title'): echo 'selected'; endif; ?>>اسم الدورة</option><option value="content" <?php if(request('sort')==='content'): echo 'selected'; endif; ?>>الأكثر محتوى</option><option value="status" <?php if(request('sort')==='status'): echo 'selected'; endif; ?>>الحالة</option><option value="enrollments" <?php if(request('sort')==='enrollments'): echo 'selected'; endif; ?>>الأكثر تسجيلاً</option></select></label>
    <details class="course-advanced-filters"><summary><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>فلاتر إضافية</span><?php if($advancedFilterCount): ?><b><?php echo e($advancedFilterCount); ?></b><?php endif; ?></summary><div>
        <label><span>نوع الدورة</span><select name="scope"><option value="">كل الأنواع</option><option value="academic" <?php if(request('scope')==='academic'): echo 'selected'; endif; ?>>أكاديمية</option><option value="general" <?php if(request('scope')==='general'): echo 'selected'; endif; ?>>عامة</option></select></label>
        <label><span>العام الدراسي</span><select name="year"><option value="">كل الأعوام</option><?php $__currentLoopData = $academicYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($year->id); ?>" <?php if((int) request('year') === $year->id): echo 'selected'; endif; ?>><?php echo e($year->name_ar); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label><span>الصف</span><select name="grade"><option value="">كل الصفوف</option><?php $__currentLoopData = ['grade-1'=>'الأول الثانوي','grade-2'=>'الثاني الثانوي','grade-3'=>'الثالث الثانوي']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(request('grade')===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label><span>الفصل الدراسي</span><select name="semester"><option value="">كل الفصول</option><option value="semester-1" <?php if(request('semester')==='semester-1'): echo 'selected'; endif; ?>>الترم الأول</option><option value="semester-2" <?php if(request('semester')==='semester-2'): echo 'selected'; endif; ?>>الترم الثاني</option></select></label>
        <label><span>التصنيف</span><select name="category"><option value="">كل التصنيفات</option><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category); ?>" <?php if(request('category')===$category): echo 'selected'; endif; ?>><?php echo e($category); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
    </div></details>
    <div class="course-filter-actions"><button class="button button-primary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg> تطبيق الفلاتر</button><?php if(request()->hasAny(['search','status','scope','year','grade','semester','category','sort'])): ?><a class="button button-quiet" href="<?php echo e(route('admin.courses.index',['view'=>$display])); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg> مسح</a><?php endif; ?></div>
</form>

<div class="course-results-head"><div><strong><?php echo e($courses->total()); ?> دورة</strong><span>اختر دورة للانتقال إلى وحداتها ثم دروسها ومحتواها.</span></div><div class="listing-head-tools"><span>صفحة <?php echo e($courses->currentPage()); ?> من <?php echo e($courses->lastPage()); ?></span><?php if (isset($component)) { $__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldfac0f66a4a3feab92a6f2adfd03a99d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin.view-switcher','data' => ['display' => $display]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin.view-switcher'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['display' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($display)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldfac0f66a4a3feab92a6f2adfd03a99d)): ?>
<?php $attributes = $__attributesOriginaldfac0f66a4a3feab92a6f2adfd03a99d; ?>
<?php unset($__attributesOriginaldfac0f66a4a3feab92a6f2adfd03a99d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d)): ?>
<?php $component = $__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d; ?>
<?php unset($__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d); ?>
<?php endif; ?></div></div>

<?php if($display === 'table'): ?>
<?php echo $__env->make('admin.courses.table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
<section class="course-admin-grid" aria-label="قائمة الدورات">
    <?php $__empty_1 = true; $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php ($status = $course->archived_at ? 'archived' : ($course->is_published ? 'published' : 'draft')); ?>
    <article class="card course-admin-card course-status-<?php echo e($status); ?>">
        <header class="course-admin-cover">
            <div class="course-cover-code" aria-hidden="true">&lt;/&gt;</div>
            <div class="course-cover-top"><span><?php echo e($course->category); ?></span><span class="badge <?php echo e($status); ?>"><?php echo e($status === 'archived' ? 'مؤرشفة' : ($status === 'published' ? 'منشورة' : 'مسودة')); ?></span></div>
            <div class="course-cover-title"><small class="latin"><?php echo e($course->id); ?></small><strong><?php echo e($course->title); ?></strong><span lang="en" dir="ltr"><?php echo e($course->title_en); ?></span></div>
        </header>
        <div class="course-admin-body">
            <div class="course-admin-meta"><span><?php echo e($course->isGeneral() ? 'دورة عامة' : 'دورة أكاديمية'); ?></span><?php if($course->isGeneral()): ?><span>كل الطلاب</span><?php else: ?><span><?php echo e(['grade-1'=>'أولى ثانوي','grade-2'=>'تانية ثانوي','grade-3'=>'تالتة ثانوي'][$course->grade] ?? $course->grade); ?></span><span><?php echo e($course->semester === 'semester-1' ? 'الترم الأول' : 'الترم الثاني'); ?></span><?php endif; ?><span><?php echo e($course->level); ?></span></div>
            <div class="course-admin-numbers"><div><strong><?php echo e($course->units_count); ?></strong><small>وحدة</small></div><div><strong><?php echo e($course->lessons_count); ?></strong><small>درس</small></div><div><strong><?php echo e($course->resources_count); ?></strong><small>مورد</small></div><div><strong><?php echo e($course->content_trainings_count); ?></strong><small>تدريب</small></div></div>
            <div class="course-readiness-inline">
                <div><span>جاهزية المحتوى</span><strong><?php echo e($course->list_readiness_score); ?>%</strong></div>
                <div class="progress-track" role="progressbar" aria-label="جاهزية <?php echo e($course->title); ?>" aria-valuenow="<?php echo e($course->list_readiness_score); ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo e($course->list_readiness_score); ?>%"></span></div>
                <small><?php echo e($course->list_readiness_score === 100 ? 'المتطلبات الأساسية مكتملة' : 'راجع الفحص قبل النشر'); ?></small>
            </div>
        </div>
        <footer class="course-admin-footer">
            <div class="course-main-actions"><a class="button button-primary" href="<?php echo e(route('admin.learning-paths.courses.units',$course)); ?>">إدارة الوحدات</a><a class="button button-secondary" href="<?php echo e(route('admin.courses.edit',$course)); ?>">تحرير</a><a class="icon-action" href="<?php echo e(route('admin.courses.preview',$course)); ?>" aria-label="معاينة <?php echo e($course->title); ?>" title="معاينة">◉</a></div>
            <details class="course-more"><summary aria-label="المزيد من إجراءات <?php echo e($course->title); ?>">•••</summary><div>
                <form method="post" action="<?php echo e(route('admin.courses.state',[$course,$course->archived_at?'restore':($course->is_published?'unpublish':'publish')])); ?>"><?php echo csrf_field(); ?><button><?php echo e($course->archived_at?'استعادة الدورة':($course->is_published?'إلغاء النشر':'نشر الدورة')); ?></button></form>
                <?php if (! ($course->archived_at)): ?><form method="post" action="<?php echo e(route('admin.courses.state',[$course,'archive'])); ?>"><?php echo csrf_field(); ?><button class="danger-text">أرشفة الدورة</button></form><?php endif; ?>
            </div></details>
        </footer>
    </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="card empty-state course-catalog-empty"><strong>لا توجد دورات مطابقة</strong><span>غيّر الفلاتر أو أنشئ دورة تعليمية جديدة.</span><a class="button button-primary" href="<?php echo e(route('admin.courses.create')); ?>">إنشاء دورة</a></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<div class="pagination"><?php echo e($courses->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/courses/index.blade.php ENDPATH**/ ?>