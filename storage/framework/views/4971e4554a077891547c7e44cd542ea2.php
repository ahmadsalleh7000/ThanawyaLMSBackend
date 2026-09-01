<?php $__env->startSection('title', 'الثقة والشهادات'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header quality-page-hero trust-page-hero">
    <div><div class="eyebrow">ضمان الجودة والمصداقية</div><h1>الثقة والشهادات</h1><p>راجع آراء الطلاب واحمِ مصداقية الشهادات الصادرة من المنصة.</p></div>
</header>

<section class="grid stats-grid trust-stats quality-kpis" aria-label="ملخص الثقة والجودة">
    <article class="card compact-stat"><span class="stat-icon">◷</span><div><strong><?php echo e(number_format($counts['pending'])); ?></strong><span>تقييمات بانتظار المراجعة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">★</span><div><strong><?php echo e(number_format($counts['published'])); ?></strong><span>تقييمات منشورة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">✓</span><div><strong><?php echo e(number_format($counts['activeCertificates'])); ?></strong><span>شهادات فعّالة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">×</span><div><strong><?php echo e(number_format($counts['revokedCertificates'])); ?></strong><span>شهادات ملغاة</span></div></article>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-heading"><div><div class="eyebrow">صوت المتعلم</div><h2>مراجعة تقييمات الدورات</h2></div></div>
    <nav class="filter-pills quality-filter-pills" aria-label="حالة التقييمات">
        <?php $__currentLoopData = [''=>'الكل','pending'=>'قيد المراجعة','published'=>'منشور','rejected'=>'مرفوض']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a class="<?php echo e(request('status','')===$value?'active':''); ?>" href="<?php echo e(route('admin.trust.index',array_filter(['status'=>$value]))); ?>"><?php echo e($label); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
    <div class="review-grid">
        <?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <article class="review-card review-status-<?php echo e($review->status); ?>">
                <div class="review-top"><div><strong><?php echo e($review->user->name); ?></strong><small><?php echo e($review->course->title); ?></small></div><span class="badge <?php echo e($review->status==='published'?'published':($review->status==='rejected'?'inactive':'draft')); ?>"><?php echo e(['pending'=>'قيد المراجعة','published'=>'منشور','rejected'=>'مرفوض'][$review->status]); ?></span></div>
                <div class="stars" aria-label="<?php echo e($review->rating); ?> من 5"><?php echo e(str_repeat('★',$review->rating)); ?><span><?php echo e(str_repeat('☆',5-$review->rating)); ?></span></div>
                <p><?php echo e($review->comment); ?></p>
                <small><?php echo e($review->created_at->diffForHumans()); ?></small>
                <form class="moderation-form" method="post" action="<?php echo e(route('admin.trust.reviews.moderate',$review)); ?>"><?php echo csrf_field(); ?>
                    <input name="moderation_note" value="<?php echo e($review->moderation_note); ?>" placeholder="ملاحظة داخلية اختيارية" maxlength="1000">
                    <div class="actions"><button class="button button-primary" name="status" value="published">نشر</button><button class="button button-danger" name="status" value="rejected">رفض</button></div>
                </form>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا توجد تقييمات ضمن هذه الحالة.</div><?php endif; ?>
    </div>
    <div class="pagination"><?php echo e($reviews->links()); ?></div>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-heading"><div><div class="eyebrow">سجل الاعتماد</div><h2>الشهادات الصادرة</h2></div><a class="button button-quiet" href="<?php echo e(route('admin.trust.index',['revoked'=>request('revoked')?null:1])); ?>"><?php echo e(request('revoked')?'عرض الفعّالة':'عرض الملغاة'); ?></a></div>
    <div class="table-wrap"><table><thead><tr><th>الطالب</th><th>الدورة</th><th>المعرّف</th><th>تاريخ الإصدار</th><th>الحالة والإجراء</th></tr></thead><tbody>
        <?php $__empty_1 = true; $__currentLoopData = $certificates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $certificate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td class="cell-title"><strong><?php echo e($certificate->user->name); ?></strong><small><?php echo e($certificate->user->email); ?></small></td><td><?php echo e($certificate->course->title); ?></td><td class="latin"><?php echo e($certificate->public_id); ?></td><td><?php echo e($certificate->issued_at->format('Y/m/d')); ?></td><td><?php if($certificate->revoked_at): ?><span class="badge inactive">ملغاة</span><small class="block-note"><?php echo e($certificate->revocation_reason); ?></small><?php else: ?><form class="inline-revoke" method="post" action="<?php echo e(route('admin.trust.certificates.revoke',$certificate)); ?>" data-confirm="هل أنت متأكد من إلغاء هذه الشهادة؟"><?php echo csrf_field(); ?><input name="reason" required minlength="10" maxlength="2000" placeholder="سبب الإلغاء"><button class="button button-danger">إلغاء</button></form><?php endif; ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5"><div class="empty-state">لا توجد شهادات ضمن هذا العرض.</div></td></tr><?php endif; ?>
    </tbody></table></div><div class="pagination"><?php echo e($certificates->links()); ?></div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/trust/index.blade.php ENDPATH**/ ?>