@extends('admin.layout')
@section('title', 'دعم ومتابعة الطلاب')
@section('content')
<header class="page-header community-page-hero support-page-hero">
    <div><div class="eyebrow">تدخل مبكر قائم على البيانات</div><h1>دعم ومتابعة الطلاب</h1><p>حوّل إشارات التعثر إلى خطوات متابعة واضحة وقابلة للقياس.</p></div>
    <form method="post" action="{{ route('admin.support.scan') }}">@csrf<button class="button button-primary"><span aria-hidden="true">⌁</span> فحص المخاطر الآن</button></form>
</header>

<section class="community-stats support-page-stats" aria-label="ملخص حالات المتابعة">
    <a href="{{ route('admin.support.index',['status'=>'open']) }}"><span class="community-stat-icon">○</span><div><strong>{{ $counts['open'] }}</strong><small>حالات مفتوحة</small></div><em>تحتاج قراراً</em></a>
    <a href="{{ route('admin.support.index',['risk'=>'high']) }}" class="{{ $counts['high'] ? 'attention' : '' }}"><span class="community-stat-icon">!</span><div><strong>{{ $counts['high'] }}</strong><small>مخاطر مرتفعة</small></div><em>أولوية المتابعة</em></a>
    <article><span class="community-stat-icon">✓</span><div><strong>{{ $counts['tasks'] }}</strong><small>مهام معلقة</small></div><em>بانتظار الإكمال</em></article>
</section>

<section class="grid content-columns support-layout community-workspace">
    <main class="stack">
        <div class="community-controlbar">
            <nav class="filter-pills community-filter-pills" aria-label="تصفية حالات المتابعة">
                @foreach([''=>'الكل','open'=>'مفتوحة','monitoring'=>'تحت المتابعة','resolved'=>'مغلقة'] as $value=>$label)<a class="{{ request('status','')===$value?'active':'' }}" href="{{ route('admin.support.index',array_filter(['status'=>$value,'risk'=>request('risk')])) }}">{{ $label }}</a>@endforeach
                <a class="priority-filter {{ request('risk')==='high'?'active':'' }}" href="{{ route('admin.support.index',['risk'=>'high']) }}"><span aria-hidden="true">!</span> مرتفعة فقط</a>
            </nav>
            <div class="community-results"><strong>{{ $cases->total() }} حالة</strong><span>صفحة {{ $cases->currentPage() }} من {{ $cases->lastPage() }}</span></div>
        </div>

        <div class="support-case-grid">
            @forelse($cases as $case)
            <a class="card support-case support-case-{{ $case->risk_level }}" href="{{ route('admin.support.show',$case) }}">
                <header class="support-case-head"><span class="risk-score {{ $case->risk_level }}">{{ $case->risk_score }}</span><div><strong>{{ $case->student->name }}</strong><small class="latin">{{ $case->student->email }}</small></div><span class="badge {{ $case->status==='resolved'?'published':($case->risk_level==='high'?'inactive':'draft') }}">{{ ['open'=>'مفتوحة','monitoring'=>'متابعة','resolved'=>'مغلقة'][$case->status] }}</span></header>
                <div class="risk-signals">@foreach($case->displayRiskSignals() as $signal)<span>{{ $signal['label'] }}</span>@endforeach</div>
                <div class="support-case-meta"><span><small>المسؤول</small><strong>{{ $case->assignee?->name ?? 'غير مسندة' }}</strong></span><span><small>المتابعة القادمة</small><strong>{{ $case->next_follow_up_at?->format('Y/m/d H:i') ?? 'غير محددة' }}</strong></span></div>
                <footer><span>{{ $case->pending_tasks_count }} مهام معلقة</span><span>{{ $case->activities_count }} أنشطة</span><b aria-hidden="true">←</b></footer>
            </a>
            @empty<div class="card empty-state community-empty"><span class="empty-state-icon" aria-hidden="true">✓</span><strong>لا توجد حالات مطابقة</strong><span>لا توجد إشارات متابعة ضمن الفلتر الحالي.</span></div>@endforelse
        </div>
        <div class="pagination">{{ $cases->links() }}</div>
    </main>

    <aside id="create-support-case"><details class="card community-side-card support-create" @if($errors->any()) open @endif><summary><span><small>تدخل يدوي</small><strong>فتح حالة متابعة</strong></span><b aria-hidden="true">＋</b></summary><p>استخدمها عندما تلاحظ مشكلة لا تظهر في مؤشرات النظام.</p><form class="stack" method="post" action="{{ route('admin.support.store') }}">@csrf<label>الطالب<select name="student_id" required><option value="">اختر الطالب</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_id')==$student->id)>{{ $student->name }} · {{ $student->email }}</option>@endforeach</select></label><label>درجة الخطورة<select name="risk_level"><option value="medium" @selected(old('risk_level')==='medium')>متوسطة</option><option value="high" @selected(old('risk_level')==='high')>مرتفعة</option></select></label><label>سبب المتابعة<textarea name="reason" required minlength="10" maxlength="2000" placeholder="اكتب ملاحظة واضحة تساعد فريق المتابعة...">{{ old('reason') }}</textarea></label><div class="community-form-note"><span aria-hidden="true">i</span><small>ستظهر الحالة مباشرة في قائمة الأولويات.</small></div><button class="button button-primary">فتح الحالة</button></form></details></aside>
</section>
@endsection
