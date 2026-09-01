<div class="card table-wrap content-list-table-wrap">
    <table class="content-list-table questions-list-table">
        <thead><tr><th>السؤال</th><th>الموضوع</th><th>النوع</th><th>الصعوبة</th><th>الاستخدام</th><th>الحالة</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr class="<?php echo e($question->is_active ? '' : 'muted-row'); ?>">
                <td><div class="table-question"><strong><?php echo e($question->prompt_ar); ?></strong><small lang="en" dir="ltr"><?php echo e($question->prompt); ?></small></div></td>
                <td><strong><?php echo e($question->topic_ar); ?></strong><small lang="en" dir="ltr"><?php echo e($question->topic); ?></small></td>
                <td><?php echo e($question->type === 'true_false' ? 'صح أو خطأ' : 'اختيار واحد'); ?></td>
                <td><span class="table-difficulty difficulty-<?php echo e($question->difficulty); ?>"><?php echo e(['easy'=>'سهل','medium'=>'متوسط','hard'=>'صعب'][$question->difficulty]); ?></span></td>
                <td><strong><?php echo e($question->assessment_questions_count); ?></strong><small>تقييمات</small></td>
                <td><span class="badge <?php echo e($question->is_active ? 'active' : 'inactive'); ?>"><?php echo e($question->is_active ? 'فعال' : 'معطل'); ?></span></td>
                <td><div class="table-row-actions"><details class="table-action-menu"><summary class="icon-action" aria-label="تحرير السؤال" title="تحرير">✎</summary><div class="table-action-panel"><form class="form-grid bank-edit" method="post" action="<?php echo e(route('admin.question-bank.update',$question)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('put'); ?> <?php echo $__env->make('admin.question-bank.fields',['item'=>$question], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><button class="button button-primary full">حفظ التعديلات</button></form></div></details><form method="post" action="<?php echo e(route('admin.question-bank.toggle',$question)); ?>"><?php echo csrf_field(); ?><button class="icon-action" aria-label="<?php echo e($question->is_active ? 'تعطيل' : 'تفعيل'); ?> السؤال"><?php echo e($question->is_active ? '×' : '✓'); ?></button></form></div></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7"><div class="empty-state"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف سؤالاً جديداً.</span></div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/question-bank/table.blade.php ENDPATH**/ ?>