@extends('admin.layout')
@section('title', 'الدورات')
@section('content')
<header class="page-header course-catalog-hero">
    <div class="course-catalog-hero-copy"><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16M8 7h8"/></svg></span><div><div class="eyebrow">استوديو المحتوى</div><h1>الدورات التعليمية</h1><p>راقب الجاهزية، نظّم المحتوى، وانشر تجربة تعليمية مكتملة.</p></div></div>
    <a class="button button-primary" href="{{ route('admin.courses.create') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> إنشاء دورة</a>
</header>

<section class="course-catalog-stats" aria-label="ملخص الدورات">
    <a href="{{ route('admin.courses.index') }}" class="{{ request('status') ? '' : 'selected' }}"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5zM4 5.5v16"/></svg></span><div><strong>{{ $stats['total'] }}</strong><small>كل الدورات</small></div></a>
    <a href="{{ route('admin.courses.index',['status'=>'published']) }}" class="{{ request('status')==='published' ? 'selected' : '' }}"><span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></span><div><strong>{{ $stats['published'] }}</strong><small>منشورة</small></div></a>
    <a href="{{ route('admin.courses.index',['status'=>'draft']) }}" class="{{ request('status')==='draft' ? 'selected' : '' }}"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 8h6M9 12h6"/></svg></span><div><strong>{{ $stats['drafts'] }}</strong><small>مسودات</small></div></a>
    <a href="{{ route('admin.courses.index',['status'=>'archived']) }}" class="{{ request('status')==='archived' ? 'selected' : '' }}"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM3 3h18v4H3zM9 11h6"/></svg></span><div><strong>{{ $stats['archived'] }}</strong><small>مؤرشفة</small></div></a>
</section>

