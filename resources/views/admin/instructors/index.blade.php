@extends('admin.layout')
@section('title', 'إدارة المدرسين')
@section('content')
<header class="page-header instructor-page-hero">
    <div><div class="eyebrow">فريق التعليم</div><h1>إدارة المدرسين</h1><p>تابع الملفات المهنية، عبء الدورات، ومؤشرات الوصول والتقييم.</p></div>
    <a class="button button-primary" href="#create-instructor"><span aria-hidden="true">＋</span> إضافة مدرس</a>
</header>

<section class="instructor-page-stats" aria-label="ملخص المدرسين">
    <a href="{{ route('admin.instructors.index') }}"><span>◎</span><div><strong>{{ $stats['total'] }}</strong><small>كل المدرسين</small></div></a>
    <a href="{{ route('admin.instructors.index',['status'=>'active']) }}"><span>✓</span><div><strong>{{ $stats['active'] }}</strong><small>ملفات نشطة</small></div></a>
    <a href="{{ route('admin.instructors.index',['workload'=>'assigned']) }}"><span>▤</span><div><strong>{{ $stats['assigned'] }}</strong><small>مرتبطون بدورات</small></div></a>
    <a href="{{ route('admin.instructors.index',['workload'=>'unassigned']) }}" class="{{ $stats['unassigned'] ? 'attention' : '' }}"><span>○</span><div><strong>{{ $stats['unassigned'] }}</strong><small>دون دورات</small></div></a>
</section>

<form class="card instructor-filter" method="get" role="search">
    <input type="hidden" name="view" value="{{ $display }}">
    <label class="search-field"><span class="sr-only">البحث في المدرسين</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="{{ $search }}" placeholder="الاسم أو البريد..."></label>
    <select name="status" aria-label="حالة الملف"><option value="">كل الحالات</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>غير نشط</option></select>
    <select name="workload" aria-label="عبء الدورات"><option value="">كل أعباء العمل</option><option value="assigned" @selected(request('workload')==='assigned')>مرتبط بدورات</option><option value="unassigned" @selected(request('workload')==='unassigned')>دون دورات</option></select>
    <select name="profile" aria-label="اكتمال الملف"><option value="">كل الملفات</option><option value="complete" @selected(request('profile')==='complete')>ملف مكتمل</option><option value="incomplete" @selected(request('profile')==='incomplete')>يحتاج استكمال</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" @selected(request('sort','latest')==='latest')>الأحدث</option><option value="name" @selected(request('sort')==='name')>الاسم</option><option value="courses" @selected(request('sort')==='courses')>أكثر الدورات</option></select>
    <button class="button button-secondary">تطبيق</button>
    @if(request()->hasAny(['search','status','workload','profile','sort']))<a class="button button-quiet" href="{{ route('admin.instructors.index',['view'=>$display]) }}">مسح</a>@endif
</form>

<div class="instructor-workspace">
    <main>
        <div class="instructor-results-head"><div><strong>{{ $instructors->total() }} مدرساً</strong><span>ملفات مهنية وأداء الدورات المرتبطة</span></div><div class="listing-head-tools"><span>صفحة {{ $instructors->currentPage() }} من {{ $instructors->lastPage() }}</span><x-admin.view-switcher :display="$display" /></div></div>
        @if($display === 'table')
        @include('admin.instructors.table')
        @else
        <div class="instructor-grid">
        @forelse($instructors as $instructor)
            @php($enrollments=$instructor->courses->sum('enrollments_count'))
            @php($rating=$instructor->courses->whereNotNull('published_rating')->avg('published_rating') ?? 0)
            <article class="card instructor-card {{ $instructor->is_active ? '' : 'instructor-card-inactive' }}">
                <header class="instructor-card-head">
                    <span class="instructor-avatar">{{ mb_substr($instructor->name,0,1) }}</span>
                    <div><strong>{{ $instructor->name }}</strong><small lang="en" dir="ltr">{{ $instructor->name_en }}</small><span>{{ $instructor->headline ?: 'المسمى المهني غير مكتمل' }}</span></div>
                    <span class="badge {{ $instructor->is_active?'active':'inactive' }}">{{ $instructor->is_active?'نشط':'غير نشط' }}</span>
                </header>
                <div class="instructor-completion"><div><span>اكتمال الملف</span><strong>{{ $instructor->profile_completion }}%</strong></div><div class="progress-track" role="progressbar" aria-label="اكتمال ملف {{ $instructor->name }}" aria-valuenow="{{ $instructor->profile_completion }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $instructor->profile_completion }}%"></span></div></div>
                <div class="instructor-metrics"><span><strong>{{ $instructor->courses_count }}</strong><small>دورات</small></span><span><strong>{{ number_format($enrollments) }}</strong><small>طلاب مسجلون</small></span><span><strong>{{ $rating ? number_format($rating,1) : '—' }}</strong><small>متوسط التقييم</small></span></div>
                <div class="instructor-contact"><span aria-hidden="true">@</span><div><small>البريد المهني</small><strong class="latin">{{ $instructor->email ?: 'غير مضاف' }}</strong></div></div>
                <p>{{ $instructor->bio ?: 'أكمل النبذة العربية لعرض خبرة المدرس وتخصصه بوضوح.' }}</p>
                @if($instructor->courses_count)<div class="instructor-courses"><small>الدورات المرتبطة</small>@foreach($instructor->courses->take(3) as $course)<a href="{{ route('admin.courses.edit',$course) }}">{{ $course->title }}</a>@endforeach @if($instructor->courses_count>3)<span>+{{ $instructor->courses_count-3 }}</span>@endif</div>@else<div class="instructor-no-courses"><span>○</span> لم يُربط بدورة بعد</div>@endif
                <footer>
                    <details class="instructor-editor"><summary>تحرير الملف</summary><form class="form-grid instructor-edit" method="post" action="{{ route('admin.instructors.update',$instructor) }}">@csrf @method('put') @include('admin.instructors.fields',['item'=>$instructor])<button class="button button-primary full">حفظ التعديلات</button></form></details>
                    <form method="post" action="{{ route('admin.instructors.toggle',$instructor) }}">@csrf<button class="button {{ $instructor->is_active?'button-danger':'button-secondary' }}">{{ $instructor->is_active?'تعطيل':'تفعيل' }}</button></form>
                </footer>
            </article>
        @empty<div class="card empty-state instructor-empty"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف مدرساً جديداً.</span><a class="button button-primary" href="#create-instructor">إضافة مدرس</a></div>@endforelse
        </div>
        @endif
        <div class="pagination">{{ $instructors->links() }}</div>
    </main>

    <aside id="create-instructor"><details class="card instructor-create" @if($errors->any()) open @endif><summary><span><small>عضو جديد</small><strong>إنشاء ملف مدرس</strong></span><b aria-hidden="true">＋</b></summary><p>أكمل البيانات بالعربية والإنجليزية ليكون الملف جاهزاً للربط بالدورات.</p><form class="stack" method="post" action="{{ route('admin.instructors.store') }}">@csrf @include('admin.instructors.fields',['item'=>null])<button class="button button-primary">إنشاء الملف</button></form></details></aside>
</div>
@endsection
