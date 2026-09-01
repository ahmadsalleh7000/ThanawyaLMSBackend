<?php $__env->startSection('title', 'مكتبة الوسائط'); ?>
<?php $__env->startSection('content'); ?>
<header class="page-header media-library-hero">
    <div><div class="eyebrow">إدارة الأصول التعليمية</div><h1>مكتبة الوسائط</h1><p>ارفع الملف مرة واحدة، تحقق من سلامته، وأعد استخدامه في أكثر من درس.</p></div>
    <a class="button button-primary" href="#upload-media"><span aria-hidden="true">↑</span> رفع ملف</a>
</header>

<section class="media-library-stats" aria-label="ملخص مكتبة الوسائط">
    <article><span>▣</span><div><strong><?php echo e(number_format($stats['files'])); ?></strong><small>ملفات فعالة</small></div><em><?php echo e($stats['videos']); ?> فيديو · <?php echo e($stats['resources']); ?> ملفات</em></article>
    <article><span>GB</span><div><strong><?php echo e($stats['bytes'] >= 1073741824 ? number_format($stats['bytes']/1073741824,2) : number_format($stats['bytes']/1048576,1)); ?></strong><small><?php echo e($stats['bytes'] >= 1073741824 ? 'جيجابايت' : 'ميجابايت'); ?> مستخدمة</small></div></article>
    <article><span>○</span><div><strong><?php echo e(number_format($stats['orphaned'])); ?></strong><small>غير مرتبطة بدروس</small></div><a href="<?php echo e(route('admin.media-library.index',['usage'=>'orphaned'])); ?>">مراجعة</a></article>
    <article class="<?php echo e($stats['failed'] ? 'has-warning' : ''); ?>"><span>!</span><div><strong><?php echo e(number_format($stats['failed'])); ?></strong><small>ملفات تحتاج تدخلاً</small></div><?php if($stats['failed']): ?><a href="<?php echo e(route('admin.media-library.index',['health'=>'failed'])); ?>">معالجة</a><?php endif; ?></article>
</section>

<form class="card media-library-filter" method="get" role="search">
    <input type="hidden" name="view" value="<?php echo e($display); ?>">
    <label class="search-field"><span class="sr-only">البحث في الملفات</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="<?php echo e(request('search')); ?>" placeholder="اسم الملف أو نوعه..."></label>
    <select name="kind" aria-label="نوع الملف"><option value="">كل الأنواع</option><option value="video" <?php if(request('kind')==='video'): echo 'selected'; endif; ?>>فيديو</option><option value="resource" <?php if(request('kind')==='resource'): echo 'selected'; endif; ?>>ملف تعليمي</option></select>
    <select name="health" aria-label="حالة المعالجة"><option value="">كل حالات المعالجة</option><option value="ready" <?php if(request('health')==='ready'): echo 'selected'; endif; ?>>جاهز</option><option value="processing" <?php if(request('health')==='processing'): echo 'selected'; endif; ?>>قيد المعالجة</option><option value="failed" <?php if(request('health')==='failed'): echo 'selected'; endif; ?>>فشل</option></select>
    <select name="usage" aria-label="حالة الاستخدام"><option value="">كل الاستخدامات</option><option value="attached" <?php if(request('usage')==='attached'): echo 'selected'; endif; ?>>مرتبطة بدروس</option><option value="orphaned" <?php if(request('usage')==='orphaned'): echo 'selected'; endif; ?>>غير مرتبطة</option></select>
    <select name="status" aria-label="حالة الأرشفة"><option value="active" <?php if(request('status','active')==='active'): echo 'selected'; endif; ?>>المكتبة النشطة</option><option value="archived" <?php if(request('status')==='archived'): echo 'selected'; endif; ?>>الأرشيف</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" <?php if(request('sort','latest')==='latest'): echo 'selected'; endif; ?>>الأحدث</option><option value="name" <?php if(request('sort')==='name'): echo 'selected'; endif; ?>>الاسم</option><option value="size" <?php if(request('sort')==='size'): echo 'selected'; endif; ?>>الحجم</option><option value="usage" <?php if(request('sort')==='usage'): echo 'selected'; endif; ?>>الأكثر استخداماً</option></select>
    <button class="button button-secondary">تطبيق</button>
    <?php if(request()->hasAny(['search','kind','health','usage','status','sort'])): ?><a class="button button-quiet" href="<?php echo e(route('admin.media-library.index',['view'=>$display])); ?>">مسح</a><?php endif; ?>
</form>

