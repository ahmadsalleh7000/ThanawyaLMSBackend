@extends('admin.layout')
@section('title', 'الاختبارات والامتحانات')
@section('content')
@php
    $typeLabels = ['assessment' => 'امتحان دورة', 'training' => 'اختبار مرتبط', 'template' => 'قالب محفوظ'];
    $typeTitles = ['assessment' => 'امتحانات الدورات', 'training' => 'الاختبارات المرتبطة', 'template' => 'القوالب المحفوظة'];
@endphp
<div class="exam-hub">
    <header class="page-header question-bank-hero">
        <div><div class="eyebrow">مركز التقييم</div><h1>الاختبارات والامتحانات</h1><p>كل امتحانات الدورات والاختبارات المرتبطة والقوالب المحفوظة في مكان واحد.</p></div>
        <div class="actions-inline"><a class="button button-secondary" href="{{ route('admin.question-bank.index') }}">بنك الأسئلة</a><a class="button button-primary" href="{{ route('admin.question-bank.index', ['tab' => 'quizzes']) }}#create-quiz">إنشاء اختبار</a></div>
    </header>

    <nav class="course-catalog-stats" aria-label="أنواع الاختبارات">
        <a href="{{ route('admin.exams.index', request()->except(['type', 'page'])) }}" @if(empty($filters['type'])) class="selected" aria-current="page" @endif><span aria-hidden="true">▦</span><div><strong>{{ array_sum($stats) }}</strong><small>كل الاختبارات</small></div></a>
        @foreach($typeTitles as $kind => $label)
            <a href="{{ route('admin.exams.index', array_merge(request()->except('page'), ['type' => $kind])) }}" @if(($filters['type'] ?? '') === $kind) class="selected" aria-current="page" @endif><span aria-hidden="true">{{ $kind === 'assessment' ? '✓' : ($kind === 'training' ? '?' : '▤') }}</span><div><strong>{{ $stats[$kind] }}</strong><small>{{ $label }}</small></div></a>
        @endforeach
    </nav>

    <form class="card exam-hub-filters" method="get" role="search" aria-label="البحث في الاختبارات">
        <label>البحث<input type="search" name="search" value="{{ $search }}" maxlength="150" placeholder="اسم الاختبار أو الدورة…"></label>
        <label>نوع الاختبار<select name="type"><option value="">كل الأنواع</option>@foreach($typeTitles as $kind => $label)<option value="{{ $kind }}" @selected(($filters['type'] ?? '') === $kind)>{{ $label }}</option>@endforeach</select></label>
        <label>الحالة<select name="status"><option value="">كل الحالات</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>منشور / نشط</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>مسودة / غير نشط</option><option value="archived" @selected(($filters['status'] ?? '') === 'archived')>مؤرشف</option></select></label>
        <label>الدورة<select name="course_id"><option value="">كل الدورات</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(($filters['course_id'] ?? '') === $course->id)>{{ $course->title }}</option>@endforeach</select></label>
        <div class="actions-inline"><button class="button button-primary">بحث وتصفية</button>@if(request()->hasAny(['search','type','status','course_id']))<a class="button button-quiet" href="{{ route('admin.exams.index') }}">مسح الفلاتر</a>@endif</div>
    </form>

    <div class="exam-hub-results-heading"><strong>{{ $records->total() }} نتيجة</strong><span>افتح الإعدادات للتحكم في المحاولات والأسئلة والمراجعة.</span></div>
    <section class="card table-wrap exam-hub-list" aria-label="قائمة الاختبارات والامتحانات">
        <table class="exam-hub-table">
            <caption class="sr-only">الاختبارات والامتحانات وإعداداتها ونتائجها</caption>
            <thead><tr><th scope="col">الاختبار</th><th scope="col">الأسئلة</th><th scope="col">النجاح والإعادة</th><th scope="col">الحالة والإتاحة</th><th scope="col">المتابعة</th><th scope="col">الإدارة</th></tr></thead>
            <tbody>
            @forelse($records as $item)
                @php
                    $isTemplate = $item['kind'] === 'template';
                    $isPublished = $item['status'] === 'published';
                    $stateLabel = $isTemplate ? ($isPublished ? 'نشط' : 'غير نشط') : ($isPublished ? 'منشور' : ($item['status'] === 'archived' ? 'مؤرشف' : 'مسودة'));
                    $actionLabel = $isTemplate ? ($isPublished ? 'تعطيل القالب' : 'تفعيل القالب') : ($isPublished ? 'إلغاء النشر' : ($item['status'] === 'archived' ? 'استعادة كمسودة' : 'نشر'));
                @endphp
                <tr data-exam-kind="{{ $item['kind'] }}" data-exam-id="{{ $item['id'] }}">
                    <td class="exam-hub-identity">
                        <span class="exam-hub-kind">{{ $typeLabels[$item['kind']] }}</span>
                        <a class="exam-hub-title" href="{{ $item['settingsUrl'] }}">{{ $item['title'] }}</a>
                        @if($item['titleEn'])<small lang="en" dir="ltr">{{ $item['titleEn'] }}</small>@endif
                        @if($item['course'] && $item['kind'] !== 'assessment')<a class="exam-hub-context" href="{{ route('admin.courses.edit', $item['course']) }}">{{ $item['course']->title }}</a>@endif
                        @if($item['context'])<small>{{ $item['context'] }}</small>@endif
                        @if($isTemplate)<small>قالب لإعادة الاستخدام؛ إعدادات المحاولات تخص كل ارتباط.</small>@endif
                    </td>
                    <td data-label="الأسئلة"><strong>{{ $item['activeQuestions'] }}</strong><small>مفعّلة من {{ $item['totalQuestions'] }}</small>@if($item['questionLimit'])<small>{{ min($item['questionLimit'], $item['activeQuestions']) }} لكل طالب</small>@endif</td>
                    <td data-label="النجاح والإعادة"><strong>{{ $item['passingScore'] }}% للنجاح</strong><small>{{ $isTemplate ? 'الإعادة تُضبط عند الربط' : ($item['maxRetries'] === null ? 'إعادة غير محدودة' : ($item['maxRetries'] === 0 ? 'محاولة واحدة فقط' : ($item['maxRetries'] === 1 ? 'إعادة واحدة' : $item['maxRetries'].' مرات إعادة'))) }}</small></td>
                    <td data-label="الحالة والإتاحة"><span class="badge {{ $isPublished ? 'published' : ($item['status'] === 'archived' ? 'inactive' : 'draft') }}">{{ $stateLabel }}</span>
                        @if($item['availableFrom']?->isFuture())<small>يبدأ <time datetime="{{ $item['availableFrom']->toISOString() }}">{{ $item['availableFrom']->format('Y/m/d H:i') }}</time></small>@endif
                        @if($item['deadline'])<small>{{ $item['deadline']->isPast() ? 'انتهى في' : 'متاح حتى' }} <time datetime="{{ $item['deadline']->toISOString() }}">{{ $item['deadline']->format('Y/m/d H:i') }}</time></small>@endif
                    </td>
                    <td data-label="المتابعة">@if($isTemplate)<strong>{{ $item['uses'] }}</strong><small>ارتباطات بهذا القالب</small>@else<a href="{{ $item['resultsUrl'] }}">{{ $item['attempts'] }} محاولة</a>@endif</td>
                    <td data-label="الإدارة"><div class="exam-hub-actions">
                        <a class="button button-secondary" href="{{ $item['settingsUrl'] }}">{{ $isTemplate ? 'الأسئلة والقالب' : 'إعدادات الاختبار' }}</a>
                        @if($item['resultsUrl'])<a class="button button-quiet" href="{{ $item['resultsUrl'] }}">نتائج الطلاب</a>@endif
                        <form method="post" action="{{ $item['stateUrl'] }}">@csrf
                            @if($item['kind'] === 'training')<input type="hidden" name="status" value="{{ $isPublished || $item['status'] === 'archived' ? 'draft' : 'published' }}">@endif
                            <button class="button button-quiet" aria-label="{{ $actionLabel }}: {{ $item['title'] }}">{{ $actionLabel }}</button>
                        </form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>لا توجد اختبارات مطابقة</strong><span>غيّر الفلاتر أو أنشئ قالب اختبار من بنك الأسئلة.</span><a class="button button-secondary" href="{{ route('admin.exams.index') }}">عرض كل الاختبارات</a></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <div class="pagination">{{ $records->links() }}</div>
</div>
@endsection
