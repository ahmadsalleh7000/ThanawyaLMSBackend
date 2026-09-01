<?php $__env->startSection('title','تحرير الوحدة'); ?>
<?php $__env->startSection('content'); ?>
<nav class="management-breadcrumb" aria-label="مسار التنقل"><a href="<?php echo e(route('admin.learning-paths.index')); ?>">المسارات التعليمية</a><span>←</span><a href="<?php echo e(route('admin.learning-paths.courses.show',$course)); ?>"><?php echo e($course->title); ?></a><span>←</span><a href="<?php echo e(route('admin.learning-paths.courses.units',$course)); ?>">الوحدات</a><span>←</span><strong>تحرير <?php echo e($unit->title_ar); ?></strong></nav>
<header class="page-header management-page-header"><div><div class="eyebrow">بيانات الوحدة</div><h1>تحرير الوحدة</h1><p>حدّث الاسم والمعرّف دون تغيير ترتيب الدروس أو محتواها.</p></div><span class="badge <?php echo e($unit->status==='published'?'published':'draft'); ?>"><?php echo e($unit->status==='published'?'منشورة':'مسودة'); ?></span></header>
<form class="card form-grid unit-edit-form" method="post" action="<?php echo e(route('admin.learning-paths.units.update',[$course,$unit])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
    <label>العنوان العربي<input name="title_ar" value="<?php echo e(old('title_ar',$unit->title_ar)); ?>" maxlength="150" required></label>
    <label lang="en">English title<input name="title_en" dir="ltr" value="<?php echo e(old('title_en',$unit->title_en)); ?>" maxlength="150" required></label>
    <label class="full">المعرّف التقني<input name="slug" dir="ltr" value="<?php echo e(old('slug',$unit->slug)); ?>" maxlength="80" pattern="[a-z0-9-]+" title="استخدم حروفاً إنجليزية صغيرة وأرقاماً وشرطات فقط" required><small>حروف إنجليزية صغيرة وأرقام وشرطات فقط.</small></label>
    <div class="form-actions full"><a class="button button-quiet" href="<?php echo e(route('admin.learning-paths.courses.units',$course)); ?>">إلغاء</a><button class="button button-primary">حفظ التعديلات</button></div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/learning-paths/unit-edit.blade.php ENDPATH**/ ?>