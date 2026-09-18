@extends('admin.layout')
@section('title', 'تحرير الدرس')
@section('content')
<nav class="academic-breadcrumb" aria-label="السياق الأكاديمي"><a href="{{ route('admin.academics.index') }}">الهيكل الأكاديمي</a><span>←</span><span>{{ $course->academicSubject?->term?->grade?->name_ar ?? $course->grade }}</span><span>←</span><span>{{ $course->academicSubject?->term?->name_ar ?? $course->semester }}</span><span>←</span><span>{{ $course->academicSubject?->subject?->name_ar ?? $course->category }}</span><span>←</span><strong>{{ $course->title }}</strong></nav>
<header class="page-header lesson-editor-hero"><div><div class="eyebrow">{{ $course->title }}</div><h1>تحرير الدرس</h1><p>إدارة محتوى الدرس، الوسائط، الكراسة، والتدريبات من مساحات واضحة ومنفصلة.</p></div><div class="actions-inline lesson-editor-actions"><a class="button button-secondary" href="{{ route('admin.lessons.workbook-preview',[$course,$lesson]) }}" target="_blank" rel="noreferrer"><span aria-hidden="true">▤</span> معاينة كراسة A4</a><a class="button button-secondary" href="{{ route('admin.lessons.workbook-download',[$course,$lesson]) }}"><span aria-hidden="true">↓</span> تنزيل PDF</a><a class="button button-secondary" href="{{ route('admin.lessons.teacher-edition-preview',[$course,$lesson]) }}" target="_blank" rel="noreferrer"><span aria-hidden="true">◉</span> نسخة المعلم</a><a class="button button-quiet" href="{{ route('admin.courses.edit',$course) }}"><span aria-hidden="true">←</span> العودة للدورة</a></div></header>
@php
    $activeLessonTab = in_array(request('tab', 'details'), ['details', 'pdf', 'workbook', 'learning', 'completion', 'media', 'questions', 'discussion', 'content'], true) ? request('tab', 'details') : 'details';
    $lessonTabs = [
        'details' => ['label' => 'بيانات الدرس', 'icon' => '✎'],
        'pdf' => ['label' => 'قارئ PDF', 'icon' => '▤'],
        'workbook' => ['label' => 'الكراسة', 'icon' => '□'],
        'learning' => ['label' => 'أهداف التعلم', 'icon' => '◎'],
        'completion' => ['label' => 'الإكمال والنصوص', 'icon' => '✓'],
        'media' => ['label' => 'الوسائط', 'icon' => '▶'],
        'questions' => ['label' => 'التدريب والأسئلة', 'icon' => '?'],
        'discussion' => ['label' => 'مناقشة الدرس', 'icon' => '◌'],
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
@endphp
<nav class="lesson-editor-tabs" aria-label="أقسام تحرير الدرس">
    @foreach($lessonTabs as $tab => $item)
        <a class="{{ $activeLessonTab === $tab ? 'is-active' : '' }}" href="{{ route('admin.lessons.edit',[$course,$lesson]) }}?tab={{ $tab }}"><span aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
@endforeach
</nav>
@if($activeLessonTab !== 'content')
<section class="lesson-editor-panel {{ in_array($activeLessonTab, ['details', 'media', 'questions'], true) ? 'stack' : 'grid content-columns' }}">
    <div class="stack">
        @if($activeLessonTab === 'details')
        <form id="lesson-details-form" class="card lesson-details-form" method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}" data-autosave-key="lesson-editor-{{ $lesson->id }}">
            @csrf @method('put')
            <div class="full section-heading"><div><div class="eyebrow">بيانات الدرس</div><h2>العنوان والنشر والإتاحة</h2><p>هذه هي الحقول الأساسية التي تحدد مكان الدرس وظهوره داخل الدورة.</p></div></div>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">1</span><div><strong>هوية الدرس</strong><small>العنوان والمعرّف المستخدمان في الإدارة ومشغل الطالب.</small></div></div>
                <div class="lesson-form-grid">
                    <label>معرّف الدرس<input dir="ltr" lang="en" name="slug" value="{{ old('slug',$lesson->slug) }}" required><small>يستخدم في روابط المشغل، مثل lesson-name.</small></label>
                    <label>الترتيب<input type="number" name="position" min="1" value="{{ old('position',$lesson->position) }}" required></label>
                    <label>العنوان العربي<input name="title" value="{{ old('title',$lesson->title) }}" required></label>
                    <label lang="en">English title<input dir="ltr" lang="en" name="title_en" value="{{ old('title_en',$lesson->title_en) }}" required></label>
                </div>
            </section>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">2</span><div><strong>المكان والنشر</strong><small>حدد الوحدة، الحالة، ونافذة إتاحة الدرس للطلاب.</small></div></div>
                <div class="lesson-form-grid">
                    <label>الوحدة<select name="unit_id"><option value="">بدون وحدة</option>@foreach($course->academicSubject?->units ?? [] as $unit)<option value="{{ $unit->id }}" @selected((string)old('unit_id',$lesson->unit_id)===(string)$unit->id)>{{ $unit->position }}. {{ $unit->title_ar }}</option>@endforeach</select></label>
                    <label>حالة الدرس<select name="status"><option value="draft" @selected(old('status',$lesson->status)==='draft')>مسودة</option><option value="published" @selected(old('status',$lesson->status)==='published')>منشور</option><option value="archived" @selected(old('status',$lesson->status)==='archived')>مؤرشف</option></select></label>
                    <label>متاح من<input type="datetime-local" name="available_from" value="{{ old('available_from',$lesson->available_from?->format('Y-m-d\\TH:i')) }}"></label>
                    <label>متاح حتى<input type="datetime-local" name="available_until" value="{{ old('available_until',$lesson->available_until?->format('Y-m-d\\TH:i')) }}"></label>
                    <label>المدة بالدقائق<input type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes',$lesson->duration_minutes) }}" required></label>
                </div>
            </section>
            <section class="lesson-form-section">
                <div class="lesson-form-section-title"><span aria-hidden="true">3</span><div><strong>الفيديو والمعاينة</strong><small>استخدم رابطًا خارجيًا عند الحاجة، أو ارفع فيديو من تبويب الوسائط.</small></div></div>
                <div class="lesson-form-grid">
                    <label class="full">رابط فيديو خارجي اختياري<input dir="ltr" name="video_url" value="{{ old('video_url',$lesson->video_url) }}" placeholder="https://..."></label>
                    <input type="hidden" name="is_preview" value="0"><label class="full lesson-form-toggle"><span><input type="checkbox" name="is_preview" value="1" @checked(old('is_preview',$lesson->is_preview))><b>السماح بمعاينة هذا الدرس للعامة</b><small>فعّلها فقط للدروس التي يمكن للزائر مشاهدتها قبل الاشتراك.</small></span></label>
                    <input type="hidden" name="comments_enabled" value="0"><label class="full lesson-form-toggle"><span><input type="checkbox" name="comments_enabled" value="1" @checked(old('comments_enabled',$lesson->comments_enabled ?? true))><b>السماح بمناقشة الدرس</b><small>عند إيقافها تبقى التعليقات السابقة مرئية، ولا يمكن نشر تعليقات أو ردود جديدة.</small></span></label>
                </div>
            </section>
            <div class="full form-actions lesson-details-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ بيانات الدرس</button></div>
        </form>
        @endif
        @if($activeLessonTab === 'discussion')
        <section class="card stack">
            <div class="section-heading"><div><div class="eyebrow">التواصل التعليمي</div><h2>تعليقات الدرس</h2><p>راجع الأسئلة، أضف ردًا باسم فريق المنصة، أو احذف المحتوى غير المناسب.</p></div><span class="badge {{ $lesson->comments_enabled ? 'published' : 'inactive' }}">{{ $lesson->comments_enabled ? 'مفعّلة' : 'متوقفة' }}</span></div>
            <div class="review-grid">
                @forelse($comments as $comment)
                    <article class="review-card">
                        <div class="review-top"><div><strong>{{ $comment->user?->name ?? 'طالب سابق' }}</strong><small>{{ $comment->created_at->diffForHumans() }}</small></div><span class="badge draft">طالب</span></div>
                        <p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $comment->content }}</p>
                        @foreach($comment->replies as $reply)
                            <div class="block-note" style="padding:10px;border-inline-start:3px solid #0f766e"><strong>{{ $reply->user?->name ?? 'عضو فريق سابق' }} · فريق المنصة</strong><p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $reply->content }}</p><form method="post" action="{{ route('admin.lesson-comments.destroy',[$course,$lesson,$reply]) }}" data-confirm="حذف هذا الرد؟">@csrf @method('delete')<button class="button button-danger">حذف الرد</button></form></div>
                        @endforeach
                        @if($lesson->comments_enabled)
                            <form class="moderation-form" method="post" action="{{ route('admin.lesson-comments.reply',[$course,$lesson,$comment]) }}">@csrf<label>رد فريق المنصة<textarea name="content" required minlength="2" maxlength="2000"></textarea></label><button class="button button-secondary">نشر الرد</button></form>
                        @endif
                        <form method="post" action="{{ route('admin.lesson-comments.destroy',[$course,$lesson,$comment]) }}" data-confirm="سيتم حذف التعليق وردوده من المناقشة. هل تريد المتابعة؟">@csrf @method('delete')<button class="button button-danger">حذف للمراجعة</button></form>
                    </article>
                @empty
                    <div class="empty-state">لا توجد تعليقات في هذا الدرس بعد.</div>
                @endforelse
            </div>
            @if($comments)<div class="pagination">{{ $comments->links() }}</div>@endif
        </section>
        @endif
        @if($activeLessonTab === 'pdf')
        <form class="card form-grid" method="post" enctype="multipart/form-data" action="{{ route('admin.lesson-pdf.store',[$course,$lesson]) }}" data-pdf-upload-form data-max-bytes="{{ $activeUploadLimitBytes }}">
            @csrf
            <div class="full section-heading"><div><div class="eyebrow">رفع ملف PDF</div><h2>إضافة ملف درس جديد</h2><p>ارفع ملف PDF من جهازك. بعد الرفع سيتم تفعيل القارئ وربط الملف بهذا الدرس تلقائيًا.</p></div></div>
            <label class="full">اختر ملف PDF<input type="file" name="file" accept="application/pdf,.pdf" required data-pdf-upload-input><small data-pdf-upload-status>حد الخادم الحالي للطلب: {{ $activeUploadLimitLabel }}. في التطوير المحلي شغّل الخادم عبر composer run serve حتى يستخدم PHP حدود الرفع الصحيحة.</small>@error('file')<small role="alert">{{ $message }}</small>@enderror</label>
            <div class="full form-actions"><button class="button button-primary" data-pdf-upload-button><span aria-hidden="true">↑</span> رفع وربط PDF</button></div>
        </form>
        <section class="card stack">
            <div class="section-heading"><div><div class="eyebrow">ملفات PDF المرتبطة</div><h2>الملفات المتاحة لهذا الدرس</h2><p>بعد الرفع الناجح سيظهر الملف هنا، وسيتم اختياره تلقائيًا في إعدادات القارئ.</p></div></div>
            <div class="detail-list">
                @forelse($lesson->mediaAssets->filter(fn($asset) => $asset->mime_type === 'application/pdf') as $asset)
                    <div class="detail-item">
                        <span class="status-dot"></span>
                        <div style="flex:1"><strong>{{ $asset->original_name }}</strong><small>{{ $asset->kind === 'lesson_pdf' ? 'PDF محتوى الدرس' : 'ملف تعليمي' }} · {{ number_format($asset->size_bytes/1024/1024,2) }} MB @if((int)$lesson->content_pdf_media_id === (int)$asset->id) · مستخدم حالياً @endif</small></div>
                        @if((int)$lesson->content_pdf_media_id === (int)$asset->id)
                            <span class="badge published">محدد</span>
                        @else
                            <form method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}">
                                @csrf @method('put')
                                <input type="hidden" name="content_pdf_enabled" value="1">
                                <input type="hidden" name="content_pdf_media_id" value="{{ $asset->id }}">
                                <button class="button button-secondary"><span aria-hidden="true">✓</span> استخدام هذا الملف</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">لا توجد ملفات PDF مرفوعة لهذا الدرس بعد.</div>
                @endforelse
            </div>
        </section>
        <form class="card form-grid" method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}">
            @csrf @method('put')
            <div class="full section-heading"><div><div class="eyebrow">محتوى الدرس</div><h2>قارئ PDF للطلاب</h2><p>ارفع ملف PDF خاص بالدرس أو ضع رابط PDF خارجي، ثم فعّل ظهوره داخل مشغل الدورة.</p></div></div>
            <input type="hidden" name="content_pdf_enabled" value="0"><label class="full"><span><input type="checkbox" name="content_pdf_enabled" value="1" @checked(old('content_pdf_enabled',$lesson->content_pdf_enabled))> عرض محتوى الدرس كقارئ PDF احترافي</span></label>
            <label>ملف PDF مرفوع<select name="content_pdf_media_id"><option value="">بدون ملف مرفوع</option>@foreach($lesson->mediaAssets->filter(fn($asset) => $asset->mime_type === 'application/pdf') as $asset)<option value="{{ $asset->id }}" @selected((string)old('content_pdf_media_id',$lesson->content_pdf_media_id)===(string)$asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
            <label>رابط PDF خارجي اختياري<input dir="ltr" name="content_pdf_url" value="{{ old('content_pdf_url',$lesson->content_pdf_url) }}" placeholder="https://example.com/lesson.pdf"></label>
            <label>عنوان ملف الدرس<input name="content_pdf_title" value="{{ old('content_pdf_title',$lesson->content_pdf_title) }}" placeholder="{{ $lesson->title }}"></label>
            <label class="full">وصف قصير للملف<textarea name="content_pdf_description" rows="3">{{ old('content_pdf_description',$lesson->content_pdf_description) }}</textarea></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ إعدادات PDF</button></div>
        </form>
        @endif
        @if($activeLessonTab === 'workbook')
        <form class="card form-grid" method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}">
            @csrf @method('put')
            <div class="full section-heading"><div><div class="eyebrow">إعدادات الكراسة</div><h2>الطباعة والتنزيل</h2><p>تحكم في ظهور كراسة A4 للطلاب وما الذي تتضمنه النسخة المطبوعة.</p></div></div>
            <input type="hidden" name="workbook_enabled" value="0"><label><span><input type="checkbox" name="workbook_enabled" value="1" @checked(old('workbook_enabled',$lesson->workbook_enabled ?? true))> تفعيل كراسة الدرس</span></label>
            <input type="hidden" name="allow_print" value="0"><label><span><input type="checkbox" name="allow_print" value="1" @checked(old('allow_print',$lesson->allow_print ?? true))> السماح بالطباعة</span></label>
            <input type="hidden" name="allow_pdf_download" value="0"><label><span><input type="checkbox" name="allow_pdf_download" value="1" @checked(old('allow_pdf_download',$lesson->allow_pdf_download ?? true))> السماح بتنزيل PDF</span></label>
            <input type="hidden" name="include_workbook_cover" value="0"><label><span><input type="checkbox" name="include_workbook_cover" value="1" @checked(old('include_workbook_cover',$lesson->include_workbook_cover ?? true))> تضمين الغلاف</span></label>
            <input type="hidden" name="include_workbook_info" value="0"><label><span><input type="checkbox" name="include_workbook_info" value="1" @checked(old('include_workbook_info',$lesson->include_workbook_info ?? true))> تضمين فكرة الدرس والأهداف والنقاط</span></label>
            <input type="hidden" name="include_workbook_toc" value="0"><label><span><input type="checkbox" name="include_workbook_toc" value="1" @checked(old('include_workbook_toc',$lesson->include_workbook_toc ?? true))> تضمين خريطة الدرس</span></label>
            <input type="hidden" name="include_workbook_answer_key" value="0"><label><span><input type="checkbox" name="include_workbook_answer_key" value="1" @checked(old('include_workbook_answer_key',$lesson->include_workbook_answer_key ?? false))> تضمين الإجابات في كراسة الطالب</span></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ إعدادات الكراسة</button></div>
        </form>
        @endif
        @if($activeLessonTab === 'learning')
        <form class="card form-grid" method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}">
            @csrf @method('put')
            <div class="full section-heading"><div><div class="eyebrow">أهداف التعلم</div><h2>ما الذي يجب أن يخرج به الطالب؟</h2><p>اكتب كل هدف أو نقطة في سطر مستقل ليظهر للطالب كتوجيه واضح داخل مشغل الدرس.</p></div></div>
            <label class="full">أهداف الدرس — سطر لكل هدف<textarea name="objectives_text" rows="4">{{ old('objectives_text',implode("\n",$lesson->objectives ?? [])) }}</textarea></label>
            <label class="full">النقاط الأساسية — سطر لكل نقطة<textarea name="key_points_text" rows="4">{{ old('key_points_text',implode("\n",$lesson->key_points ?? [])) }}</textarea></label>
            <label class="full">ملخص الدرس<textarea name="summary" rows="4">{{ old('summary',$lesson->summary) }}</textarea></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ أهداف التعلم</button></div>
        </form>
        @endif
        @if($activeLessonTab === 'completion')
        @php($rules = old('completion_rules',$lesson->completion_rules ?? []))
        <form class="card form-grid" method="post" action="{{ route('admin.lessons.update',[$course,$lesson]) }}">
            @csrf @method('put')
            <div class="full section-heading"><div><div class="eyebrow">شروط الإكمال</div><h2>الإكمال والنصوص التفريغية</h2><p>اتركها بدون تحديد للدروس البسيطة، أو فعّل الشروط المهمة للدروس الأساسية.</p></div></div>
            <input type="hidden" name="completion_rules[require_content_opened]" value="0"><label><span><input type="checkbox" name="completion_rules[require_content_opened]" value="1" @checked((bool)($rules['require_content_opened'] ?? false))> يجب فتح محتوى الدرس</span></label>
            <input type="hidden" name="completion_rules[require_practice_passed]" value="0"><label><span><input type="checkbox" name="completion_rules[require_practice_passed]" value="1" @checked((bool)($rules['require_practice_passed'] ?? false))> يجب اجتياز تدريب الدرس</span></label>
            <label>أقل مدة مشاهدة بالثواني<input type="number" min="0" max="86400" name="completion_rules[minimum_video_seconds]" value="{{ $rules['minimum_video_seconds'] ?? 0 }}"></label>
            <label class="full">النص العربي — سطر لكل مقطع<textarea name="transcript_text">{{ old('transcript_text',implode("\n",$lesson->transcript ?? [])) }}</textarea></label>
            <label class="full" lang="en">English transcript — one segment per line<textarea dir="ltr" lang="en" name="transcript_en_text">{{ old('transcript_en_text',implode("\n",$lesson->transcript_en ?? [])) }}</textarea></label>
            <label class="full" lang="en">Timed captions<textarea dir="ltr" lang="en" name="captions_text" rows="5" placeholder="00:00:00.000 --> 00:00:04.000 | Welcome to this lesson.">@foreach($lesson->captions ?? [] as $caption){{ $caption['start'] }} --> {{ $caption['end'] }} | {{ $caption['text'] }}@if(!$loop->last){{ "\n" }}@endif @endforeach</textarea><small>One cue per line: start --&gt; end | caption text. Use HH:MM:SS.mmm.</small></label>
            <div class="full form-actions"><button class="button button-primary"><span aria-hidden="true">✓</span> حفظ الإكمال والنصوص</button></div>
        </form>
        @endif
    </div>
    @if($activeLessonTab === 'media')
    <section class="card stack lesson-editor-card">
        <div class="section-heading"><div><div class="eyebrow">الوسائط الخاصة</div><h2>الفيديو والملفات</h2></div></div>
        <p class="lesson-editor-hint">هذه المساحة للملفات فقط: فيديو الدرس، PDF محتوى الدرس، أو ملفات تعليمية مرفقة. التدريب والأسئلة لها تبويب مستقل.</p>
        <div class="detail-list">@forelse($lesson->mediaAssets as $asset)<div class="detail-item lesson-editor-row"><span class="status-dot"></span><div style="flex:1"><strong>{{ $asset->original_name }}</strong><small>{{ $asset->kind === 'video' ? 'فيديو خاص' : ($asset->kind === 'lesson_pdf' ? 'PDF محتوى الدرس' : 'ملف تعليمي') }} · {{ number_format($asset->size_bytes/1024,1) }} KB</small></div><form method="post" action="{{ route('admin.media.destroy',[$course,$lesson,$asset]) }}">@csrf @method('delete')<button class="button button-danger"><span aria-hidden="true">×</span> حذف</button></form></div>@empty<div class="empty-state">لا توجد ملفات مرفوعة.</div>@endforelse</div>
        <hr class="divider">
        <form class="form-grid lesson-editor-form" method="post" enctype="multipart/form-data" action="{{ route('admin.media.store',[$course,$lesson]) }}">@csrf<label>نوع الملف<select name="kind"><option value="video">فيديو الدرس</option><option value="lesson_pdf">PDF محتوى الدرس</option><option value="resource">ملف تعليمي</option></select></label><label>اختر الملف<input type="file" name="file" required><small>الحد التطبيقي 512MB. في التطوير المحلي شغّل الخادم عبر composer run serve حتى يستخدم PHP حدود الرفع الصحيحة.</small></label><div class="full form-actions"><button class="button button-secondary"><span aria-hidden="true">↑</span> رفع الملف</button></div></form>
    </section>
    @endif
    @if($activeLessonTab === 'questions')
    <section class="card stack lesson-editor-card">
        <div class="section-heading"><div><div class="eyebrow">تدريب الدرس</div><h2>اختبارات مرتبطة بهذا الدرس</h2><p>اربط الدرس باختبار محفوظ من بنك الأسئلة. الاختبار التدريبي للممارسة، والاختبار الإلزامي يجب على الطالب اجتيازه.</p></div></div>
        <div class="detail-list">@forelse($lesson->trainings as $training)@php($trainingQuestionCount = $training->questionSet()->count())<div class="detail-item lesson-editor-row"><span class="status-dot"></span><div style="flex:1"><strong>{{ $training->title_ar }}</strong><small>{{ $training->quiz ? $training->quiz->name_ar.' · ' : '' }}{{ $trainingQuestionCount }} أسئلة · {{ $training->is_required ? 'إلزامي' : 'تدريبي' }} · {{ $training->status }}</small></div><div class="lesson-training-actions">@if($trainingQuestionCount > 0)<form method="post" action="{{ route('admin.learning-paths.state',['training',$training->id]) }}">@csrf<input type="hidden" name="status" value="{{ $training->status === 'published' ? 'draft' : 'published' }}"><button class="button button-secondary"><span aria-hidden="true">{{ $training->status === 'published' ? '○' : '✓' }}</span> {{ $training->status === 'published' ? 'إلغاء النشر' : 'نشر' }}</button></form>@else<span class="lesson-training-empty-action"><span aria-hidden="true">!</span> أضف أسئلة أولاً</span>@endif<form method="post" action="{{ route('admin.learning-paths.trainings.destroy',$training) }}" data-confirm="سيتم حذف ارتباط هذا الاختبار من الدرس فقط. سيبقى الاختبار محفوظاً في بنك الأسئلة.">@csrf @method('delete')<button class="button button-danger"><span aria-hidden="true">×</span> حذف الارتباط</button></form></div></div>@empty<div class="empty-state">لا يوجد اختبار مرتبط بهذا الدرس بعد.</div>@endforelse</div>
        <hr class="divider">
        <form class="lesson-quiz-assign-form" method="post" action="{{ route('admin.learning-paths.trainings.store',['lesson',$lesson->id]) }}">@csrf<div class="lesson-quiz-select-card"><span class="lesson-quiz-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 11l2 2 4-5M5 4h14v16H5zM8 18h8"/></svg></span><label>اختيار من بنك الأسئلة<select name="question_bank_quiz_id" required><option value="">اختر اختبارًا محفوظًا</option>@foreach($questionQuizzes as $quiz)<option value="{{ $quiz->id }}">{{ $quiz->name_ar }} — {{ $quiz->type === 'required' ? 'إلزامي' : 'تدريبي' }} · {{ $quiz->questions_count }} أسئلة</option>@endforeach</select><small>اربط اختبارًا محفوظًا بالدرس. يمكنك إدارة محتوى الاختبار من بنك الأسئلة.</small></label></div><div class="lesson-quiz-options"><label>اسم مخصص داخل الدرس<input name="title_ar" placeholder="اتركه فارغًا لاستخدام اسم الاختبار"></label><label lang="en">English title<input dir="ltr" lang="en" name="title_en" placeholder="Optional lesson label"></label><label>درجة النجاح<input type="number" name="passing_score" min="1" max="100" placeholder="افتراضيًا من الاختبار"></label></div><div class="lesson-quiz-form-actions"><a class="button button-quiet" href="{{ route('admin.question-bank.index') }}#quiz-builder"><span aria-hidden="true">▦</span> إدارة الاختبارات</a><button class="button button-secondary"><span aria-hidden="true">＋</span> ربط الاختبار بالدرس</button></div></form>
    </section>
    @endif
