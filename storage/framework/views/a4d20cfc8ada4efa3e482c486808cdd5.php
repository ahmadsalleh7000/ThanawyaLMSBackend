<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?php echo e($unit->title_ar); ?> | <?php echo e(($edition ?? 'student') === 'teacher' ? 'نسخة المعلم' : 'كراسة الوحدة'); ?></title>
    <script defer src="<?php echo e(asset('js/workbook.js')); ?>?v=<?php echo e(filemtime(public_path('js/workbook.js'))); ?>"></script>
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        html { background: #eef2f7; }
        body {
            margin: 0;
            color: #172033;
            background: #eef2f7;
            font-family: "Tajawal", "Noto Sans Arabic", Arial, sans-serif;
            line-height: 1.75;
        }
        .workbook-toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            color: #fff;
            background: #0f2347;
        }
        .workbook-toolbar a,
        .workbook-toolbar button {
            min-height: 38px;
            padding: .45rem .8rem;
            border: 1px solid rgba(255,255,255,.45);
            border-radius: 6px;
            color: #fff;
            background: transparent;
            text-decoration: none;
            cursor: pointer;
        }
        .workbook-page {
            width: 210mm;
            min-height: 297mm;
            margin: 14px auto;
            padding: 16mm 14mm;
            background: #fff;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .14);
        }
        .workbook-cover {
            display: grid;
            gap: .7rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #0f2347;
        }
        .workbook-kicker {
            color: #475569;
            font-size: .78rem;
            font-weight: 800;
        }
        h1, h2, h3 { margin: 0; color: #0f2347; break-after: avoid; }
        h1 { font-size: 1.75rem; line-height: 1.35; }
        .workbook-meta {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem;
            margin-top: .4rem;
        }
        .workbook-meta span {
            padding: .45rem .55rem;
            border: 1px solid #d8e0ea;
            background: #f8fafc;
        }
        .workbook-meta small,
        .workbook-footer {
            color: #64748b;
            font-size: .68rem;
        }
        .workbook-meta b { display: block; color: #172033; font-size: .78rem; }
        .unit-toc,
        .workbook-section {
            margin-top: 1rem;
            break-inside: avoid;
        }
        .workbook-section h2,
        .lesson-title {
            padding-bottom: .25rem;
            border-bottom: 1px solid #d8e0ea;
            font-size: 1rem;
        }
        .lesson-title {
            margin-top: 1.15rem;
            color: #172033;
        }
        .lesson-title small {
            color: #64748b;
            font-size: .7rem;
            font-weight: 700;
        }
        .workbook-section p { margin: .45rem 0 0; }
        .workbook-list {
            margin: .55rem 0 0;
            padding-inline-start: 1.3rem;
            color: #334155;
            font-size: .82rem;
        }
        .workbook-list li { margin-bottom: .15rem; }
        .workbook-resources {
            width: 100%;
            margin-top: .6rem;
            border-collapse: collapse;
            font-size: .72rem;
            break-inside: avoid;
        }
        .workbook-resources th,
        .workbook-resources td {
            padding: .4rem .45rem;
            border: 1px solid #cbd5e1;
            text-align: start;
            vertical-align: top;
        }
        .workbook-resources th { color: #0f2347; background: #f1f5f9; }
        .answer-lines { display: grid; gap: .45rem; margin-top: .45rem; }
        .answer-lines span { display: block; height: 18px; border-bottom: 1px solid #94a3b8; }
        .workbook-code {
            direction: ltr;
            overflow-wrap: anywhere;
            padding: .7rem;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            text-align: left;
            white-space: pre-wrap;
            font-family: "Courier New", monospace;
            font-size: .72rem;
            line-height: 1.55;
        }
        .workbook-callout {
            padding: .7rem .85rem;
            border: 1px solid #d8e0ea;
            border-inline-start: 5px solid #475569;
            background: #f8fafc;
        }
        .workbook-callout.is-important { border-inline-start-color: #163b75; background: #f8fbff; }
        .workbook-callout.is-mistake { border-inline-start-color: #9f1239; background: #fff7f7; }
        .workbook-code-label {
            direction: ltr;
            display: inline-block;
            margin-bottom: .3rem;
            padding: .08rem .35rem;
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #fff;
            text-align: left;
            font-family: "Courier New", monospace;
            font-size: .62rem;
        }
        .workbook-content-table { margin-top: .55rem; }
        .workbook-image { display: grid; gap: .35rem; margin: .55rem 0 0; break-inside: avoid; }
        .workbook-image.is-center { justify-items: center; }
        .workbook-image.is-start { justify-items: start; }
        .workbook-image.is-end { justify-items: end; }
        .workbook-image img { max-width: 100%; max-height: 170mm; border: 1px solid #d8e0ea; object-fit: contain; }
        .workbook-image.is-small img { width: 55mm; }
        .workbook-image.is-medium img { width: 95mm; }
        .workbook-image.is-large img { width: 140mm; }
        .workbook-image.is-full img { width: 100%; }
        .workbook-image figcaption { max-width: 140mm; color: #64748b; font-size: .68rem; line-height: 1.6; text-align: center; }
        .workbook-mistakes { display: grid; gap: .6rem; margin-top: .55rem; }
        .workbook-mistakes article { padding: .55rem .65rem; border: 1px solid #fecdd3; background: #fff; break-inside: avoid; }
        .workbook-mistakes strong,
        .workbook-field strong { display: block; color: #0f2347; font-size: .72rem; }
        .workbook-field { display: grid; gap: .18rem; margin-top: .5rem; }
        .workbook-output { margin-top: .1rem; background: #fff; }
        .workbook-page-break { break-before: page; height: 0; }
        .lesson-start { break-before: page; }
        .lesson-start:first-of-type { break-before: auto; }
        .workbook-footer {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: .5rem;
            border-top: 1px solid #d8e0ea;
        }
        @media print {
            html, body { background: #fff; }
            .workbook-toolbar { display: none; }
            .workbook-page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <nav class="workbook-toolbar" aria-label="إجراءات كراسة الوحدة">
        <strong><?php echo e(($edition ?? 'student') === 'teacher' ? 'معاينة نسخة المعلم للوحدة' : 'معاينة كراسة الوحدة'); ?></strong>
        <span>
            <?php if(!empty($adminPreview)): ?>
                <a href="<?php echo e(route('admin.learning-paths.units.lessons', [$course, $unit])); ?>">العودة للوحدة</a>
            <?php endif; ?>
            <button type="button" data-workbook-print>طباعة A4</button>
        </span>
    </nav>

    <main class="workbook-page">
        <header class="workbook-cover">
            <span class="workbook-kicker"><?php echo e($brandName); ?> · <?php echo e(($edition ?? 'student') === 'teacher' ? 'نسخة المعلم' : 'كراسة وحدة'); ?></span>
            <h1><?php echo e($unit->title_ar); ?></h1>
            <div class="workbook-meta" aria-label="بيانات الوحدة">
                <span><small>الدورة</small><b><?php echo e($course->title); ?></b></span>
                <span><small>الوحدة</small><b><?php echo e(str_pad($unit->position, 2, '0', STR_PAD_LEFT)); ?></b></span>
                <span><small>عدد الدروس</small><b><?php echo e($lessons->count()); ?></b></span>
                <span><small>تاريخ الطباعة</small><b><?php echo e($printDate->format('Y-m-d')); ?></b></span>
            </div>
        </header>

        <section class="workbook-section unit-toc">
            <h2>فهرس الوحدة</h2>
            <ol class="workbook-list">
                <?php $__empty_1 = true; $__currentLoopData = $lessons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lesson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <li><?php echo e($lesson->title); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <li>لا توجد دروس منشورة في هذه الوحدة.</li>
                <?php endif; ?>
            </ol>
        </section>

        <?php $__currentLoopData = $lessons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lesson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="lesson-start">
                <h2 class="lesson-title">
                    <?php echo e(str_pad($lesson->position, 2, '0', STR_PAD_LEFT)); ?>. <?php echo e($lesson->title); ?>

                    <small>· <?php echo e($lesson->duration_minutes); ?> دقيقة</small>
                </h2>

                <?php if($lesson->summary): ?>
                    <section class="workbook-section workbook-callout is-important">
                        <h2>فكرة الدرس</h2>
                        <p><?php echo e($lesson->summary); ?></p>
                    </section>
                <?php endif; ?>

                <?php if(is_array($lesson->objectives) && count($lesson->objectives)): ?>
                    <section class="workbook-section">
                        <h2>أهداف التعلم</h2>
                        <ol class="workbook-list">
                            <?php $__currentLoopData = $lesson->objectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($objective); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <?php $__currentLoopData = $lesson->sections->filter(fn($section) => (($edition ?? 'student') === 'teacher' ? ($section->visibleInWorkbook() || (($section->settingsWithDefaults()['show_in_teacher_edition']) ?? false)) : $section->visibleInWorkbook()) && (($includeAnswerKey ?? false) || $section->type !== 'answer_key')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo $__env->make('workbooks.partials.lesson-section', ['section' => $section, 'lesson' => $lesson], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <footer class="workbook-footer">
            <span><?php echo e($brandName); ?></span>
            <span><?php echo e(($edition ?? 'student') === 'teacher' ? 'نسخة المعلم' : 'كراسة الوحدة'); ?> · <?php echo e($unit->title_ar); ?></span>
        </footer>
    </main>
</body>
</html>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/workbooks/unit.blade.php ENDPATH**/ ?>