<?php $__env->startSection('title','ملخص التجارة'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header finance-page-hero commerce-page-hero"><div><div class="eyebrow">الإدارة المالية</div><h1>ملخص التجارة</h1><p>نظرة سريعة على الإيرادات والعروض وروابط إدارة كل جزء بشكل مستقل.</p></div></header>
<section class="grid finance-stats finance-overview-stats">
<?php $__currentLoopData = [['إجمالي القيمة',$summary['gross']],['الخصومات',$summary['discounts']],['المحصل',$summary['collected']],['المسترد',$summary['refunded']]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><article class="card compact-stat"><span class="stat-icon">£</span><div><strong><?php echo e(number_format($value,2)); ?></strong><span><?php echo e($label); ?> · EGP</span></div></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</section>
<section class="grid">
<a class="card detail-item" href="<?php echo e(route('admin.commerce.subscription-plans.index')); ?>"><div><small>اشتراك الطلاب</small><strong>خطط الاشتراك</strong><span><?php echo e($subscriptionPlans->where('is_active', true)->count()); ?> نشطة من <?php echo e($subscriptionPlans->count()); ?> خطة</span></div><span class="badge published">إدارة</span></a>
<a class="card detail-item" href="<?php echo e(route('admin.commerce.coupons.index')); ?>"><div><small>العروض</small><strong>أكواد الخصم</strong><span><?php echo e($couponsCount); ?> كود مسجل</span></div><span class="badge published">إدارة</span></a>
<a class="card detail-item" href="<?php echo e(route('admin.commerce.payment-settings.index')); ?>"><div><small>التحويل اليدوي</small><strong>قنوات الدفع</strong><span>InstaPay والمحافظ ومهلة الطلب.</span></div><span class="badge published">إعدادات</span></a>
<a class="card detail-item" href="<?php echo e(route('admin.commerce.bundles.index')); ?>"><div><small>المسارات</small><strong>الباقات</strong><span><?php echo e($bundlesCount); ?> باقة تعليمية</span></div><span class="badge published">إدارة</span></a>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/commerce/index.blade.php ENDPATH**/ ?>