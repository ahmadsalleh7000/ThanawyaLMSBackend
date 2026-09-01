<?php $__env->startSection('title', 'رسالة تواصل'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $statusLabels = ['new' => 'جديدة', 'in_progress' => 'قيد المتابعة', 'resolved' => 'مكتملة'];
?>
<header class="page-header">
    <div>
        <div class="eyebrow latin"><?php echo e($message->public_reference); ?></div>
        <h1><?php echo e($message->subject); ?></h1>
        <p><?php echo e($message->name); ?> · <span class="latin"><?php echo e($message->email); ?></span></p>
    </div>
    <a class="button button-quiet" href="<?php echo e(route('admin.contact-messages.index')); ?>">العودة للصندوق</a>
</header>

<section class="grid content-columns">
    <main class="card stack">
        <div class="section-heading">
            <div>
                <div class="eyebrow">محتوى الرسالة</div>
                <h2><?php echo e($message->subject); ?></h2>
            </div>
            <span class="badge <?php echo e($message->status === 'resolved' ? 'published' : ($message->status === 'new' ? 'inactive' : 'draft')); ?>">
                <?php echo e($statusLabels[$message->status]); ?>

            </span>
        </div>
        <p style="white-space:pre-wrap;line-height:1.9"><?php echo e($message->message); ?></p>
        <div class="student-context">
            <span>اللغة: <?php echo e($message->locale === 'ar' ? 'العربية' : 'English'); ?></span>
            <span>أُرسلت <?php echo e($message->created_at->diffForHumans()); ?></span>
            <?php if($message->user?->role === 'student'): ?>
                <a href="<?php echo e(route('admin.users.show', $message->user)); ?>">حساب مسجل: <?php echo e($message->user->name); ?></a>
            <?php elseif($message->user): ?>
                <span>حساب مسجل: <?php echo e($message->user->name); ?></span>
            <?php endif; ?>
        </div>
        <?php if($message->admin_note || $message->last_reply_body): ?>
            <div class="detail-list">
                <?php if($message->admin_note): ?>
                    <article class="detail-item">
                        <div>
                            <small>ملاحظة داخلية</small>
                            <p style="white-space:pre-wrap"><?php echo e($message->admin_note); ?></p>
                        </div>
                    </article>
                <?php endif; ?>
                <?php if($message->last_reply_body): ?>
                    <article class="detail-item">
                        <div>
                            <small>آخر رد مرسل · <?php echo e($message->replied_at?->format('Y/m/d H:i')); ?> <?php if($message->replier): ?> · <?php echo e($message->replier->name); ?> <?php endif; ?></small>
                            <p style="white-space:pre-wrap"><?php echo e($message->last_reply_body); ?></p>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <aside class="stack">
        <section class="card">
            <div class="section-heading"><h2>إدارة الرسالة</h2></div>
            <form class="stack" method="post" action="<?php echo e(route('admin.contact-messages.update', $message)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('put'); ?>
                <label>الحالة
                    <select name="status">
                        <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if($message->status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>
                <label>ملاحظة داخلية
                    <textarea name="admin_note" maxlength="2000" placeholder="اكتب ما تم فحصه أو الخطوة التالية..."><?php echo e(old('admin_note', $message->admin_note)); ?></textarea>
                </label>
                <?php if($message->handler): ?>
                    <small>آخر معالجة بواسطة <?php echo e($message->handler->name); ?></small>
                <?php endif; ?>
                <button class="button button-primary">حفظ الحالة والملاحظة</button>
            </form>
        </section>

        <section class="card">
            <div class="section-heading"><h2>إرسال رد من الإدارة</h2></div>
            <form class="stack" method="post" action="<?php echo e(route('admin.contact-messages.reply', $message)); ?>">
                <?php echo csrf_field(); ?>
                <label>الرد
                    <textarea name="reply_body" required minlength="5" maxlength="5000" placeholder="اكتب الرد الذي سيصل إلى بريد المرسل..."><?php echo e(old('reply_body')); ?></textarea>
                </label>
                <label>الحالة بعد الرد
                    <select name="status">
                        <option value="in_progress">قيد المتابعة</option>
                        <option value="resolved" selected>مكتملة</option>
                    </select>
                </label>
                <button class="button button-primary">إرسال الرد</button>
                <a class="button button-secondary" dir="ltr" href="mailto:<?php echo e($message->email); ?>?subject=<?php echo e(rawurlencode('Re: '.$message->subject)); ?>">فتح البريد يدوياً</a>
            </form>
        </section>
    </aside>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/contact-messages/show.blade.php ENDPATH**/ ?>