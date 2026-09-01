<div class="card table-wrap content-list-table-wrap">
    <table class="content-list-table instructors-list-table">
        <thead><tr><th>المدرس</th><th>الحالة</th><th>اكتمال الملف</th><th>الدورات</th><th>الطلاب</th><th>التقييم</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $instructors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $instructor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($enrollments=$instructor->courses->sum('enrollments_count')); ?>
            <?php ($rating=$instructor->courses->whereNotNull('published_rating')->avg('published_rating') ?? 0); ?>
            <tr class="<?php echo e($instructor->is_active ? '' : 'muted-row'); ?>">
                <td><div class="table-primary-cell"><span class="table-record-icon instructor"><?php echo e(mb_substr($instructor->name,0,1)); ?></span><div><strong><?php echo e($instructor->name); ?></strong><small lang="en" dir="ltr"><?php echo e($instructor->name_en); ?></small><em class="latin"><?php echo e($instructor->email ?: '—'); ?></em></div></div></td>
                <td><span class="badge <?php echo e($instructor->is_active ? 'active' : 'inactive'); ?>"><?php echo e($instructor->is_active ? 'نشط' : 'غير نشط'); ?></span></td>
                <td><div class="table-progress"><div><strong><?php echo e($instructor->profile_completion); ?>%</strong></div><div class="progress-track"><span style="width:<?php echo e($instructor->profile_completion); ?>%"></span></div></div></td>
                <td><strong><?php echo e($instructor->courses_count); ?></strong><small><?php echo e($instructor->courses->first()?->title ?: 'دون دورة'); ?></small></td>
                <td><strong><?php echo e(number_format($enrollments)); ?></strong><small>مسجلون</small></td>
                <td><strong><?php echo e($rating ? number_format($rating,1) : '—'); ?></strong><small>من 5</small></td>
                <td><div class="table-row-actions"><details class="table-action-menu"><summary class="icon-action" aria-label="تحرير <?php echo e($instructor->name); ?>" title="تحرير">✎</summary><div class="table-action-panel"><form class="form-grid instructor-edit" method="post" action="<?php echo e(route('admin.instructors.update',$instructor)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('put'); ?> <?php echo $__env->make('admin.instructors.fields',['item'=>$instructor], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="button button-primary full">حفظ التعديلات</button></form></div></details><form method="post" action="<?php echo e(route('admin.instructors.toggle',$instructor)); ?>"><?php echo csrf_field(); ?><button class="icon-action" aria-label="<?php echo e($instructor->is_active?'تعطيل':'تفعيل'); ?> <?php echo e($instructor->name); ?>"><?php echo e($instructor->is_active ? '×' : '✓'); ?></button></form></div></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7"><div class="empty-state"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف مدرساً.</span></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/instructors/table.blade.php ENDPATH**/ ?>