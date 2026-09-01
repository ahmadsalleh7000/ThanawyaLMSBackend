<?php $__env->startSection('title', 'إدارة المدرسين'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header instructor-page-hero">
    <div><div class="eyebrow">فريق التعليم</div><h1>إدارة المدرسين</h1><p>تابع الملفات المهنية، عبء الدورات، ومؤشرات الوصول والتقييم.</p></div>
    <a class="button button-primary" href="#create-instructor"><span aria-hidden="true">＋</span> إضافة مدرس</a>
</header>

<section class="instructor-page-stats" aria-label="ملخص المدرسين">
    <a href="<?php echo e(route('admin.instructors.index')); ?>"><span>◎</span><div><strong><?php echo e($stats['total']); ?></strong><small>كل المدرسين</small></div></a>
    <a href="<?php echo e(route('admin.instructors.index',['status'=>'active'])); ?>"><span>✓</span><div><strong><?php echo e($stats['active']); ?></strong><small>ملفات نشطة</small></div></a>
    <a href="<?php echo e(route('admin.instructors.index',['workload'=>'assigned'])); ?>"><span>▤</span><div><strong><?php echo e($stats['assigned']); ?></strong><small>مرتبطون بدورات</small></div></a>
    <a href="<?php echo e(route('admin.instructors.index',['workload'=>'unassigned'])); ?>" class="<?php echo e($stats['unassigned'] ? 'attention' : ''); ?>"><span>○</span><div><strong><?php echo e($stats['unassigned']); ?></strong><small>دون دورات</small></div></a>
</section>

<form class="card instructor-filter" method="get" role="search">
    <input type="hidden" name="view" value="<?php echo e($display); ?>">
    <label class="search-field"><span class="sr-only">البحث في المدرسين</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="<?php echo e($search); ?>" placeholder="الاسم أو البريد..."></label>
    <select name="status" aria-label="حالة الملف"><option value="">كل الحالات</option><option value="active" <?php if(request('status')==='active'): echo 'selected'; endif; ?>>نشط</option><option value="inactive" <?php if(request('status')==='inactive'): echo 'selected'; endif; ?>>غير نشط</option></select>
    <select name="workload" aria-label="عبء الدورات"><option value="">كل أعباء العمل</option><option value="assigned" <?php if(request('workload')==='assigned'): echo 'selected'; endif; ?>>مرتبط بدورات</option><option value="unassigned" <?php if(request('workload')==='unassigned'): echo 'selected'; endif; ?>>دون دورات</option></select>
    <select name="profile" aria-label="اكتمال الملف"><option value="">كل الملفات</option><option value="complete" <?php if(request('profile')==='complete'): echo 'selected'; endif; ?>>ملف مكتمل</option><option value="incomplete" <?php if(request('profile')==='incomplete'): echo 'selected'; endif; ?>>يحتاج استكمال</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" <?php if(request('sort','latest')==='latest'): echo 'selected'; endif; ?>>الأحدث</option><option value="name" <?php if(request('sort')==='name'): echo 'selected'; endif; ?>>الاسم</option><option value="courses" <?php if(request('sort')==='courses'): echo 'selected'; endif; ?>>أكثر الدورات</option></select>
    <button class="button button-secondary">تطبيق</button>
    <?php if(request()->hasAny(['search','status','workload','profile','sort'])): ?><a class="button button-quiet" href="<?php echo e(route('admin.instructors.index',['view'=>$display])); ?>">مسح</a><?php endif; ?>
</form>

<div class="instructor-workspace">
    <main>
        <div class="instructor-results-head"><div><strong><?php echo e($instructors->total()); ?> مدرساً</strong><span>ملفات مهنية وأداء الدورات المرتبطة</span></div><div class="listing-head-tools"><span>صفحة <?php echo e($instructors->currentPage()); ?> من <?php echo e($instructors->lastPage()); ?></span><?php if (isset($component)) { $__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d = $component; } ?>
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
        <?php echo $__env->make('admin.instructors.table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php else: ?>
        <div class="instructor-grid">
        <?php $__empty_1 = true; $__currentLoopData = $instructors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $instructor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($enrollments=$instructor->courses->sum('enrollments_count')); ?>
            <?php ($rating=$instructor->courses->whereNotNull('published_rating')->avg('published_rating') ?? 0); ?>
            <article class="card instructor-card <?php echo e($instructor->is_active ? '' : 'instructor-card-inactive'); ?>">
                <header class="instructor-card-head">
                    <span class="instructor-avatar"><?php echo e(mb_substr($instructor->name,0,1)); ?></span>
                    <div><strong><?php echo e($instructor->name); ?></strong><small lang="en" dir="ltr"><?php echo e($instructor->name_en); ?></small><span><?php echo e($instructor->headline ?: 'المسمى المهني غير مكتمل'); ?></span></div>
                    <span class="badge <?php echo e($instructor->is_active?'active':'inactive'); ?>"><?php echo e($instructor->is_active?'نشط':'غير نشط'); ?></span>
                </header>
                <div class="instructor-completion"><div><span>اكتمال الملف</span><strong><?php echo e($instructor->profile_completion); ?>%</strong></div><div class="progress-track" role="progressbar" aria-label="اكتمال ملف <?php echo e($instructor->name); ?>" aria-valuenow="<?php echo e($instructor->profile_completion); ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo e($instructor->profile_completion); ?>%"></span></div></div>
                <div class="instructor-metrics"><span><strong><?php echo e($instructor->courses_count); ?></strong><small>دورات</small></span><span><strong><?php echo e(number_format($enrollments)); ?></strong><small>طلاب مسجلون</small></span><span><strong><?php echo e($rating ? number_format($rating,1) : '—'); ?></strong><small>متوسط التقييم</small></span></div>
                <div class="instructor-contact"><span aria-hidden="true">@</span><div><small>البريد المهني</small><strong class="latin"><?php echo e($instructor->email ?: 'غير مضاف'); ?></strong></div></div>
                <p><?php echo e($instructor->bio ?: 'أكمل النبذة العربية لعرض خبرة المدرس وتخصصه بوضوح.'); ?></p>
                <?php if($instructor->courses_count): ?><div class="instructor-courses"><small>الدورات المرتبطة</small><?php $__currentLoopData = $instructor->courses->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('admin.courses.edit',$course)); ?>"><?php echo e($course->title); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if($instructor->courses_count>3): ?><span>+<?php echo e($instructor->courses_count-3); ?></span><?php endif; ?></div><?php else: ?><div class="instructor-no-courses"><span>○</span> لم يُربط بدورة بعد</div><?php endif; ?>
                <footer>
                    <details class="instructor-editor"><summary>تحرير الملف</summary><form class="form-grid instructor-edit" method="post" action="<?php echo e(route('admin.instructors.update',$instructor)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('put'); ?> <?php echo $__env->make('admin.instructors.fields',['item'=>$instructor], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="button button-primary full">حفظ التعديلات</button></form></details>
                    <form method="post" action="<?php echo e(route('admin.instructors.toggle',$instructor)); ?>"><?php echo csrf_field(); ?><button class="button <?php echo e($instructor->is_active?'button-danger':'button-secondary'); ?>"><?php echo e($instructor->is_active?'تعطيل':'تفعيل'); ?></button></form>
                </footer>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="card empty-state instructor-empty"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف مدرساً جديداً.</span><a class="button button-primary" href="#create-instructor">إضافة مدرس</a></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="pagination"><?php echo e($instructors->links()); ?></div>
    </main>

    <aside id="create-instructor"><details class="card instructor-create" <?php if($errors->any()): ?> open <?php endif; ?>><summary><span><small>عضو جديد</small><strong>إنشاء ملف مدرس</strong></span><b aria-hidden="true">＋</b></summary><p>أكمل البيانات بالعربية والإنجليزية ليكون الملف جاهزاً للربط بالدورات.</p><form class="stack" method="post" action="<?php echo e(route('admin.instructors.store')); ?>"><?php echo csrf_field(); ?> <?php echo $__env->make('admin.instructors.fields',['item'=>null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="button button-primary">إنشاء الملف</button></form></details></aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/instructors/index.blade.php ENDPATH**/ ?>