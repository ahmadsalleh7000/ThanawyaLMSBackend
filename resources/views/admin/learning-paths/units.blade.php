@extends('admin.layout')
@section('title','إدارة الوحدات')
@section('content')
@php($term = $course->academicSubject?->term)
@php($courseContext = $course->isGeneral() ? 'دورة عامة · جميع الطلاب' : collect([$term?->academicYear?->name_ar, $term?->grade?->name_ar, $term?->name_ar])->filter()->join(' · '))
<nav class="management-breadcrumb" aria-label="مسار التنقل"><a href="{{ route('admin.courses.index') }}">الدورات</a><span>←</span><strong>{{ $course->title }}</strong><span>←</span><strong>الوحدات</strong></nav>
<header class="page-header management-page-header units-page-header">
    <div class="units-page-heading"><span class="units-page-kicker">إدارة الوحدات</span><h1>{{ $course->title }}</h1><div class="units-page-meta"><span>{{ $courseContext }}</span><span><b>{{ $course->units->count() }}</b> وحدات</span><span><b>{{ $course->units->sum('lessons_count') }}</b> دروس</span></div></div>
    <button type="button" class="button button-primary curriculum-create-trigger" data-inline-form-toggle="add-unit-panel" aria-controls="add-unit-panel" aria-expanded="{{ $errors->any() || session('continue_unit_creation') || old('slug') !== null || old('title_ar') !== null || old('title_en') !== null ? 'true' : 'false' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة وحدة</button>
</header>
<section class="curriculum-inline-create" id="add-unit-panel" aria-labelledby="add-unit-panel-title" @unless($errors->any() || session('continue_unit_creation') || old('slug') !== null || old('title_ar') !== null || old('title_en') !== null) hidden @endunless>
    <form class="curriculum-inline-create-form" method="post" action="{{ route('admin.learning-paths.units.store',$course) }}" data-processing data-processing-text="جارٍ إضافة الوحدة...">@csrf
        <div class="curriculum-inline-create-header"><div class="curriculum-dialog-title"><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z"/></svg></span><div><div class="eyebrow">{{ $course->title }}</div><h2 id="add-unit-panel-title">إضافة وحدة جديدة</h2><p>أنشئ الوحدة كمسودة، ثم أضف دروسها من صفحة الإدارة.</p></div></div><button class="curriculum-inline-close" type="button" data-inline-form-close="add-unit-panel" aria-label="إغلاق نموذج إضافة الوحدة">×</button></div>
        @if($errors->any())<div class="curriculum-create-errors" role="alert"><strong>راجع بيانات الوحدة</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="curriculum-create-fields">
            <label><span>العنوان العربي</span><input name="title_ar" value="{{ old('title_ar') }}" maxlength="150" autocomplete="off" placeholder="مثال: أساسيات البرمجة" required></label>
            <label lang="en"><span>English title</span><input name="title_en" dir="ltr" value="{{ old('title_en') }}" maxlength="150" autocomplete="off" placeholder="Programming Foundations" required></label>
            <label class="full"><span>المعرّف التقني</span><input name="slug" dir="ltr" value="{{ old('slug') }}" maxlength="80" pattern="[a-z0-9-]+" title="استخدم حروفاً إنجليزية صغيرة وأرقاماً وشرطات فقط" autocomplete="off" placeholder="programming-foundations" required><small>حروف إنجليزية صغيرة وأرقام وشرطات فقط. يجب أن يكون فريداً داخل الدورة.</small></label>
        </div>
        <div class="curriculum-inline-create-footer"><div class="curriculum-create-note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>ستُضاف الوحدة في نهاية الترتيب الحالي وبحالة مسودة.</span></div><div class="curriculum-inline-actions"><button type="button" class="button button-quiet" data-inline-form-close="add-unit-panel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg> إلغاء</button><button class="button button-secondary" type="submit" name="add_another" value="1"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6M12 19v-7M8 15l4 4 4-4"/></svg> حفظ وإضافة أخرى</button><button class="button button-primary" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة الوحدة</button></div></div>
    </form>