@php($advancedFilterCount = collect(['scope','year','grade','semester','category'])->filter(fn ($key) => filled(request($key)))->count())
<form class="card course-catalog-filter course-catalog-filter-refined" method="get" role="search">
    <input type="hidden" name="view" value="{{ $display }}">
    <label class="search-field course-filter-search"><span>البحث</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="{{ $search }}" placeholder="اسم الدورة أو المعرّف..."></label>
    <label class="course-filter-field"><span>الحالة</span><select name="status"><option value="">كل الحالات</option><option value="published" @selected(request('status')==='published')>منشورة</option><option value="draft" @selected(request('status')==='draft')>مسودة</option><option value="archived" @selected(request('status')==='archived')>مؤرشفة</option></select></label>
    <label class="course-filter-field"><span>الترتيب</span><select name="sort"><option value="latest" @selected(request('sort','latest')==='latest')>الأحدث</option><option value="title" @selected(request('sort')==='title')>اسم الدورة</option><option value="content" @selected(request('sort')==='content')>الأكثر محتوى</option><option value="status" @selected(request('sort')==='status')>الحالة</option><option value="enrollments" @selected(request('sort')==='enrollments')>الأكثر تسجيلاً</option></select></label>
    <details class="course-advanced-filters"><summary><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>فلاتر إضافية</span>@if($advancedFilterCount)<b>{{ $advancedFilterCount }}</b>@endif</summary><div>
        <label><span>نوع الدورة</span><select name="scope"><option value="">كل الأنواع</option><option value="academic" @selected(request('scope')==='academic')>أكاديمية</option><option value="general" @selected(request('scope')==='general')>عامة</option></select></label>
        <label><span>العام الدراسي</span><select name="year"><option value="">كل الأعوام</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected((int) request('year') === $year->id)>{{ $year->name_ar }}</option>@endforeach</select></label>
        <label><span>الصف</span><select name="grade"><option value="">كل الصفوف</option>@foreach(['grade-1'=>'الأول الثانوي','grade-2'=>'الثاني الثانوي','grade-3'=>'الثالث الثانوي'] as $value=>$label)<option value="{{ $value }}" @selected(request('grade')===$value)>{{ $label }}</option>@endforeach</select></label>
        <label><span>الفصل الدراسي</span><select name="semester"><option value="">كل الفصول</option><option value="semester-1" @selected(request('semester')==='semester-1')>الترم الأول</option><option value="semester-2" @selected(request('semester')==='semester-2')>الترم الثاني</option></select></label>
        <label><span>التصنيف</span><select name="category"><option value="">كل التصنيفات</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label>
    </div></details>
    <div class="course-filter-actions"><button class="button button-primary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg> تطبيق الفلاتر</button>@if(request()->hasAny(['search','status','scope','year','grade','semester','category','sort']))<a class="button button-quiet" href="{{ route('admin.courses.index',['view'=>$display]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg> مسح</a>@endif</div>
</form>

<div class="course-results-head"><div><strong>{{ $courses->total() }} دورة</strong><span>اختر دورة للانتقال إلى وحداتها ثم دروسها ومحتواها.</span></div><div class="listing-head-tools"><span>صفحة {{ $courses->currentPage() }} من {{ $courses->lastPage() }}</span><x-admin.view-switcher :display="$display" /></div></div>

@if($display === 'table')
@include('admin.courses.table')
@else
<section class="course-admin-grid" aria-label="قائمة الدورات">
    @forelse($courses as $course)
    @php($status = $course->archived_at ? 'archived' : ($course->is_published ? 'published' : 'draft'))
    <article class="card course-admin-card course-status-{{ $status }}">
        <header class="course-admin-cover">
            <div class="course-cover-code" aria-hidden="true">&lt;/&gt;</div>
            <div class="course-cover-top"><span>{{ $course->category }}</span><span class="badge {{ $status }}">{{ $status === 'archived' ? 'مؤرشفة' : ($status === 'published' ? 'منشورة' : 'مسودة') }}</span></div>
            <div class="course-cover-title"><small class="latin">{{ $course->id }}</small><strong>{{ $course->title }}</strong><span lang="en" dir="ltr">{{ $course->title_en }}</span></div>
        </header>
        <div class="course-admin-body">
            <div class="course-admin-meta"><span>{{ $course->isGeneral() ? 'دورة عامة' : 'دورة أكاديمية' }}</span>@if($course->isGeneral())<span>كل الطلاب</span>@else<span>{{ ['grade-1'=>'أولى ثانوي','grade-2'=>'تانية ثانوي','grade-3'=>'تالتة ثانوي'][$course->grade] ?? $course->grade }}</span><span>{{ $course->semester === 'semester-1' ? 'الترم الأول' : 'الترم الثاني' }}</span>@endif<span>{{ $course->level }}</span></div>
            <div class="course-admin-numbers"><div><strong>{{ $course->units_count }}</strong><small>وحدة</small></div><div><strong>{{ $course->lessons_count }}</strong><small>درس</small></div><div><strong>{{ $course->resources_count }}</strong><small>مورد</small></div><div><strong>{{ $course->content_trainings_count }}</strong><small>تدريب</small></div></div>
            <div class="course-readiness-inline">
                <div><span>جاهزية المحتوى</span><strong>{{ $course->list_readiness_score }}%</strong></div>
                <div class="progress-track" role="progressbar" aria-label="جاهزية {{ $course->title }}" aria-valuenow="{{ $course->list_readiness_score }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $course->list_readiness_score }}%"></span></div>
                <small>{{ $course->list_readiness_score === 100 ? 'المتطلبات الأساسية مكتملة' : 'راجع الفحص قبل النشر' }}</small>
            </div>
        </div>
        <footer class="course-admin-footer">
            <div class="course-main-actions"><a class="button button-primary" href="{{ route('admin.learning-paths.courses.units',$course) }}">إدارة الوحدات</a><a class="button button-secondary" href="{{ route('admin.courses.edit',$course) }}">تحرير</a><a class="icon-action" href="{{ route('admin.courses.preview',$course) }}" aria-label="معاينة {{ $course->title }}" title="معاينة">◉</a></div>
            <details class="course-more"><summary aria-label="المزيد من إجراءات {{ $course->title }}">•••</summary><div>
                <form method="post" action="{{ route('admin.courses.state',[$course,$course->archived_at?'restore':($course->is_published?'unpublish':'publish')]) }}">@csrf<button>{{ $course->archived_at?'استعادة الدورة':($course->is_published?'إلغاء النشر':'نشر الدورة') }}</button></form>
                @unless($course->archived_at)<form method="post" action="{{ route('admin.courses.state',[$course,'archive']) }}">@csrf<button class="danger-text">أرشفة الدورة</button></form>@endunless
            </div></details>
        </footer>
    </article>
    @empty
    <div class="card empty-state course-catalog-empty"><strong>لا توجد دورات مطابقة</strong><span>غيّر الفلاتر أو أنشئ دورة تعليمية جديدة.</span><a class="button button-primary" href="{{ route('admin.courses.create') }}">إنشاء دورة</a></div>
    @endforelse
</section>
@endif
<div class="pagination">{{ $courses->links() }}</div>
@endsection