</section>
@endif
@php($sectionLabels = [
    'heading' => 'عنوان', 'paragraph' => 'فقرة', 'introduction' => 'مقدمة', 'objectives' => 'أهداف تعلم',
    'key_terms' => 'مصطلحات', 'definition' => 'تعريف', 'explanation' => 'شرح', 'important_note' => 'ملاحظة مهمة',
    'common_mistake' => 'خطأ شائع', 'example' => 'مثال', 'worked_example' => 'مثال محلول', 'comparison' => 'مقارنة', 'formula' => 'صيغة رياضية',
    'table' => 'جدول', 'image' => 'صورة', 'code' => 'كود', 'activity' => 'نشاط', 'question' => 'سؤال',
    'question_group' => 'مجموعة أسئلة', 'summary' => 'ملخص', 'challenge' => 'تحدي', 'answer_key' => 'إجابات',
    'page_break' => 'فاصل صفحة',
])
@php($sectionGroups = [
    'المحتوى' => ['heading', 'paragraph', 'introduction', 'objectives', 'key_terms', 'definition', 'explanation', 'summary'],
    'التدريس' => ['important_note', 'common_mistake', 'example', 'worked_example', 'comparison', 'formula', 'activity', 'challenge'],
    'التقييم' => ['question', 'question_group', 'answer_key'],
    'الوسائط والتنسيق' => ['table', 'image', 'code', 'page_break'],
])
@if($activeLessonTab === 'content')
<section class="card stack" style="margin-top:1rem">
    <div class="section-heading">
        <div>
            <div class="eyebrow">Lesson Content</div>
            <h2>محتوى الدرس المنظم</h2>
            <p>ابنِ الدرس ككتل تعليمية قابلة للعرض في صفحة الطالب والكراسة المطبوعة من نفس المصدر.</p>
        </div>
    </div>
    <form class="form-grid" method="post" action="{{ route('admin.lesson-sections.store',[$course,$lesson]) }}">
        @csrf
        <label>نوع الكتلة
            <select name="type" required>
                @foreach($sectionGroups as $group => $types)
                    <optgroup label="{{ $group }}">
                        @foreach($types as $type)<option value="{{ $type }}">{{ $sectionLabels[$type] }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </label>
        <label>عنوان اختياري<input name="title" placeholder="مثال: تحقق من فهمك"></label>
        <label>لغة الكود اختياري<input dir="ltr" lang="en" name="language" placeholder="php, js, python"></label>
        <label class="full">الصيغة الرياضية<textarea dir="ltr" lang="en" name="formula" rows="2" placeholder="مثال: a² + b² = c² أو E = mc^2"></textarea><small>تُعرض كنص آمن. استخدم ^ للأسّ و _ للرمز السفلي.</small></label>
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
        <label>صورة من ملفات الدرس<select name="image_media_asset_id"><option value="">بدون صورة مرفوعة</option>@foreach($lesson->mediaAssets->filter(fn($asset) => str_starts_with((string)$asset->mime_type, 'image/')) as $asset)<option value="{{ $asset->id }}">{{ $asset->original_name }}</option>@endforeach</select></label>
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
        @forelse($lesson->sections as $section)
            @php($settings = $section->settingsWithDefaults())
            <details class="detail-item" open>
                <summary style="display:flex;gap:.75rem;align-items:center;cursor:pointer">
                    <span class="status-dot"></span>
                    <strong style="flex:1">{{ $section->sort_order }}. {{ $sectionLabels[$section->type] ?? $section->type }} @if($section->title) — {{ $section->title }} @endif</strong>
                    <small>{{ $settings['show_on_web'] ? 'ويب' : 'مخفي عن الويب' }} · {{ $settings['show_in_workbook'] ? 'كراسة' : 'خارج الكراسة' }}</small>
                </summary>
                <form class="form-grid" method="post" action="{{ route('admin.lesson-sections.update',[$course,$lesson,$section]) }}" style="margin-top:1rem">
                    @csrf @method('put')
                    <label>نوع الكتلة<select name="type" required>@foreach($sectionGroups as $group => $types)<optgroup label="{{ $group }}">@foreach($types as $type)<option value="{{ $type }}" @selected($section->type===$type)>{{ $sectionLabels[$type] }}</option>@endforeach</optgroup>@endforeach</select></label>
                    <label>العنوان<input name="title" value="{{ $section->title }}"></label>
                    <label>لغة الكود<input dir="ltr" lang="en" name="language" value="{{ data_get($section->content,'language') }}"></label>
                    <label class="full">الصيغة الرياضية<textarea dir="ltr" lang="en" name="formula" rows="2">{{ data_get($section->content,'formula') }}</textarea><small>تُعرض كنص آمن. استخدم ^ للأسّ و _ للرمز السفلي.</small></label>
                    <label class="full">المحتوى الرئيسي<textarea name="content_text" rows="5">{{ data_get($section->content,'text') }}</textarea></label>
                    <label class="full">عناصر متعددة<textarea name="items_text" rows="4">{{ implode("\n",(array)data_get($section->content,'items',[])) }}</textarea></label>
                    <div class="full section-heading"><div><div class="eyebrow">حقول متخصصة</div><h2>للأمثلة والأخطاء الشائعة والكود</h2></div></div>
                    <label>الخطأ الشائع<textarea name="mistake" rows="3">{{ data_get($section->content,'common_mistake.mistake') }}</textarea></label>
                    <label>الفهم الصحيح<textarea name="correction" rows="3">{{ data_get($section->content,'common_mistake.correction') }}</textarea></label>
                    <label>مسألة المثال المحلول<textarea name="worked_problem" rows="3">{{ data_get($section->content,'worked_example.problem') }}</textarea></label>
                    <label>طريقة التفكير<textarea name="worked_thinking" rows="3">{{ data_get($section->content,'worked_example.thinking') }}</textarea></label>
                    <label>الحل<textarea name="worked_solution" rows="3">{{ data_get($section->content,'worked_example.solution') }}</textarea></label>
                    <label>شرح الحل<textarea name="worked_explanation" rows="3">{{ data_get($section->content,'worked_example.explanation') }}</textarea></label>
                    <label class="full">الخلاصة من المثال<textarea name="worked_key_lesson" rows="2">{{ data_get($section->content,'worked_example.key_lesson') }}</textarea></label>
                    <label>مشكلة الكود<textarea name="code_problem" rows="3">{{ data_get($section->content,'code_example.problem') }}</textarea></label>
                    <label>منطق الحل<textarea name="code_logic" rows="3">{{ data_get($section->content,'code_example.logic') }}</textarea></label>
                    <label class="full">الكود<textarea dir="ltr" lang="en" name="code_body" rows="6">{{ data_get($section->content,'code_example.code') }}</textarea></label>
                    <label>شرح الكود<textarea name="code_explanation" rows="3">{{ data_get($section->content,'code_example.explanation') }}</textarea></label>
                    <label>المخرجات المتوقعة<textarea dir="ltr" lang="en" name="code_expected_output" rows="3">{{ data_get($section->content,'code_example.expected_output') }}</textarea></label>
                    <label>جرّب بنفسك<textarea name="code_try_it" rows="3">{{ data_get($section->content,'code_example.try_it') }}</textarea></label>
                    <div class="full section-heading"><div><div class="eyebrow">الأسئلة</div><h2>حقول التقييم المنظمة</h2></div></div>
                    <label>نوع السؤال<select name="question_type"><option value="short_answer" @selected(data_get($section->content,'question.type','short_answer')==='short_answer')>إجابة قصيرة</option><option value="multiple_choice" @selected(data_get($section->content,'question.type')==='multiple_choice')>اختيار من متعدد</option><option value="true_false" @selected(data_get($section->content,'question.type')==='true_false')>صح أو خطأ</option><option value="complete" @selected(data_get($section->content,'question.type')==='complete')>أكمل</option><option value="matching" @selected(data_get($section->content,'question.type')==='matching')>توصيل</option><option value="essay" @selected(data_get($section->content,'question.type')==='essay')>مقالي</option></select></label>
                    <label>مساحة الإجابة<select name="question_answer_space"><option value="medium" @selected(data_get($section->content,'question.answer_space','medium')==='medium')>متوسطة</option><option value="none" @selected(data_get($section->content,'question.answer_space')==='none')>بدون مساحة</option><option value="small" @selected(data_get($section->content,'question.answer_space')==='small')>صغيرة</option><option value="large" @selected(data_get($section->content,'question.answer_space')==='large')>كبيرة</option></select></label>
                    <label class="full">نص السؤال<textarea name="question_prompt" rows="3">{{ data_get($section->content,'question.prompt') }}</textarea></label>
                    <label class="full">اختيارات السؤال — سطر لكل اختيار<textarea name="question_options_text" rows="4">{{ implode("\n",(array)data_get($section->content,'question.options',[])) }}</textarea></label>
                    <label>الإجابة النموذجية<textarea name="question_answer" rows="3">{{ data_get($section->content,'question.answer') }}</textarea></label>
                    <label>شرح الإجابة<textarea name="question_explanation" rows="3">{{ data_get($section->content,'question.explanation') }}</textarea></label>
                    <label class="full">مجموعة أسئلة — سؤال في كل سطر<textarea name="question_group_text" rows="5">{{ collect((array)data_get($section->content,'question_group.questions',[]))->pluck('prompt')->implode("\n") }}</textarea></label>
                    <label>مساحة إجابات المجموعة<select name="question_group_answer_space"><option value="medium" @selected(data_get($section->content,'question_group.answer_space','medium')==='medium')>متوسطة</option><option value="none" @selected(data_get($section->content,'question_group.answer_space')==='none')>بدون مساحة</option><option value="small" @selected(data_get($section->content,'question_group.answer_space')==='small')>صغيرة</option><option value="large" @selected(data_get($section->content,'question_group.answer_space')==='large')>كبيرة</option></select></label>
                    <div class="full section-heading"><div><div class="eyebrow">الجداول والصور</div><h2>بيانات منظمة للطباعة والويب</h2></div></div>
                    <label class="full">صفوف الجدول — استخدم | بين الخلايا<textarea name="table_text" rows="4">{{ collect((array)data_get($section->content,'table.rows',[]))->map(fn($row) => implode(' | ', (array)$row))->implode("\n") }}</textarea></label>
                    <label>صورة من ملفات الدرس<select name="image_media_asset_id"><option value="">بدون صورة مرفوعة</option>@foreach($lesson->mediaAssets->filter(fn($asset) => str_starts_with((string)$asset->mime_type, 'image/')) as $asset)<option value="{{ $asset->id }}" @selected((int)data_get($section->content,'image.media_asset_id')===$asset->id)>{{ $asset->original_name }}</option>@endforeach</select></label>
                    <label>رابط صورة خارجي<input dir="ltr" name="image_url" value="{{ data_get($section->content,'image.url') }}"></label>
                    <label>النص البديل للصورة<input name="image_alt" value="{{ data_get($section->content,'image.alt') }}"></label>
                    <label>تعليق الصورة<input name="image_caption" value="{{ data_get($section->content,'image.caption') }}"></label>
                    <label>محاذاة الصورة<select name="image_alignment"><option value="center" @selected(data_get($section->content,'image.alignment','center')==='center')>وسط</option><option value="start" @selected(data_get($section->content,'image.alignment')==='start')>بداية</option><option value="end" @selected(data_get($section->content,'image.alignment')==='end')>نهاية</option></select></label>
                    <label>عرض الصورة في الطباعة<select name="image_print_width"><option value="medium" @selected(data_get($section->content,'image.print_width','medium')==='medium')>متوسط</option><option value="small" @selected(data_get($section->content,'image.print_width')==='small')>صغير</option><option value="large" @selected(data_get($section->content,'image.print_width')==='large')>كبير</option><option value="full" @selected(data_get($section->content,'image.print_width')==='full')>كامل العرض</option></select></label>
                    <input type="hidden" name="settings[show_on_web]" value="0"><label><span><input type="checkbox" name="settings[show_on_web]" value="1" @checked($settings['show_on_web'])> يظهر في صفحة الطالب</span></label>
                    <input type="hidden" name="settings[show_in_workbook]" value="0"><label><span><input type="checkbox" name="settings[show_in_workbook]" value="1" @checked($settings['show_in_workbook'])> يظهر في الكراسة</span></label>
                    <input type="hidden" name="settings[show_in_teacher_edition]" value="0"><label><span><input type="checkbox" name="settings[show_in_teacher_edition]" value="1" @checked($settings['show_in_teacher_edition'])> يظهر في نسخة المعلم</span></label>
                    <input type="hidden" name="settings[avoid_page_break_inside]" value="0"><label><span><input type="checkbox" name="settings[avoid_page_break_inside]" value="1" @checked($settings['avoid_page_break_inside'])> لا تقطع داخل الكتلة</span></label>
                    <input type="hidden" name="settings[page_break_before]" value="0"><label><span><input type="checkbox" name="settings[page_break_before]" value="1" @checked($settings['page_break_before'])> فاصل قبلها</span></label>
                    <input type="hidden" name="settings[page_break_after]" value="0"><label><span><input type="checkbox" name="settings[page_break_after]" value="1" @checked($settings['page_break_after'])> فاصل بعدها</span></label>
                    <div class="full form-actions"><button class="button button-secondary"><span aria-hidden="true">✓</span> حفظ الكتلة</button></div>
                </form>
                <div class="actions-inline" style="margin-top:.75rem">
                    <form method="post" action="{{ route('admin.lesson-sections.move',[$course,$lesson,$section,'up']) }}">@csrf<button class="button button-quiet"><span aria-hidden="true">↑</span> أعلى</button></form>
                    <form method="post" action="{{ route('admin.lesson-sections.move',[$course,$lesson,$section,'down']) }}">@csrf<button class="button button-quiet"><span aria-hidden="true">↓</span> أسفل</button></form>
                    <form method="post" action="{{ route('admin.lesson-sections.duplicate',[$course,$lesson,$section]) }}">@csrf<button class="button button-quiet"><span aria-hidden="true">⧉</span> نسخ</button></form>
                    <form method="post" action="{{ route('admin.lesson-sections.destroy',[$course,$lesson,$section]) }}">@csrf @method('delete')<button class="button button-danger"><span aria-hidden="true">×</span> حذف</button></form>
                </div>
            </details>
        @empty
            <div class="empty-state">لم تتم إضافة محتوى منظم بعد. ابدأ بكتلة مقدمة أو أهداف تعلم.</div>
        @endforelse
    </div>
</section>
@endif
<section class="card stack lesson-revisions-card">
    <div class="section-heading"><div><div class="eyebrow">Revision history</div><h2>سجل نسخ الدرس</h2><p>يتم حفظ نسخة قبل كل تحديث ويمكن استعادتها بأمان.</p></div></div>
    <div class="detail-list">
        @forelse($lesson->revisions->take(8) as $revision)
            <div class="detail-item lesson-editor-row">
                <div style="flex:1"><strong>{{ $revision->created_at?->format('Y-m-d H:i') }}</strong><small>{{ $revision->reason === 'before_restore' ? 'قبل الاستعادة' : 'قبل التحديث' }} · {{ $revision->user?->name ?? 'عضو فريق سابق' }}</small></div>
                <form method="post" action="{{ route('admin.lessons.revisions.restore',[$course,$lesson,$revision]) }}" data-confirm="سيتم حفظ النسخة الحالية ثم استعادة هذه النسخة. هل تريد المتابعة؟">@csrf<button class="button button-secondary"><span aria-hidden="true">↶</span> استعادة</button></form>
            </div>
        @empty
            <div class="empty-state">لا توجد نسخ محفوظة حتى الآن.</div>
        @endforelse
    </div>
</section>
@push('scripts')
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
<script>
(() => {
    const form = document.querySelector('[data-autosave-key]');
    if (!form) return;
    const key = form.dataset.autosaveKey;
    const fields = [...form.querySelectorAll('input, select, textarea')].filter((field) => field.name && field.type !== 'hidden' && field.type !== 'file');
    const read = () => Object.fromEntries(fields.map((field) => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
    const write = (values) => fields.forEach((field) => { if (!(field.name in values)) return; if (field.type === 'checkbox') field.checked = Boolean(values[field.name]); else field.value = values[field.name]; });
    const saved = localStorage.getItem(key);
    if (saved) {
        try { const values = JSON.parse(saved); if (window.confirm('تم العثور على مسودة محلية غير محفوظة. هل تريد استعادتها؟')) write(values); else localStorage.removeItem(key); } catch { localStorage.removeItem(key); }
    }
    let timer;
    const queueSave = () => { clearTimeout(timer); timer = setTimeout(() => localStorage.setItem(key, JSON.stringify(read())), 1000); };
    fields.forEach((field) => { field.addEventListener('input', queueSave); field.addEventListener('change', queueSave); });
    form.addEventListener('submit', () => localStorage.removeItem(key));
})();
</script>
@endpush
@endsection
