<?php $__env->startSection('title', 'تحرير الدرس'); ?>
<?php $__env->startSection('content'); ?>
<nav class="academic-breadcrumb" aria-label="السياق الأكاديمي"><a href="<?php echo e(route('admin.academics.index')); ?>">الهيكل الأكاديمي</a><span>←</span><span><?php echo e($course->academicSubject?->term?->grade?->name_ar ?? $course->grade); ?></span><span>←</span><span><?php echo e($course->academicSubject?->term?->name_ar ?? $course->semester); ?></span><span>←</span><span><?php echo e($course->academicSubject?->subject?->name_ar ?? $course->category); ?></span><span>←</span><strong><?php echo e($course->title); ?></strong></nav>
<header class="page-header lesson-editor-hero"><div><div class="eyebrow"><?php echo e($course->title); ?></div><h1>تحرير الدرس</h1><p>إدارة محتوى الدرس، الوسائط، الكراسة، والتدريبات من مساحات واضحة ومنفصلة.</p></div><div class="actions-inline lesson-editor-actions"><a class="button button-secondary" href="<?php echo e(route('admin.lessons.workbook-preview',[$course,$lesson])); ?>" target="_blank" rel="noreferrer"><span aria-hidden="true">▤</span> معاينة كراسة A4</a><a class="button button-secondary" href="<?php echo e(route('admin.lessons.workbook-download',[$course,$lesson])); ?>"><span aria-hidden="true">↓</span> تنزيل PDF</a><a class="button button-secondary" href="<?php echo e(route('admin.lessons.teacher-edition-preview',[$course,$lesson])); ?>" target="_blank" rel="noreferrer"><span aria-hidden="true">◉</span> نسخة المعلم</a><a class="button button-quiet" href="<?php echo e(route('admin.courses.edit',$course)); ?>"><span aria-hidden="true">←</span> العودة للدورة</a></div></header>
<?php
    $activeLessonTab = in_array(request('tab', 'details'), ['details', 'pdf', 'workbook', 'learning', 'completion', 'media', 'questions', 'content'], true) ? request('tab', 'details') : 'details';
    $lessonTabs = [
        'details' => ['label' => 'بيانات الدرس', 'icon' => '✎'],
        'pdf' => ['label' => 'قارئ PDF', 'icon' => '▤'],
        'workbook' => ['label' => 'الكراسة', 'icon' => '□'],
        'learning' => ['label' => 'أهداف التعلم', 'icon' => '◎'],
        'completion' => ['label' => 'الإكمال والنصوص', 'icon' => '✓'],
        'media' => ['label' => 'الوسائط', 'icon' => '▶'],
        'questions' => ['label' => 'التدريب والأسئلة', 'icon' => '?'],
        'content' => ['label' => 'محتوى الدرس', 'icon' => '☰'],
    ];
    $uploadBytesFromIni = static function ($value): int {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    };
    $formatUploadBytes = static function (int $bytes): string {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 1).'GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).'MB';
        }
        return round($bytes / 1024, 1).'KB';
    };
    $postMaxBytes = $uploadBytesFromIni(ini_get('post_max_size'));
    $uploadMaxBytes = $uploadBytesFromIni(ini_get('upload_max_filesize'));
    $activeUploadLimitBytes = min(array_filter([$postMaxBytes, $uploadMaxBytes])) ?: 0;
    $activeUploadLimitLabel = $activeUploadLimitBytes ? $formatUploadBytes($activeUploadLimitBytes) : 'غير معروف';
?>
<nav class="lesson-editor-tabs" aria-label="أقسام تحرير الدرس">
    <?php $__currentLoopData = $lessonTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a class="<?php echo e($activeLessonTab === $tab ? 'is-active' : ''); ?>" href="<?php echo e(route('admin.lessons.edit',[$course,$lesson])); ?>?tab=<?php echo e($tab); ?>"><span aria-hidden="true"><?php echo e($item['icon']); ?></span><?php echo e($item['label']); ?></a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</nav>
