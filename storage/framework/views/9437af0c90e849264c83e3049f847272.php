<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?php echo e($lesson->content_pdf_title ?: $lesson->title); ?> | <?php echo e(($edition ?? 'student') === 'teacher' ? 'نسخة المعلم' : 'كراسة الدرس'); ?></title>
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
            z-index: 5;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .8rem 1rem;
            color: #fff;
            background: #0f2347;
        }
        .workbook-toolbar strong { font-size: .9rem; }
        .workbook-toolbar button,
        .workbook-toolbar a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .45rem .75rem;
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 8px;
            color: #fff;
            background: rgba(255,255,255,.1);
            text-decoration: none;
            font: inherit;
            font-size: .72rem;
            font-weight: 800;
            cursor: pointer;
        }
        .workbook-page {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 14mm;
            background: #fff;
            box-shadow: 0 18px 60px rgba(15, 23, 42, .16);
        }
        .workbook-cover {
            display: grid;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #163b75;
            break-after: avoid;
        }
        .workbook-kicker {
            color: #163b75;
            font-size: .78rem;
            font-weight: 900;
        }
        h1 {
            max-width: 165mm;
            margin: 0;
            color: #101827;
            font-size: 1.55rem;
            line-height: 1.45;
            break-after: avoid;
        }
        h2, h3 { break-after: avoid; }
        .workbook-meta {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .45rem;
            margin-top: .35rem;
        }
        .workbook-meta span {
            min-height: 48px;
            display: grid;
            gap: .1rem;
            padding: .45rem .55rem;
            border: 1px solid #d8e0ea;
            background: #f8fafc;
            font-size: .68rem;
        }
        .workbook-meta b {
            overflow: hidden;
            color: #0f2347;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .workbook-section {
            margin-top: 1rem;
            break-inside: avoid;
        }
        .workbook-section h2 {
            margin: 0 0 .45rem;
            padding-inline-start: .5rem;
            border-inline-start: 4px solid #163b75;
            color: #0f2347;
            font-size: 1rem;
        }
        .workbook-section p {
            margin: 0;
            color: #334155;
            font-size: .82rem;
        }
        .workbook-list {
            display: grid;
            gap: .35rem;
            margin: 0;
            padding-inline-start: 1.2rem;
            color: #334155;
            font-size: .8rem;
        }
        .workbook-note {
            padding: .7rem .85rem;
            border: 1px solid #c7d7ee;
            border-inline-start: 5px solid #163b75;
            background: #f8fbff;
            break-inside: avoid;
        }
        .workbook-outline li { margin-bottom: .15rem; }
        .workbook-resources {
            width: 100%;
            border-collapse: collapse;
            font-size: .72rem;
            break-inside: avoid;
        }
        .workbook-resources th,
        .workbook-resources td {
            padding: .42rem .5rem;
            border: 1px solid #d8e0ea;
            text-align: start;
            vertical-align: top;
        }
        .workbook-resources th { color: #0f2347; background: #f1f5f9; }
        .workbook-practice {
            display: grid;
            gap: .75rem;
        }
        .workbook-question {
            padding: .75rem;
            border: 1px solid #d8e0ea;
            background: #fff;
            break-inside: avoid;
        }
        .workbook-question strong {
            display: block;
            margin-bottom: .45rem;
            color: #101827;
            font-size: .78rem;
        }
        .workbook-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .3rem .75rem;
            margin: .35rem 0 .65rem;
            padding: 0;
            list-style: none;
            color: #334155;
            font-size: .72rem;
        }
        .answer-lines {
            display: grid;
            gap: .45rem;
            margin-top: .45rem;
        }
        .answer-lines span {
            display: block;
            height: 18px;
            border-bottom: 1px solid #94a3b8;
        }
        .workbook-pdf-link {
            direction: ltr;
            text-align: left;
            word-break: break-all;
            font-family: "Courier New", monospace;
            font-size: .68rem;
        }
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
        .workbook-content-table {
            margin-top: .55rem;
        }
        .workbook-image {
            display: grid;
            gap: .35rem;
            margin: .55rem 0 0;
            break-inside: avoid;
        }
        .workbook-image.is-center { justify-items: center; }
        .workbook-image.is-start { justify-items: start; }
        .workbook-image.is-end { justify-items: end; }
        .workbook-image img {
            max-width: 100%;
            max-height: 170mm;
            border: 1px solid #d8e0ea;
            object-fit: contain;
        }
        .workbook-image.is-small img { width: 55mm; }
        .workbook-image.is-medium img { width: 95mm; }
        .workbook-image.is-large img { width: 140mm; }
        .workbook-image.is-full img { width: 100%; }
        .workbook-image figcaption {
            max-width: 140mm;
            color: #64748b;
            font-size: .68rem;
            line-height: 1.6;
            text-align: center;
        }
        .workbook-mistakes {
            display: grid;
            gap: .6rem;
            margin-top: .55rem;
        }
        .workbook-mistakes article {
            padding: .55rem .65rem;
            border: 1px solid #fecdd3;
            background: #fff;
            break-inside: avoid;
        }
        .workbook-mistakes strong {
            display: block;
            color: #9f1239;
            font-size: .72rem;
        }
        .workbook-mistakes p + strong {
            margin-top: .35rem;
            color: #0f2347;
        }
        .workbook-field {
            display: grid;
            gap: .18rem;
            margin-top: .5rem;
        }
        .workbook-field strong {
            color: #0f2347;
            font-size: .72rem;
        }
        .workbook-output {
            margin-top: .1rem;
            background: #fff;
        }
        .workbook-page-break { break-before: page; height: 0; }
        .workbook-footer {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: .5rem;
            border-top: 1px solid #d8e0ea;
            color: #64748b;
            font-size: .64rem;
        }
        @media print {
            html, body { background: #fff; }
            .workbook-toolbar { display: none; }
            .workbook-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            a { color: inherit; text-decoration: none; }
        }
        @media screen and (max-width: 760px) {
            .workbook-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 1rem;
            }
            .workbook-meta,
            .workbook-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="workbook-toolbar" aria-label="إجراءات كراسة الدرس">
        <strong><?php echo e(($edition ?? 'student') === 'teacher' ? 'معاينة نسخة المعلم' : 'معاينة كراسة الدرس'); ?></strong>
        <span>
            <?php if(!empty($adminPreview)): ?>
                <a href="<?php echo e(route('admin.lessons.edit', [$course, $lesson])); ?>">العودة للدرس</a>
            <?php endif; ?>
            <button type="button" data-workbook-print>طباعة A4</button>
        </span>
    </nav>

    <main class="workbook-page">
        <?php if($lesson->include_workbook_cover): ?>
            <header class="workbook-cover">
                <span class="workbook-kicker"><?php echo e($brandName); ?> · <?php echo e(($edition ?? 'student') === 'teacher' ? 'نسخة المعلم' : 'كراسة درس'); ?></span>
                <h1><?php echo e($lesson->content_pdf_title ?: $lesson->title); ?></h1>
                <div class="workbook-meta" aria-label="بيانات الدرس">
                    <span><small>الدورة</small><b><?php echo e($course->title); ?></b></span>
                    <span><small>الوحدة</small><b><?php echo e($unit?->title_ar ?: $course->unit); ?></b></span>
                    <span><small>الصف</small><b><?php echo e($course->grade ?: 'غير محدد'); ?></b></span>
                    <span><small>المدة</small><b><?php echo e($lesson->duration_minutes); ?> دقيقة</b></span>
                </div>
            </header>
        <?php endif; ?>

        <?php if($lesson->include_workbook_info && $lesson->summary): ?>
            <section class="workbook-section workbook-note">
                <h2>فكرة الدرس</h2>
                <p><?php echo e($lesson->summary); ?></p>
            </section>
        <?php endif; ?>

        <?php if($lesson->include_workbook_info && is_array($lesson->objectives) && count($lesson->objectives)): ?>
            <section class="workbook-section">
                <h2>أهداف التعلم</h2>
                <ol class="workbook-list">
                    <?php $__currentLoopData = $lesson->objectives; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($objective); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ol>
            </section>
        <?php endif; ?>

        <?php if($lesson->include_workbook_info && is_array($lesson->key_points) && count($lesson->key_points)): ?>
            <section class="workbook-section">
                <h2>النقاط الأساسية</h2>
                <ul class="workbook-list">
                    <?php $__currentLoopData = $lesson->key_points; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $point): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($point); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if($pdfUrl): ?>
            <section class="workbook-section workbook-note">
                <h2>ملف محتوى الدرس</h2>
                <p><?php echo e($lesson->content_pdf_description ?: 'يمكن فتح ملف الدرس الأصلي من الرابط التالي للمراجعة الرقمية.'); ?></p>
                <p class="workbook-pdf-link"><?php echo e($pdfUrl); ?></p>
            </section>
        <?php endif; ?>

        <?php if($lesson->include_workbook_toc && is_array($lesson->transcript) && count($lesson->transcript)): ?>
            <section class="workbook-section">
                <h2>خريطة الدرس</h2>
                <ol class="workbook-list workbook-outline">
                    <?php $__currentLoopData = $lesson->transcript; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($line); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ol>
            </section>
        <?php endif; ?>

        <?php $__currentLoopData = $lesson->sections->filter(fn($section) => (($edition ?? 'student') === 'teacher' ? ($section->visibleInWorkbook() || (($section->settingsWithDefaults()['show_in_teacher_edition']) ?? false)) : $section->visibleInWorkbook()) && (($includeAnswerKey ?? false) || $section->type !== 'answer_key')); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('workbooks.partials.lesson-section', ['section' => $section], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <?php if($resources->isNotEmpty()): ?>
            <section class="workbook-section">
                <h2>ملفات مساعدة</h2>
                <table class="workbook-resources">
                    <thead>
                        <tr>
                            <th>الملف</th>
                            <th>النوع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $resources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resource): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><?php echo e($resource->original_name); ?></td>
                                <td><?php echo e($resource->mime_type); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <?php if($practice): ?>
            <section class="workbook-section workbook-practice">
                <h2>تدريب الدرس</h2>
                <?php if($practice->instructions_ar): ?>
                    <p><?php echo e($practice->instructions_ar); ?></p>
                <?php endif; ?>
                <?php $__currentLoopData = $practice->questionSet(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="workbook-question">
                        <strong><?php echo e($index + 1); ?>. <?php echo e($question->prompt_ar ?: $question->prompt); ?></strong>
                        <?php if(is_array($question->options_ar ?: $question->options) && count($question->options_ar ?: $question->options)): ?>
                            <ul class="workbook-options">
                                <?php $__currentLoopData = ($question->options_ar ?: $question->options); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li>□ <?php echo e($option); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        <?php endif; ?>
                        <div class="answer-lines" aria-label="مساحة الإجابة">
                            <span></span><span></span><span></span>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </section>
        <?php endif; ?>

        <footer class="workbook-footer">
            <span><?php echo e($brandName); ?></span>
            <span>تم الإنشاء: <?php echo e($printDate->format('Y/m/d')); ?></span>
        </footer>
    </main>
</body>
</html>
<?php /**PATH /home/ahmed/Ahmed/MyWebsite/backend/resources/views/workbooks/lesson.blade.php ENDPATH**/ ?>