<div class="media-library-workspace">
    <main>
        <div class="media-results-head"><div><strong><?php echo e($items->total()); ?> ملفاً</strong><span><?php echo e(request('status') === 'archived' ? 'الأصول المؤرشفة محفوظة ولا يمكن ربطها' : 'أصول متاحة للإدارة وإعادة الاستخدام'); ?></span></div><div class="listing-head-tools"><span>صفحة <?php echo e($items->currentPage()); ?> من <?php echo e($items->lastPage()); ?></span><?php if (isset($component)) { $__componentOriginaldfac0f66a4a3feab92a6f2adfd03a99d = $component; } ?>
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
        <?php echo $__env->make('admin.media-library.table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php else: ?>
        <div class="media-library-grid">
            <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($extension = strtoupper(pathinfo($item->original_name, PATHINFO_EXTENSION) ?: ($item->kind === 'video' ? 'VIDEO' : 'FILE'))); ?>
            <article class="card library-item <?php echo e($item->archived_at ? 'library-item-archived' : ''); ?>">
                <header class="media-card-head">
                    <span class="media-kind <?php echo e($item->kind); ?>"><b><?php echo e($item->kind === 'video' ? '▶' : '▤'); ?></b><small><?php echo e($extension); ?></small></span>
                    <div class="media-file-title"><strong title="<?php echo e($item->original_name); ?>"><?php echo e($item->original_name); ?></strong><small><?php echo e($item->mime_type); ?></small></div>
                    <span class="media-health media-health-<?php echo e($item->processing_status); ?>"><i></i><?php echo e(['ready'=>'جاهز','processing'=>'قيد المعالجة','failed'=>'فشل'][$item->processing_status] ?? $item->processing_status); ?></span>
                </header>
                <div class="media-file-facts"><span><small>الحجم</small><strong><?php echo e($item->size_bytes >= 1048576 ? number_format($item->size_bytes/1048576,1).' MB' : number_format($item->size_bytes/1024).' KB'); ?></strong></span><span><small>الاستخدام</small><strong><?php echo e($item->attachments_count); ?> ارتباطات</strong></span><span><small>تاريخ الرفع</small><strong><?php echo e($item->created_at->format('Y/m/d')); ?></strong></span></div>
                <div class="media-uploader"><span class="account-avatar"><?php echo e(mb_substr($item->uploader?->name ?? 'ن',0,1)); ?></span><div><small>رفع بواسطة</small><strong><?php echo e($item->uploader?->name ?? 'بيانات سابقة'); ?></strong></div></div>
                <?php if($item->attachments_count): ?>
                <div class="media-attachments"><small>مستخدم في</small><?php $__currentLoopData = $item->attachments->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span><?php echo e($attachment->lesson?->course?->title); ?> · <?php echo e($attachment->lesson?->title); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if($item->attachments_count > 2): ?><em>+<?php echo e($item->attachments_count - 2); ?> استخدامات أخرى</em><?php endif; ?></div>
                <?php else: ?><div class="media-orphan-note"><span>○</span><div><strong>غير مرتبط بعد</strong><small>يمكن ربطه بدرس أو أرشفته إذا لم تعد تحتاجه.</small></div></div><?php endif; ?>
                <?php if($item->processing_error): ?><div class="media-processing-error"><strong>تعذر التحقق من الملف</strong><span><?php echo e($item->processing_error); ?></span></div><?php endif; ?>
                <?php if(!$item->archived_at && $item->processing_status === 'ready'): ?>
                <details class="attach-media"><summary><span>＋</span> ربط بدرس</summary><form class="stack attach-media-form" method="post" action="<?php echo e(route('admin.media-library.attach',$item)); ?>"><?php echo csrf_field(); ?><select name="lesson_id" required><option value="">اختر الدورة والدرس</option><?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><optgroup label="<?php echo e($course->title); ?>"><?php $__currentLoopData = $course->lessons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lesson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($lesson->id); ?>"><?php echo e($lesson->title); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></optgroup><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><button class="button button-primary">تأكيد الربط</button></form></details>
                <?php endif; ?>
                <footer><form method="post" action="<?php echo e(route('admin.media-library.verify',$item)); ?>"><?php echo csrf_field(); ?><button class="button button-quiet">فحص السلامة</button></form><?php if($item->attachments_count===0 && !$item->archived_at): ?><form method="post" action="<?php echo e(route('admin.media-library.archive',$item)); ?>" data-confirm="سيُخفى الملف من المكتبة النشطة دون حذفه من التخزين. متابعة؟"><?php echo csrf_field(); ?><button class="button button-danger">أرشفة</button></form><?php endif; ?></footer>
            </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="card empty-state media-empty"><strong>لا توجد ملفات مطابقة</strong><span>عدّل الفلاتر أو ارفع أصلاً تعليمياً جديداً.</span><a class="button button-primary" href="#upload-media">رفع ملف</a></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="pagination"><?php echo e($items->links()); ?></div>
    </main>

    <aside id="upload-media">
        <section class="card media-upload">
            <div class="section-heading"><div><div class="eyebrow">أصل جديد</div><h2>رفع إلى المكتبة</h2></div></div>
            <form class="stack" method="post" enctype="multipart/form-data" action="<?php echo e(route('admin.media-library.store')); ?>"><?php echo csrf_field(); ?>
                <label>نوع الأصل<select name="kind"><option value="video">فيديو MP4 أو WebM</option><option value="resource">PDF، ZIP أو مستند</option></select></label>
                <label class="media-dropzone"><input type="file" name="file" required><span aria-hidden="true">↑</span><strong>اختر ملفاً من جهازك</strong><small>أو اسحبه إلى هذه المنطقة</small></label>
                <div class="upload-rules"><span>الفيديو حتى 512MB</span><span>الملفات حتى 25MB</span><span>يُحسب توقيع SHA-256</span></div>
                <button class="button button-primary">رفع وحفظ الأصل</button>
            </form>
        </section>
    </aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/media-library/index.blade.php ENDPATH**/ ?>