<?php if($activeLessonTab !== 'content'): ?>
<section class="lesson-editor-panel <?php echo e(in_array($activeLessonTab, ['details', 'media', 'questions'], true) ? 'stack' : 'grid content-columns'); ?>">
    <div class="stack">
        <?php if($activeLessonTab === 'details'): ?>
        <form class="card lesson-details-form" method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
            <div class="full section-heading"><div><div class="eyebrow">بيانات الدرس</div><h2>العنوان والنشر والإتاحة</h2><p>هذه هي الحقول الأساسية التي تحدد مكان الدرس وظهوره داخل الدورة.</p></div></div>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">1</span><div><strong>هوية الدرس</strong><small>العنوان والمعرّف المستخدمان في الإدارة ومشغل الطالب.</small></div></div>
                <div class="lesson-form-grid">
                    <label>معرّف الدرس<input dir="ltr" lang="en" name="slug" value="<?php echo e(old('slug',$lesson->slug)); ?>" required><small>يستخدم في روابط المشغل، مثل lesson-name.</small></label>
                    <label>الترتيب<input type="number" name="position" min="1" value="<?php echo e(old('position',$lesson->position)); ?>" required></label>
                    <label>العنوان العربي<input name="title" value="<?php echo e(old('title',$lesson->title)); ?>" required></label>
                    <label lang="en">English title<input dir="ltr" lang="en" name="title_en" value="<?php echo e(old('title_en',$lesson->title_en)); ?>" required></label>
                </div>
            </section>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">2</span><div><strong>المكان والنشر</strong><small>حدد الوحدة، الحالة، ونافذة إتاحة الدرس للطلاب.</small></div></div>
                <div class="lesson-form-grid">
                    <label>الوحدة<select name="unit_id"><option value="">بدون وحدة</option><?php $__currentLoopData = $course->academicSubject?->units ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($unit->id); ?>" <?php if((string)old('unit_id',$lesson->unit_id)===(string)$unit->id): echo 'selected'; endif; ?>><?php echo e($unit->position); ?>. <?php echo e($unit->title_ar); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label>حالة الدرس<select name="status"><option value="draft" <?php if(old('status',$lesson->status)==='draft'): echo 'selected'; endif; ?>>مسودة</option><option value="published" <?php if(old('status',$lesson->status)==='published'): echo 'selected'; endif; ?>>منشور</option><option value="archived" <?php if(old('status',$lesson->status)==='archived'): echo 'selected'; endif; ?>>مؤرشف</option></select></label>
                    <label>متاح من<input type="datetime-local" name="available_from" value="<?php echo e(old('available_from',$lesson->available_from?->format('Y-m-d\\TH:i'))); ?>"></label>
                    <label>متاح حتى<input type="datetime-local" name="available_until" value="<?php echo e(old('available_until',$lesson->available_until?->format('Y-m-d\\TH:i'))); ?>"></label>
                    <label>المدة بالدقائق<input type="number" name="duration_minutes" min="1" value="<?php echo e(old('duration_minutes',$lesson->duration_minutes)); ?>" required></label>
                </div>
            </section>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">3</span><div><strong>الفيديو والمعاينة</strong><small>استخدم رابطًا خارجيًا عند الحاجة، أو ارفع فيديو من تبويب الوسائط.</small></div></div>
                <div class="lesson-form-grid">
                    <label class="full">رابط فيديو خارجي اختياري<input dir="ltr" name="video_url" value="<?php echo e(old('video_url',$lesson->video_url)); ?>" placeholder="https://..."></label>
                    <input type="hidden" name="is_preview" value="0"><label class="full lesson-form-toggle"><span><input type="checkbox" name="is_preview" value="1" <?php if(old('is_preview',$lesson->is_preview)): echo 'checked'; endif; ?>><b>السماح بمعاينة هذا الدرس للعامة</b><small>فعّلها فقط للدروس التي يمكن للزائر مشاهدتها قبل الاشتراك.</small></span></label>
                </div>
            </section>
            <div class="full form-actions lesson-details-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ بيانات الدرس</button></div>
        </form>
        <?php endif; ?>
        <?php if($activeLessonTab === 'pdf'): ?>
        <form class="card form-grid" method="post" enctype="multipart/form-data" action="<?php echo e(route('admin.lesson-pdf.store',[$course,$lesson])); ?>" data-pdf-upload-form data-max-bytes="<?php echo e($activeUploadLimitBytes); ?>">
            <?php echo csrf_field(); ?>
            <div class="full section-heading"><div><div class="eyebrow">رفع ملف PDF</div><h2>إضافة ملف درس جديد</h2><p>ارفع ملف PDF من جهازك. بعد الرفع سيتم تفعيل القارئ وربط الملف بهذا الدرس تلقائيًا.</p></div></div>
            <label class="full">اختر ملف PDF<input type="file" name="file" accept="application/pdf,.pdf" required data-pdf-upload-input><small data-pdf-upload-status>حد الخادم الحالي للطلب: <?php echo e($activeUploadLimitLabel); ?>. في التطوير المحلي شغّل الخادم عبر composer run serve حتى يستخدم PHP حدود الرفع الصحيحة.</small><?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small role="alert"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
            <div class="full form-actions"><button class="button button-primary" data-pdf-upload-button><span aria-hidden="true">↑</span> رفع وربط PDF</button></div>
        </form>
        <section class="card stack">
            <div class="section-heading"><div><div class="eyebrow">ملفات PDF المرتبطة</div><h2>الملفات المتاحة لهذا الدرس</h2><p>بعد الرفع الناجح سيظهر الملف هنا، وسيتم اختياره تلقائيًا في إعدادات القارئ.</p></div></div>
            <div class="detail-list">
                <?php $__empty_1 = true; $__currentLoopData = $lesson->mediaAssets->filter(fn($asset) => $asset->mime_type === 'application/pdf'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="detail-item">
                        <span class="status-dot"></span>
                        <div style="flex:1"><strong><?php echo e($asset->original_name); ?></strong><small><?php echo e($asset->kind === 'lesson_pdf' ? 'PDF محتوى الدرس' : 'ملف تعليمي'); ?> · <?php echo e(number_format($asset->size_bytes/1024/1024,2)); ?> MB <?php if((int)$lesson->content_pdf_media_id === (int)$asset->id): ?> · مستخدم حالياً <?php endif; ?></small></div>
                        <?php if((int)$lesson->content_pdf_media_id === (int)$asset->id): ?>
                            <span class="badge published">محدد</span>
                        <?php else: ?>
                            <form method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
                                <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
                                <input type="hidden" name="content_pdf_enabled" value="1">
                                <input type="hidden" name="content_pdf_media_id" value="<?php echo e($asset->id); ?>">
                                <button class="button button-secondary"><span aria-hidden="true">✓</span> استخدام هذا الملف</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="empty-state">لا توجد ملفات PDF مرفوعة لهذا الدرس بعد.</div>
                <?php endif; ?>
            </div>
        </section>
        <form class="card form-grid" method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
            <div class="full section-heading"><div><div class="eyebrow">محتوى الدرس</div><h2>قارئ PDF للطلاب</h2><p>ارفع ملف PDF خاص بالدرس أو ضع رابط PDF خارجي، ثم فعّل ظهوره داخل مشغل الدورة.</p></div></div>
            <input type="hidden" name="content_pdf_enabled" value="0"><label class="full"><span><input type="checkbox" name="content_pdf_enabled" value="1" <?php if(old('content_pdf_enabled',$lesson->content_pdf_enabled)): echo 'checked'; endif; ?>> عرض محتوى الدرس كقارئ PDF احترافي</span></label>
            <label>ملف PDF مرفوع<select name="content_pdf_media_id"><option value="">بدون ملف مرفوع</option><?php $__currentLoopData = $lesson->mediaAssets->filter(fn($asset) => $asset->mime_type === 'application/pdf'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($asset->id); ?>" <?php if((string)old('content_pdf_media_id',$lesson->content_pdf_media_id)===(string)$asset->id): echo 'selected'; endif; ?>><?php echo e($asset->original_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>رابط PDF خارجي اختياري<input dir="ltr" name="content_pdf_url" value="<?php echo e(old('content_pdf_url',$lesson->content_pdf_url)); ?>" placeholder="https://example.com/lesson.pdf"></label>
            <label>عنوان ملف الدرس<input name="content_pdf_title" value="<?php echo e(old('content_pdf_title',$lesson->content_pdf_title)); ?>" placeholder="<?php echo e($lesson->title); ?>"></label>
            <label class="full">وصف قصير للملف<textarea name="content_pdf_description" rows="3"><?php echo e(old('content_pdf_description',$lesson->content_pdf_description)); ?></textarea></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ إعدادات PDF</button></div>
        </form>
        <?php endif; ?>
        <?php if($activeLessonTab === 'workbook'): ?>
        <form class="card form-grid" method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
            <div class="full section-heading"><div><div class="eyebrow">إعدادات الكراسة</div><h2>الطباعة والتنزيل</h2><p>تحكم في ظهور كراسة A4 للطلاب وما الذي تتضمنه النسخة المطبوعة.</p></div></div>
            <input type="hidden" name="workbook_enabled" value="0"><label><span><input type="checkbox" name="workbook_enabled" value="1" <?php if(old('workbook_enabled',$lesson->workbook_enabled ?? true)): echo 'checked'; endif; ?>> تفعيل كراسة الدرس</span></label>
            <input type="hidden" name="allow_print" value="0"><label><span><input type="checkbox" name="allow_print" value="1" <?php if(old('allow_print',$lesson->allow_print ?? true)): echo 'checked'; endif; ?>> السماح بالطباعة</span></label>
            <input type="hidden" name="allow_pdf_download" value="0"><label><span><input type="checkbox" name="allow_pdf_download" value="1" <?php if(old('allow_pdf_download',$lesson->allow_pdf_download ?? true)): echo 'checked'; endif; ?>> السماح بتنزيل PDF</span></label>
            <input type="hidden" name="include_workbook_cover" value="0"><label><span><input type="checkbox" name="include_workbook_cover" value="1" <?php if(old('include_workbook_cover',$lesson->include_workbook_cover ?? true)): echo 'checked'; endif; ?>> تضمين الغلاف</span></label>
            <input type="hidden" name="include_workbook_info" value="0"><label><span><input type="checkbox" name="include_workbook_info" value="1" <?php if(old('include_workbook_info',$lesson->include_workbook_info ?? true)): echo 'checked'; endif; ?>> تضمين فكرة الدرس والأهداف والنقاط</span></label>
            <input type="hidden" name="include_workbook_toc" value="0"><label><span><input type="checkbox" name="include_workbook_toc" value="1" <?php if(old('include_workbook_toc',$lesson->include_workbook_toc ?? true)): echo 'checked'; endif; ?>> تضمين خريطة الدرس</span></label>
            <input type="hidden" name="include_workbook_answer_key" value="0"><label><span><input type="checkbox" name="include_workbook_answer_key" value="1" <?php if(old('include_workbook_answer_key',$lesson->include_workbook_answer_key ?? false)): echo 'checked'; endif; ?>> تضمين الإجابات في كراسة الطالب</span></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ إعدادات الكراسة</button></div>
        </form>
        <?php endif; ?>
        <?php if($activeLessonTab === 'learning'): ?>
        <form class="card form-grid" method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
            <div class="full section-heading"><div><div class="eyebrow">أهداف التعلم</div><h2>ما الذي يجب أن يخرج به الطالب؟</h2><p>اكتب كل هدف أو نقطة في سطر مستقل ليظهر للطالب كتوجيه واضح داخل مشغل الدرس.</p></div></div>
            <label class="full">أهداف الدرس — سطر لكل هدف<textarea name="objectives_text" rows="4"><?php echo e(old('objectives_text',implode("\n",$lesson->objectives ?? []))); ?></textarea></label>
            <label class="full">النقاط الأساسية — سطر لكل نقطة<textarea name="key_points_text" rows="4"><?php echo e(old('key_points_text',implode("\n",$lesson->key_points ?? []))); ?></textarea></label>
            <label class="full">ملخص الدرس<textarea name="summary" rows="4"><?php echo e(old('summary',$lesson->summary)); ?></textarea></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ أهداف التعلم</button></div>
        </form>
        <?php endif; ?>
        <?php if($activeLessonTab === 'completion'): ?>
        <?php ($rules = old('completion_rules',$lesson->completion_rules ?? [])); ?>
        <form class="card form-grid" method="post" action="<?php echo e(route('admin.lessons.update',[$course,$lesson])); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
            <div class="full section-heading"><div><div class="eyebrow">شروط الإكمال</div><h2>الإكمال والنصوص التفريغية</h2><p>اتركها بدون تحديد للدروس البسيطة، أو فعّل الشروط المهمة للدروس الأساسية.</p></div></div>
            <input type="hidden" name="completion_rules[require_content_opened]" value="0"><label><span><input type="checkbox" name="completion_rules[require_content_opened]" value="1" <?php if((bool)($rules['require_content_opened'] ?? false)): echo 'checked'; endif; ?>> يجب فتح محتوى الدرس</span></label>
            <input type="hidden" name="completion_rules[require_practice_passed]" value="0"><label><span><input type="checkbox" name="completion_rules[require_practice_passed]" value="1" <?php if((bool)($rules['require_practice_passed'] ?? false)): echo 'checked'; endif; ?>> يجب اجتياز تدريب الدرس</span></label>
            <label>أقل مدة مشاهدة بالثواني<input type="number" min="0" max="86400" name="completion_rules[minimum_video_seconds]" value="<?php echo e($rules['minimum_video_seconds'] ?? 0); ?>"></label>
            <label class="full">النص العربي — سطر لكل مقطع<textarea name="transcript_text"><?php echo e(old('transcript_text',implode("\n",$lesson->transcript ?? []))); ?></textarea></label>
            <label class="full" lang="en">English transcript — one segment per line<textarea dir="ltr" lang="en" name="transcript_en_text"><?php echo e(old('transcript_en_text',implode("\n",$lesson->transcript_en ?? []))); ?></textarea></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ الإكمال والنصوص</button></div>
        </form>
        <?php endif; ?>
    </div>
    <?php if($activeLessonTab === 'media'): ?>
    <section class="card stack lesson-editor-card">
        <div class="section-heading"><div><div class="eyebrow">الوسائط الخاصة</div><h2>الفيديو والملفات</h2></div></div>
        <p class="lesson-editor-hint">هذه المساحة للملفات فقط: فيديو الدرس، PDF محتوى الدرس، أو ملفات تعليمية مرفقة. التدريب والأسئلة لها تبويب مستقل.</p>
        <div class="detail-list"><?php $__empty_1 = true; $__currentLoopData = $lesson->mediaAssets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div class="detail-item lesson-editor-row"><span class="status-dot"></span><div style="flex:1"><strong><?php echo e($asset->original_name); ?></strong><small><?php echo e($asset->kind === 'video' ? 'فيديو خاص' : ($asset->kind === 'lesson_pdf' ? 'PDF محتوى الدرس' : 'ملف تعليمي')); ?> · <?php echo e(number_format($asset->size_bytes/1024,1)); ?> KB</small></div><form method="post" action="<?php echo e(route('admin.media.destroy',[$course,$lesson,$asset])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('delete'); ?><button class="button button-danger"><span aria-hidden="true">×</span> حذف</button></form></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا توجد ملفات مرفوعة.</div><?php endif; ?></div>
        <hr class="divider">
        <form class="form-grid lesson-editor-form" method="post" enctype="multipart/form-data" action="<?php echo e(route('admin.media.store',[$course,$lesson])); ?>"><?php echo csrf_field(); ?><label>نوع الملف<select name="kind"><option value="video">فيديو الدرس</option><option value="lesson_pdf">PDF محتوى الدرس</option><option value="resource">ملف تعليمي</option></select></label><label>اختر الملف<input type="file" name="file" required><small>الحد التطبيقي 512MB. في التطوير المحلي شغّل الخادم عبر composer run serve حتى يستخدم PHP حدود الرفع الصحيحة.</small></label><div class="full form-actions"><button class="button button-secondary"><span aria-hidden="true">↑</span> رفع الملف</button></div></form>
    </section>
    <?php endif; ?>
    <?php if($activeLessonTab === 'questions'): ?>
    <section class="card stack lesson-editor-card">
        <div class="section-heading"><div><div class="eyebrow">تدريب الدرس</div><h2>اختبارات مرتبطة بهذا الدرس</h2><p>اربط الدرس باختبار محفوظ من بنك الأسئلة. الاختبار التدريبي للممارسة، والاختبار الإلزامي يجب على الطالب اجتيازه.</p></div></div>
        <div class="detail-list"><?php $__empty_1 = true; $__currentLoopData = $lesson->trainings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $training): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php ($trainingQuestionCount = $training->questionSet()->count()); ?><div class="detail-item lesson-editor-row"><span class="status-dot"></span><div style="flex:1"><strong><?php echo e($training->title_ar); ?></strong><small><?php echo e($training->quiz ? $training->quiz->name_ar.' · ' : ''); ?><?php echo e($trainingQuestionCount); ?> أسئلة · <?php echo e($training->is_required ? 'إلزامي' : 'تدريبي'); ?> · <?php echo e($training->status); ?></small></div><div class="lesson-training-actions"><?php if($trainingQuestionCount > 0): ?><form method="post" action="<?php echo e(route('admin.learning-paths.state',['training',$training->id])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="status" value="<?php echo e($training->status === 'published' ? 'draft' : 'published'); ?>"><button class="button button-secondary"><span aria-hidden="true"><?php echo e($training->status === 'published' ? '○' : '✓'); ?></span> <?php echo e($training->status === 'published' ? 'إلغاء النشر' : 'نشر'); ?></button></form><?php else: ?><span class="lesson-training-empty-action"><span aria-hidden="true">!</span> أضف أسئلة أولاً</span><?php endif; ?><form method="post" action="<?php echo e(route('admin.learning-paths.trainings.destroy',$training)); ?>" data-confirm="سيتم حذف ارتباط هذا الاختبار من الدرس فقط. سيبقى الاختبار محفوظاً في بنك الأسئلة."><?php echo csrf_field(); ?> <?php echo method_field('delete'); ?><button class="button button-danger"><span aria-hidden="true">×</span> حذف الارتباط</button></form></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">لا يوجد اختبار مرتبط بهذا الدرس بعد.</div><?php endif; ?></div>
        <hr class="divider">
        <form class="lesson-quiz-assign-form" method="post" action="<?php echo e(route('admin.learning-paths.trainings.store',['lesson',$lesson->id])); ?>"><?php echo csrf_field(); ?><div class="lesson-quiz-select-card"><span class="lesson-quiz-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11l2 2 4-5M5 4h14v16H5zM8 18h8"/></svg></span><label>اختيار من بنك الأسئلة<select name="question_bank_quiz_id" required><option value="">اختر اختبارًا محفوظًا</option><?php $__currentLoopData = $questionQuizzes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quiz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($quiz->id); ?>"><?php echo e($quiz->name_ar); ?> — <?php echo e($quiz->type === 'required' ? 'إلزامي' : 'تدريبي'); ?> · <?php echo e($quiz->questions_count); ?> أسئلة</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><small>اربط اختبارًا محفوظًا بالدرس. يمكنك إدارة محتوى الاختبار من بنك الأسئلة.</small></label></div><div class="lesson-quiz-options"><label>اسم مخصص داخل الدرس<input name="title_ar" placeholder="اتركه فارغًا لاستخدام اسم الاختبار"></label><label lang="en">English title<input dir="ltr" lang="en" name="title_en" placeholder="Optional lesson label"></label><label>درجة النجاح<input type="number" name="passing_score" min="1" max="100" placeholder="افتراضيًا من الاختبار"></label></div><div class="lesson-quiz-form-actions"><a class="button button-quiet" href="<?php echo e(route('admin.question-bank.index')); ?>#quiz-builder"><span aria-hidden="true">▦</span> إدارة الاختبارات</a><button class="button button-secondary"><span aria-hidden="true">＋</span> ربط الاختبار بالدرس</button></div></form>
    </section>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php ($sectionLabels = [
    'heading' => 'عنوان', 'paragraph' => 'فقرة', 'introduction' => 'مقدمة', 'objectives' => 'أهداف تعلم',
    'key_terms' => 'مصطلحات', 'definition' => 'تعريف', 'explanation' => 'شرح', 'important_note' => 'ملاحظة مهمة',
    'common_mistake' => 'خطأ شائع', 'example' => 'مثال', 'worked_example' => 'مثال محلول', 'comparison' => 'مقارنة',
    'table' => 'جدول', 'image' => 'صورة', 'code' => 'كود', 'activity' => 'نشاط', 'question' => 'سؤال',
    'question_group' => 'مجموعة أسئلة', 'summary' => 'ملخص', 'challenge' => 'تحدي', 'answer_key' => 'إجابات',
    'page_break' => 'فاصل صفحة',
]); ?>
<?php ($sectionGroups = [
    'المحتوى' => ['heading', 'paragraph', 'introduction', 'objectives', 'key_terms', 'definition', 'explanation', 'summary'],
    'التدريس' => ['important_note', 'common_mistake', 'example', 'worked_example', 'comparison', 'activity', 'challenge'],
    'التقييم' => ['question', 'question_group', 'answer_key'],
    'الوسائط والتنسيق' => ['table', 'image', 'code', 'page_break'],
]); ?>
<?php if($activeLessonTab === 'content'): ?>
<section class="card stack" style="margin-top:1rem">
    <div class="section-heading">
        <div>
            <div class="eyebrow">Lesson Content</div>
            <h2>محتوى الدرس المنظم</h2>
            <p>ابنِ الدرس ككتل تعليمية قابلة للعرض في صفحة الطالب والكراسة المطبوعة من نفس المصدر.</p>
        </div>
    </div>
    <form class="form-grid" method="post" action="<?php echo e(route('admin.lesson-sections.store',[$course,$lesson])); ?>">
        <?php echo csrf_field(); ?>
        <label>نوع الكتلة
            <select name="type" required>
                <?php $__currentLoopData = $sectionGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $types): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <optgroup label="<?php echo e($group); ?>">
                        <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type); ?>"><?php echo e($sectionLabels[$type]); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </optgroup>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </label>
        <label>عنوان اختياري<input name="title" placeholder="مثال: تحقق من فهمك"></label>
        <label>لغة الكود اختياري<input dir="ltr" lang="en" name="language" placeholder="php, js, python"></label>
        <label class="full">المحتوى الرئيسي<textarea name="content_text" rows="5" placeholder="اكتب الشرح، السؤال، المثال، أو الكود هنا."></textarea></label>
        <label class="full">عناصر متعددة — سطر لكل عنصر<textarea name="items_text" rows="4" placeholder="مناسب للأهداف، النقاط، المصطلحات، أو خطوات النشاط."></textarea></label>
        <div class="full section-heading"><div><div class="eyebrow">حقول متخصصة</div><h2>للأمثلة والأخطاء الشائعة والكود</h2><p>استخدمها عند اختيار النوع المناسب. ستظهر بشكل منظم للطالب وفي الكراسة.</p></div></div>
        <label>الخطأ الشائع<textarea name="mistake" rows="3" placeholder="مثال: الخلط بين المتغير والثابت"></textarea></label>
        <label>الفهم الصحيح<textarea name="correction" rows="3" placeholder="مثال: المتغير يمكن تغيير قيمته أثناء التنفيذ"></textarea></label>
        <label>مسألة المثال المحلول<textarea name="worked_problem" rows="3"></textarea></label>
        <label>طريقة التفكير<textarea name="worked_thinking" rows="3"></textarea></label>
        <label>الحل<textarea name="worked_solution" rows="3"></textarea></label>
        <label>شرح الحل<textarea name="worked_explanation" rows="3"></textarea></label>
        <label class="full">الخلاصة من المثال<textarea name="worked_key_lesson" rows="2"></textarea></label>
        <label>مشكلة الكود<textarea name="code_problem" rows="3"></textarea></label>
        <label>منطق الحل<textarea name="code_logic" rows="3"></textarea></label>
        <label class="full">الكود<textarea dir="ltr" lang="en" name="code_body" rows="6"></textarea></label>
        <label>شرح الكود<textarea name="code_explanation" rows="3"></textarea></label>
        <label>المخرجات المتوقعة<textarea dir="ltr" lang="en" name="code_expected_output" rows="3"></textarea></label>
        <label>جرّب بنفسك<textarea name="code_try_it" rows="3"></textarea></label>
        <div class="full section-heading"><div><div class="eyebrow">الأسئلة</div><h2>حقول التقييم المنظمة</h2><p>استخدمها مع كتلة سؤال أو مجموعة أسئلة. الخيارات تكتب سطرًا لكل اختيار.</p></div></div>
        <label>نوع السؤال<select name="question_type"><option value="short_answer">إجابة قصيرة</option><option value="multiple_choice">اختيار من متعدد</option><option value="true_false">صح أو خطأ</option><option value="complete">أكمل</option><option value="matching">توصيل</option><option value="essay">مقالي</option></select></label>
        <label>مساحة الإجابة<select name="question_answer_space"><option value="medium">متوسطة</option><option value="none">بدون مساحة</option><option value="small">صغيرة</option><option value="large">كبيرة</option></select></label>
        <label class="full">نص السؤال<textarea name="question_prompt" rows="3"></textarea></label>
        <label class="full">اختيارات السؤال — سطر لكل اختيار<textarea name="question_options_text" rows="4"></textarea></label>
        <label>الإجابة النموذجية<textarea name="question_answer" rows="3"></textarea></label>
        <label>شرح الإجابة<textarea name="question_explanation" rows="3"></textarea></label>
        <label class="full">مجموعة أسئلة — سؤال في كل سطر<textarea name="question_group_text" rows="5"></textarea></label>
        <label>مساحة إجابات المجموعة<select name="question_group_answer_space"><option value="medium">متوسطة</option><option value="none">بدون مساحة</option><option value="small">صغيرة</option><option value="large">كبيرة</option></select></label>
        <div class="full section-heading"><div><div class="eyebrow">الجداول والصور</div><h2>بيانات منظمة للطباعة والويب</h2></div></div>
        <label class="full">صفوف الجدول — استخدم | بين الخلايا<textarea name="table_text" rows="4" placeholder="المعيار | المفهوم أ | المفهوم ب&#10;الاستخدام | ... | ..."></textarea></label>
        <label>صورة من ملفات الدرس<select name="image_media_asset_id"><option value="">بدون صورة مرفوعة</option><?php $__currentLoopData = $lesson->mediaAssets->filter(fn($asset) => str_starts_with((string)$asset->mime_type, 'image/')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($asset->id); ?>"><?php echo e($asset->original_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
        <label>رابط صورة خارجي<input dir="ltr" name="image_url" placeholder="https://example.com/image.png"></label>
        <label>النص البديل للصورة<input name="image_alt" placeholder="وصف مختصر للصورة"></label>
        <label>تعليق الصورة<input name="image_caption"></label>
        <label>محاذاة الصورة<select name="image_alignment"><option value="center">وسط</option><option value="start">بداية</option><option value="end">نهاية</option></select></label>
        <label>عرض الصورة في الطباعة<select name="image_print_width"><option value="medium">متوسط</option><option value="small">صغير</option><option value="large">كبير</option><option value="full">كامل العرض</option></select></label>
        <input type="hidden" name="settings[show_on_web]" value="0"><label><span><input type="checkbox" name="settings[show_on_web]" value="1" checked> يظهر في صفحة الطالب</span></label>
        <input type="hidden" name="settings[show_in_workbook]" value="0"><label><span><input type="checkbox" name="settings[show_in_workbook]" value="1" checked> يظهر في الكراسة</span></label>
        <input type="hidden" name="settings[show_in_teacher_edition]" value="0"><label><span><input type="checkbox" name="settings[show_in_teacher_edition]" value="1"> يظهر في نسخة المعلم</span></label>
        <input type="hidden" name="settings[avoid_page_break_inside]" value="0"><label><span><input type="checkbox" name="settings[avoid_page_break_inside]" value="1" checked> لا تقطع داخل الكتلة عند الطباعة</span></label>
        <input type="hidden" name="settings[page_break_before]" value="0"><label><span><input type="checkbox" name="settings[page_break_before]" value="1"> فاصل صفحة قبلها</span></label>
        <input type="hidden" name="settings[page_break_after]" value="0"><label><span><input type="checkbox" name="settings[page_break_after]" value="1"> فاصل صفحة بعدها</span></label>
        <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">＋</span> إضافة كتلة محتوى</button></div>
    </form>
    <hr class="divider">
    <div class="detail-list">
        <?php $__empty_1 = true; $__currentLoopData = $lesson->sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($settings = $section->settingsWithDefaults()); ?>
            <details class="detail-item" open>
                <summary style="display:flex;gap:.75rem;align-items:center;cursor:pointer">
                    <span class="status-dot"></span>
                    <strong style="flex:1"><?php echo e($section->sort_order); ?>. <?php echo e($sectionLabels[$section->type] ?? $section->type); ?> <?php if($section->title): ?> — <?php echo e($section->title); ?> <?php endif; ?></strong>
                    <small><?php echo e($settings['show_on_web'] ? 'ويب' : 'مخفي عن الويب'); ?> · <?php echo e($settings['show_in_workbook'] ? 'كراسة' : 'خارج الكراسة'); ?></small>
                </summary>
                <form class="form-grid" method="post" action="<?php echo e(route('admin.lesson-sections.update',[$course,$lesson,$section])); ?>" style="margin-top:1rem">
                    <?php echo csrf_field(); ?> <?php echo method_field('put'); ?>
                    <label>نوع الكتلة<select name="type" required><?php $__currentLoopData = $sectionGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $types): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><optgroup label="<?php echo e($group); ?>"><?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type); ?>" <?php if($section->type===$type): echo 'selected'; endif; ?>><?php echo e($sectionLabels[$type]); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></optgroup><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label>العنوان<input name="title" value="<?php echo e($section->title); ?>"></label>
                    <label>لغة الكود<input dir="ltr" lang="en" name="language" value="<?php echo e(data_get($section->content,'language')); ?>"></label>
                    <label class="full">المحتوى الرئيسي<textarea name="content_text" rows="5"><?php echo e(data_get($section->content,'text')); ?></textarea></label>
                    <label class="full">عناصر متعددة<textarea name="items_text" rows="4"><?php echo e(implode("\n",(array)data_get($section->content,'items',[]))); ?></textarea></label>
                    <div class="full section-heading"><div><div class="eyebrow">حقول متخصصة</div><h2>للأمثلة والأخطاء الشائعة والكود</h2></div></div>
                    <label>الخطأ الشائع<textarea name="mistake" rows="3"><?php echo e(data_get($section->content,'common_mistake.mistake')); ?></textarea></label>
                    <label>الفهم الصحيح<textarea name="correction" rows="3"><?php echo e(data_get($section->content,'common_mistake.correction')); ?></textarea></label>
                    <label>مسألة المثال المحلول<textarea name="worked_problem" rows="3"><?php echo e(data_get($section->content,'worked_example.problem')); ?></textarea></label>
                    <label>طريقة التفكير<textarea name="worked_thinking" rows="3"><?php echo e(data_get($section->content,'worked_example.thinking')); ?></textarea></label>
                    <label>الحل<textarea name="worked_solution" rows="3"><?php echo e(data_get($section->content,'worked_example.solution')); ?></textarea></label>
                    <label>شرح الحل<textarea name="worked_explanation" rows="3"><?php echo e(data_get($section->content,'worked_example.explanation')); ?></textarea></label>
                    <label class="full">الخلاصة من المثال<textarea name="worked_key_lesson" rows="2"><?php echo e(data_get($section->content,'worked_example.key_lesson')); ?></textarea></label>
                    <label>مشكلة الكود<textarea name="code_problem" rows="3"><?php echo e(data_get($section->content,'code_example.problem')); ?></textarea></label>
                    <label>منطق الحل<textarea name="code_logic" rows="3"><?php echo e(data_get($section->content,'code_example.logic')); ?></textarea></label>
                    <label class="full">الكود<textarea dir="ltr" lang="en" name="code_body" rows="6"><?php echo e(data_get($section->content,'code_example.code')); ?></textarea></label>
                    <label>شرح الكود<textarea name="code_explanation" rows="3"><?php echo e(data_get($section->content,'code_example.explanation')); ?></textarea></label>
                    <label>المخرجات المتوقعة<textarea dir="ltr" lang="en" name="code_expected_output" rows="3"><?php echo e(data_get($section->content,'code_example.expected_output')); ?></textarea></label>
                    <label>جرّب بنفسك<textarea name="code_try_it" rows="3"><?php echo e(data_get($section->content,'code_example.try_it')); ?></textarea></label>
                    <div class="full section-heading"><div><div class="eyebrow">الأسئلة</div><h2>حقول التقييم المنظمة</h2></div></div>
                    <label>نوع السؤال<select name="question_type"><option value="short_answer" <?php if(data_get($section->content,'question.type','short_answer')==='short_answer'): echo 'selected'; endif; ?>>إجابة قصيرة</option><option value="multiple_choice" <?php if(data_get($section->content,'question.type')==='multiple_choice'): echo 'selected'; endif; ?>>اختيار من متعدد</option><option value="true_false" <?php if(data_get($section->content,'question.type')==='true_false'): echo 'selected'; endif; ?>>صح أو خطأ</option><option value="complete" <?php if(data_get($section->content,'question.type')==='complete'): echo 'selected'; endif; ?>>أكمل</option><option value="matching" <?php if(data_get($section->content,'question.type')==='matching'): echo 'selected'; endif; ?>>توصيل</option><option value="essay" <?php if(data_get($section->content,'question.type')==='essay'): echo 'selected'; endif; ?>>مقالي</option></select></label>
                    <label>مساحة الإجابة<select name="question_answer_space"><option value="medium" <?php if(data_get($section->content,'question.answer_space','medium')==='medium'): echo 'selected'; endif; ?>>متوسطة</option><option value="none" <?php if(data_get($section->content,'question.answer_space')==='none'): echo 'selected'; endif; ?>>بدون مساحة</option><option value="small" <?php if(data_get($section->content,'question.answer_space')==='small'): echo 'selected'; endif; ?>>صغيرة</option><option value="large" <?php if(data_get($section->content,'question.answer_space')==='large'): echo 'selected'; endif; ?>>كبيرة</option></select></label>
                    <label class="full">نص السؤال<textarea name="question_prompt" rows="3"><?php echo e(data_get($section->content,'question.prompt')); ?></textarea></label>
                    <label class="full">اختيارات السؤال — سطر لكل اختيار<textarea name="question_options_text" rows="4"><?php echo e(implode("\n",(array)data_get($section->content,'question.options',[]))); ?></textarea></label>
                    <label>الإجابة النموذجية<textarea name="question_answer" rows="3"><?php echo e(data_get($section->content,'question.answer')); ?></textarea></label>
                    <label>شرح الإجابة<textarea name="question_explanation" rows="3"><?php echo e(data_get($section->content,'question.explanation')); ?></textarea></label>
                    <label class="full">مجموعة أسئلة — سؤال في كل سطر<textarea name="question_group_text" rows="5"><?php echo e(collect((array)data_get($section->content,'question_group.questions',[]))->pluck('prompt')->implode("\n")); ?></textarea></label>
                    <label>مساحة إجابات المجموعة<select name="question_group_answer_space"><option value="medium" <?php if(data_get($section->content,'question_group.answer_space','medium')==='medium'): echo 'selected'; endif; ?>>متوسطة</option><option value="none" <?php if(data_get($section->content,'question_group.answer_space')==='none'): echo 'selected'; endif; ?>>بدون مساحة</option><option value="small" <?php if(data_get($section->content,'question_group.answer_space')==='small'): echo 'selected'; endif; ?>>صغيرة</option><option value="large" <?php if(data_get($section->content,'question_group.answer_space')==='large'): echo 'selected'; endif; ?>>كبيرة</option></select></label>
                    <div class="full section-heading"><div><div class="eyebrow">الجداول والصور</div><h2>بيانات منظمة للطباعة والويب</h2></div></div>
                    <label class="full">صفوف الجدول — استخدم | بين الخلايا<textarea name="table_text" rows="4"><?php echo e(collect((array)data_get($section->content,'table.rows',[]))->map(fn($row) => implode(' | ', (array)$row))->implode("\n")); ?></textarea></label>
                    <label>صورة من ملفات الدرس<select name="image_media_asset_id"><option value="">بدون صورة مرفوعة</option><?php $__currentLoopData = $lesson->mediaAssets->filter(fn($asset) => str_starts_with((string)$asset->mime_type, 'image/')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($asset->id); ?>" <?php if((int)data_get($section->content,'image.media_asset_id')===$asset->id): echo 'selected'; endif; ?>><?php echo e($asset->original_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label>رابط صورة خارجي<input dir="ltr" name="image_url" value="<?php echo e(data_get($section->content,'image.url')); ?>"></label>
                    <label>النص البديل للصورة<input name="image_alt" value="<?php echo e(data_get($section->content,'image.alt')); ?>"></label>
                    <label>تعليق الصورة<input name="image_caption" value="<?php echo e(data_get($section->content,'image.caption')); ?>"></label>
                    <label>محاذاة الصورة<select name="image_alignment"><option value="center" <?php if(data_get($section->content,'image.alignment','center')==='center'): echo 'selected'; endif; ?>>وسط</option><option value="start" <?php if(data_get($section->content,'image.alignment')==='start'): echo 'selected'; endif; ?>>بداية</option><option value="end" <?php if(data_get($section->content,'image.alignment')==='end'): echo 'selected'; endif; ?>>نهاية</option></select></label>
                    <label>عرض الصورة في الطباعة<select name="image_print_width"><option value="medium" <?php if(data_get($section->content,'image.print_width','medium')==='medium'): echo 'selected'; endif; ?>>متوسط</option><option value="small" <?php if(data_get($section->content,'image.print_width')==='small'): echo 'selected'; endif; ?>>صغير</option><option value="large" <?php if(data_get($section->content,'image.print_width')==='large'): echo 'selected'; endif; ?>>كبير</option><option value="full" <?php if(data_get($section->content,'image.print_width')==='full'): echo 'selected'; endif; ?>>كامل العرض</option></select></label>
                    <input type="hidden" name="settings[show_on_web]" value="0"><label><span><input type="checkbox" name="settings[show_on_web]" value="1" <?php if($settings['show_on_web']): echo 'checked'; endif; ?>> يظهر في صفحة الطالب</span></label>
                    <input type="hidden" name="settings[show_in_workbook]" value="0"><label><span><input type="checkbox" name="settings[show_in_workbook]" value="1" <?php if($settings['show_in_workbook']): echo 'checked'; endif; ?>> يظهر في الكراسة</span></label>
                    <input type="hidden" name="settings[show_in_teacher_edition]" value="0"><label><span><input type="checkbox" name="settings[show_in_teacher_edition]" value="1" <?php if($settings['show_in_teacher_edition']): echo 'checked'; endif; ?>> يظهر في نسخة المعلم</span></label>
                    <input type="hidden" name="settings[avoid_page_break_inside]" value="0"><label><span><input type="checkbox" name="settings[avoid_page_break_inside]" value="1" <?php if($settings['avoid_page_break_inside']): echo 'checked'; endif; ?>> لا تقطع داخل الكتلة</span></label>
                    <input type="hidden" name="settings[page_break_before]" value="0"><label><span><input type="checkbox" name="settings[page_break_before]" value="1" <?php if($settings['page_break_before']): echo 'checked'; endif; ?>> فاصل قبلها</span></label>
                    <input type="hidden" name="settings[page_break_after]" value="0"><label><span><input type="checkbox" name="settings[page_break_after]" value="1" <?php if($settings['page_break_after']): echo 'checked'; endif; ?>> فاصل بعدها</span></label>
                    <div class="full form-actions"><button class="button button-secondary"><span aria-hidden="true">✓</span> حفظ الكتلة</button></div>
                </form>
                <div class="actions-inline" style="margin-top:.75rem">
                    <form method="post" action="<?php echo e(route('admin.lesson-sections.move',[$course,$lesson,$section,'up'])); ?>"><?php echo csrf_field(); ?><button class="button button-quiet"><span aria-hidden="true">↑</span> أعلى</button></form>
                    <form method="post" action="<?php echo e(route('admin.lesson-sections.move',[$course,$lesson,$section,'down'])); ?>"><?php echo csrf_field(); ?><button class="button button-quiet"><span aria-hidden="true">↓</span> أسفل</button></form>
                    <form method="post" action="<?php echo e(route('admin.lesson-sections.duplicate',[$course,$lesson,$section])); ?>"><?php echo csrf_field(); ?><button class="button button-quiet"><span aria-hidden="true">⧉</span> نسخ</button></form>
                    <form method="post" action="<?php echo e(route('admin.lesson-sections.destroy',[$course,$lesson,$section])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('delete'); ?><button class="button button-danger"><span aria-hidden="true">×</span> حذف</button></form>
                </div>
            </details>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="empty-state">لم تتم إضافة محتوى منظم بعد. ابدأ بكتلة مقدمة أو أهداف تعلم.</div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const form = document.querySelector('[data-pdf-upload-form]');
    if (!form) return;
    const input = form.querySelector('[data-pdf-upload-input]');
    const status = form.querySelector('[data-pdf-upload-status]');
    const button = form.querySelector('[data-pdf-upload-button]');
    const maxBytes = Number(form.dataset.maxBytes || 0);
    const originalStatus = status?.textContent || '';
    const originalButton = button?.textContent || '';
    const formatBytes = (bytes) => {
        if (bytes >= 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024 / 1024).toFixed(1)}GB`;
        if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)}MB`;
        return `${(bytes / 1024).toFixed(1)}KB`;
    };
    const showFileState = () => {
        const file = input?.files?.[0];
        if (!file) {
            if (status) status.textContent = originalStatus;
            if (button) button.disabled = false;
            return true;
        }
        if (maxBytes && file.size > maxBytes) {
            if (status) status.textContent = `الملف المحدد (${formatBytes(file.size)}) أكبر من حد الخادم الحالي (${formatBytes(maxBytes)}). شغّل composer run serve أو اختر ملفًا أصغر.`;
            if (button) button.disabled = true;
            return false;
        }
        if (status) status.textContent = `جاهز للرفع: ${file.name} (${formatBytes(file.size)})`;
        if (button) button.disabled = false;
        return true;
    };
    input?.addEventListener('change', showFileState);
    form.addEventListener('submit', (event) => {
        if (!showFileState()) {
            event.preventDefault();
            return;
        }
        if (button) {
            button.disabled = true;
            button.textContent = 'جارٍ رفع PDF...';
        }
    });
    window.addEventListener('pageshow', () => {
        if (button) {
            button.disabled = false;
            button.textContent = originalButton;
        }
        showFileState();
    });
})();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/admin/courses/lesson.blade.php ENDPATH**/ ?>