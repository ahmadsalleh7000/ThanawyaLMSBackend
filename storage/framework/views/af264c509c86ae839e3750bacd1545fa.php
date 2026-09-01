<?php $__env->startSection('title', 'الطلاب والأسر'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header users-page-hero community-page-hero">
    <div><div class="eyebrow">إدارة المجتمع التعليمي</div><h1>الطلاب وأولياء الأمور</h1><p>راجع الحسابات، تابع الوصول إلى التعلم، وأنشئ روابط أسرية واضحة.</p></div>
    <a class="button button-primary" href="#family-link"><span aria-hidden="true">＋</span> ربط أسرة</a>
</header>

<section class="users-page-stats" aria-label="ملخص الحسابات">
    <a href="<?php echo e(route('admin.users.index',['role'=>'student'])); ?>"><span>◉</span><div><strong><?php echo e($stats['students']); ?></strong><small>طلاب</small></div></a>
    <a href="<?php echo e(route('admin.users.index',['role'=>'parent'])); ?>"><span>◎</span><div><strong><?php echo e($stats['parents']); ?></strong><small>أولياء أمور</small></div></a>
    <a href="<?php echo e(route('admin.users.index',['status'=>'active'])); ?>"><span>✓</span><div><strong><?php echo e($stats['active']); ?></strong><small>حسابات نشطة</small></div></a>
    <a href="<?php echo e(route('admin.users.index',['linkage'=>'unlinked'])); ?>" class="<?php echo e($stats['unlinked'] ? 'attention' : ''); ?>"><span>○</span><div><strong><?php echo e($stats['unlinked']); ?></strong><small>دون رابط أسري</small></div></a>
</section>

<form class="card users-filter" method="get" role="search">
    <input type="hidden" name="view" value="<?php echo e($display); ?>">
    <label class="search-field"><span class="sr-only">بحث الحسابات</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="<?php echo e(request('search')); ?>" placeholder="الاسم أو البريد..."></label>
    <select name="role" aria-label="نوع الحساب"><option value="">كل الحسابات</option><option value="student" <?php if(request('role')==='student'): echo 'selected'; endif; ?>>الطلاب</option><option value="parent" <?php if(request('role')==='parent'): echo 'selected'; endif; ?>>أولياء الأمور</option></select>
    <select name="status" aria-label="حالة الحساب"><option value="">كل الحالات</option><option value="active" <?php if(request('status')==='active'): echo 'selected'; endif; ?>>نشط</option><option value="inactive" <?php if(request('status')==='inactive'): echo 'selected'; endif; ?>>معطل</option></select>
    <select name="linkage" aria-label="الرابط الأسري"><option value="">كل الروابط</option><option value="linked" <?php if(request('linkage')==='linked'): echo 'selected'; endif; ?>>مرتبط بأسرة</option><option value="unlinked" <?php if(request('linkage')==='unlinked'): echo 'selected'; endif; ?>>دون رابط</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" <?php if(request('sort','latest')==='latest'): echo 'selected'; endif; ?>>الأحدث</option><option value="name" <?php if(request('sort')==='name'): echo 'selected'; endif; ?>>الاسم</option><option value="activity" <?php if(request('sort')==='activity'): echo 'selected'; endif; ?>>الأكثر نشاطاً</option></select>
    <button class="button button-secondary">تطبيق</button>
    <?php if(request()->hasAny(['search','role','status','linkage','sort'])): ?><a class="button button-quiet" href="<?php echo e(route('admin.users.index',['view'=>$display])); ?>">مسح</a><?php endif; ?>
</form>

<div class="users-workspace">
    <main>
        <div class="users-results-head"><div><strong><?php echo e($users->total()); ?> حساباً</strong><span>حالة الوصول والروابط الأسرية</span></div><div class="users-results-tools"><span>صفحة <?php echo e($users->currentPage()); ?> من <?php echo e($users->lastPage()); ?></span><?php if (isset($component)) { $__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d = $component; } ?>
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
        <div class="card users-table-wrap table-wrap">
            <table class="users-admin-table">
                <thead><tr><th>الحساب</th><th>النوع</th><th>الحالة</th><th>النشاط</th><th>الرابط الأسري</th><th>تاريخ الانضمام</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php ($family = $user->role === 'student' ? $user->linkedParents : $user->linkedStudents); ?>
                    <tr class="<?php echo e($user->is_active ? '' : 'user-table-inactive'); ?>">
                        <td><div class="user-table-identity"><span class="user-admin-avatar role-<?php echo e($user->role); ?>"><?php echo e(mb_substr($user->name,0,1)); ?></span><div><strong><?php echo e($user->name); ?></strong><small class="latin"><?php echo e($user->email); ?></small></div></div></td>
                        <td><span class="user-role-pill role-<?php echo e($user->role); ?>"><?php echo e($user->role === 'student' ? 'طالب' : 'ولي أمر'); ?></span></td>
                        <td><span class="badge <?php echo e($user->is_active ? 'active' : 'inactive'); ?>"><?php echo e($user->is_active ? 'نشط' : 'معطل'); ?></span></td>
                        <td><div class="user-table-activity"><strong><?php echo e($user->role === 'student' ? $user->enrollments_count : $user->linked_students_count); ?></strong><small><?php echo e($user->role === 'student' ? 'تسجيلات بالدورات' : 'طلاب مرتبطون'); ?></small></div></td>
                        <td><?php if($family->isNotEmpty()): ?><div class="user-table-family"><?php $__currentLoopData = $family->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($member->name); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if($family->count()>2): ?><em>+<?php echo e($family->count()-2); ?></em><?php endif; ?></div><?php else: ?><span class="unlinked-label">○ دون رابط</span><?php endif; ?></td>
                        <td><time datetime="<?php echo e($user->created_at->toDateString()); ?>"><?php echo e($user->created_at->format('Y/m/d')); ?></time></td>
                        <td><div class="user-table-actions"><?php if($user->role === 'student'): ?><a class="icon-action" href="<?php echo e(route('admin.users.show',$user)); ?>" aria-label="عرض ملف <?php echo e($user->name); ?>" title="عرض ملف التعلم">◉</a><?php endif; ?><form method="post" action="<?php echo e(route('admin.users.toggle',$user)); ?>"><?php echo csrf_field(); ?><button class="icon-action <?php echo e($user->is_active ? 'deactivate' : 'activate'); ?>" aria-label="<?php echo e($user->is_active ? 'تعطيل' : 'تفعيل'); ?> حساب <?php echo e($user->name); ?>" title="<?php echo e($user->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب'); ?>"><?php echo e($user->is_active ? '×' : '✓'); ?></button></form></div></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7"><div class="empty-state"><strong>لا توجد حسابات مطابقة</strong><span>غيّر الفلاتر أو ابحث ببيانات أخرى.</span></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="users-admin-grid">
        <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($family = $user->role === 'student' ? $user->linkedParents : $user->linkedStudents); ?>
            <article class="card user-admin-card <?php echo e($user->is_active ? '' : 'user-admin-card-inactive'); ?>">
                <header><span class="user-admin-avatar role-<?php echo e($user->role); ?>"><?php echo e(mb_substr($user->name,0,1)); ?></span><div><strong><?php echo e($user->name); ?></strong><small class="latin"><?php echo e($user->email); ?></small><span><?php echo e($user->role === 'student' ? 'حساب طالب' : 'حساب ولي أمر'); ?></span></div><span class="badge <?php echo e($user->is_active ? 'active' : 'inactive'); ?>"><?php echo e($user->is_active ? 'نشط' : 'معطل'); ?></span></header>
                <div class="user-admin-facts">
                    <?php if($user->role === 'student'): ?><span><strong><?php echo e($user->enrollments_count); ?></strong><small>تسجيلات</small></span><span><strong><?php echo e($user->linked_parents_count); ?></strong><small>أولياء أمور</small></span><?php else: ?><span><strong><?php echo e($user->linked_students_count); ?></strong><small>طلاب مرتبطون</small></span><span><strong><?php echo e($user->created_at->diffForHumans(null,true)); ?></strong><small>عمر الحساب</small></span><?php endif; ?>
                </div>
                <?php if($family->isNotEmpty()): ?><div class="user-family"><small><?php echo e($user->role === 'student' ? 'أولياء الأمور' : 'الطلاب المرتبطون'); ?></small><?php $__currentLoopData = $family->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($member->name); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if($family->count()>3): ?><em>+<?php echo e($family->count()-3); ?></em><?php endif; ?></div><?php else: ?><div class="user-no-family"><span>○</span><div><strong>لا يوجد رابط أسري</strong><small>استخدم نموذج الربط لإتاحة متابعة الطالب.</small></div></div><?php endif; ?>
                <footer>
                    <?php if($user->role === 'student'): ?><a class="button button-secondary" href="<?php echo e(route('admin.users.show',$user)); ?>">عرض ملف التعلم</a><?php else: ?><span class="parent-account-note">تظهر بيانات الطلاب بعد إنشاء الرابط</span><?php endif; ?>
                    <form method="post" action="<?php echo e(route('admin.users.toggle',$user)); ?>"><?php echo csrf_field(); ?><button class="button <?php echo e($user->is_active ? 'button-danger' : 'button-secondary'); ?>"><?php echo e($user->is_active ? 'تعطيل' : 'تفعيل'); ?></button></form>
                </footer>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="card empty-state users-empty"><strong>لا توجد حسابات مطابقة</strong><span>غيّر الفلاتر أو ابحث ببيانات أخرى.</span></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="pagination"><?php echo e($users->links()); ?></div>
    </main>

    <aside id="family-link"><details class="card family-link-card" <?php if($errors->any()): ?> open <?php endif; ?>><summary><span><small>متابعة أسرية</small><strong>ربط ولي أمر بطالب</strong></span><b aria-hidden="true">＋</b></summary><p>يمنح الرابط ولي الأمر حق متابعة الدورات والتقدم فقط.</p><form class="stack" method="post" action="<?php echo e(route('admin.users.link')); ?>"><?php echo csrf_field(); ?><label>ولي الأمر<select name="parent_id" required><option value="">اختر ولي الأمر</option><?php $__currentLoopData = $parents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($parent->id); ?>" <?php if(old('parent_id')==$parent->id): echo 'selected'; endif; ?>><?php echo e($parent->name); ?> · <?php echo e($parent->email); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label>الطالب<select name="student_id" required><option value="">اختر الطالب</option><?php $__currentLoopData = $students; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $student): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($student->id); ?>" <?php if(old('student_id')==$student->id): echo 'selected'; endif; ?>><?php echo e($student->name); ?> · <?php echo e($student->email); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label>الصف الدراسي<select name="grade"><option value="grade-1">الأول الثانوي</option><option value="grade-2">الثاني الثانوي</option><option value="grade-3">الثالث الثانوي</option></select></label><div class="family-link-note"><span aria-hidden="true">✓</span><small>يمكن تحديث الصف بإعادة حفظ الرابط نفسه.</small></div><button class="button button-primary">حفظ الرابط الأسري</button></form></details></aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/users/index.blade.php ENDPATH**/ ?>