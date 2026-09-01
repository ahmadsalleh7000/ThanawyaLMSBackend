<?php $__env->startSection('title', 'رسائل التواصل'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $statusLabels = ['new' => 'جديدة', 'in_progress' => 'قيد المتابعة', 'resolved' => 'مكتملة'];
?>
<header class="page-header community-page-hero">
    <div>
        <div class="eyebrow">صندوق دعم مركزي</div>
        <h1>رسائل التواصل</h1>
        <p>راجع استفسارات الزوار والطلاب، وحدد ما يحتاج متابعة.</p>
    </div>
</header>
<section class="community-stats" aria-label="ملخص الرسائل">
    <?php $__currentLoopData = ['new' => ['جديدة', 'تحتاج مراجعة'], 'in_progress' => ['قيد المتابعة', 'فتحها الفريق'], 'resolved' => ['مكتملة', 'تم التعامل معها']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $copy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('admin.contact-messages.index', ['status' => $status])); ?>">
            <span class="community-stat-icon"><?php echo e($status === 'new' ? '!' : ($status === 'resolved' ? '✓' : '◷')); ?></span>
            <div><strong><?php echo e($counts[$status]); ?></strong><small><?php echo e($copy[0]); ?></small></div>
            <em><?php echo e($copy[1]); ?></em>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</section>
<section class="card stack">
    <form class="filter-bar" method="get">
        <label>بحث<input name="q" value="<?php echo e(request('q')); ?>" placeholder="الاسم أو البريد أو رقم المتابعة"></label>
        <label>الحالة
            <select name="status">
                <option value="">كل الحالات</option>
                <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if(request('status') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </label>
        <button class="button button-secondary">تطبيق</button>
        <a class="button button-quiet" href="<?php echo e(route('admin.contact-messages.index')); ?>">مسح</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>المرسل</th><th>الموضوع</th><th>الحالة</th><th>المعالجة</th><th>التاريخ</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><strong><?php echo e($message->name); ?></strong><small class="latin"><?php echo e($message->email); ?></small><small class="latin"><?php echo e($message->public_reference); ?></small></td>
                        <td><?php echo e($message->subject); ?></td>
                        <td><span class="badge <?php echo e($message->status === 'resolved' ? 'published' : ($message->status === 'new' ? 'inactive' : 'draft')); ?>"><?php echo e($statusLabels[$message->status]); ?></span></td>
                        <td>
                            <?php if($message->replied_at): ?>
                                <strong>تم الرد</strong><small><?php echo e($message->replied_at->diffForHumans()); ?></small>
                            <?php elseif($message->handler): ?>
                                <strong><?php echo e($message->handler->name); ?></strong><small>قيد المتابعة</small>
                            <?php else: ?>
                                <span class="badge inactive">لم تُسند</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($message->created_at->format('Y/m/d H:i')); ?></td>
                        <td><a class="button button-quiet" href="<?php echo e(route('admin.contact-messages.show', $message)); ?>">فتح</a></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6"><div class="empty-state">لا توجد رسائل مطابقة.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination"><?php echo e($messages->links()); ?></div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/contact-messages/index.blade.php ENDPATH**/ ?>