</section>
<section class="management-list curriculum-management-list" aria-label="وحدات الدورة">
@if($course->units->isNotEmpty())
    <div class="curriculum-list-header" aria-hidden="true"><span>الترتيب</span><span>الوحدة</span><span>المحتوى والحالة</span><span>الإجراءات</span></div>
@endif
@forelse($course->units as $unit)
    <article class="management-row curriculum-management-row unit-management-row">
        <span class="management-index">{{ $unit->position }}</span>
        <div class="management-copy"><strong>{{ $unit->title_ar }}</strong><small dir="ltr">{{ $unit->title_en }}</small></div>
        <div class="management-facts curriculum-facts"><span class="curriculum-fact"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h4"/></svg><span><b>{{ $unit->lessons_count }}</b> دروس</span></span><span class="badge unit-status-badge {{ $unit->status==='published'?'published':($unit->status==='archived'?'archived':'draft') }}"><i aria-hidden="true"></i>{{ $unit->status==='published'?'منشورة':($unit->status==='archived'?'مؤرشفة':'مسودة') }}</span></div>
        <div class="management-actions curriculum-row-actions"><a class="primary" href="{{ route('admin.learning-paths.units.lessons',[$course,$unit]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6M9 16h4"/></svg> إدارة الدروس</a><a href="{{ route('admin.learning-paths.units.edit',[$course,$unit]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.5-1 10-10-3.5-3.5-10 10zM13.5 6.5 17 10"/></svg> تحرير</a><details class="learning-actions-menu unit-actions-menu"><summary aria-label="إجراءات الوحدة"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg><span>المزيد</span></summary><div><form class="unit-state-action" method="post" action="{{ route('admin.learning-paths.state',['unit',$unit->id]) }}">@csrf<input type="hidden" name="status" value="{{ $unit->status==='published'?'draft':'published' }}"><button @disabled($unit->status !== 'published' && $unit->lessons_count === 0) @if($unit->status !== 'published' && $unit->lessons_count === 0) title="أضف درساً واحداً على الأقل قبل النشر" @endif>@if($unit->status==='published')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 5.2A10.6 10.6 0 0 1 12 5c6 0 9.5 7 9.5 7a15 15 0 0 1-2.1 2.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 7 9.5 7a9.7 9.7 0 0 0 3-.5"/></svg>@else<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5M5 14v6h14v-6"/></svg>@endif<span>{{ $unit->status==='published'?'إلغاء النشر':($unit->lessons_count === 0 ? 'أضف درساً للنشر' : 'نشر الوحدة') }}</span></button></form><div class="unit-order-actions" role="group" aria-label="ترتيب الوحدة"><form method="post" action="{{ route('admin.learning-paths.move',['unit',$unit->id,'up']) }}">@csrf<button @disabled($loop->first) @if($loop->first) title="الوحدة في أعلى الترتيب" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg><span>نقل لأعلى</span></button></form><form method="post" action="{{ route('admin.learning-paths.move',['unit',$unit->id,'down']) }}">@csrf<button @disabled($loop->last) @if($loop->last) title="الوحدة في أسفل الترتيب" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><span>نقل لأسفل</span></button></form></div></div></details></div>
    </article>
@empty
    <div class="empty-state academic-empty-state">
        <img class="academic-empty-illustration" src="{{ asset('images/admin/academic-empty-state.webp') }}" alt="" aria-hidden="true">
        <div><strong>لا توجد وحدات بعد</strong><span>أضف وحدة لبدء تنظيم الدروس.</span></div>
        <button type="button" class="button button-primary academic-empty-action" data-inline-form-toggle="add-unit-panel" aria-controls="add-unit-panel" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إضافة وحدة</button>
    </div>
@endforelse
</section>

@endsection
