@extends('admin.layout')
@section('title', 'ملف الطالب')
@section('content')
<div class="student-hero">
    <span class="student-avatar" aria-hidden="true">{{ mb_substr($student->name, 0, 1) }}</span>
    <div class="student-identity">
        <div class="eyebrow" style="color:#93c5fd">ملف المتعلم</div>
        <h1>{{ $student->name }}</h1>
        <p>{{ $student->email }}</p>
        <div class="student-meta"><span>{{ $student->is_active ? 'حساب نشط' : 'حساب معطل' }}</span><span>انضم {{ $student->created_at->diffForHumans() }}</span><span>{{ $parents->count() }} أولياء أمور مرتبطون</span></div>
    </div>
    <div class="student-hero-actions">
        <a class="button button-quiet" href="{{ route('admin.users.index') }}">العودة للحسابات</a>
        <form method="post" action="{{ route('admin.users.toggle', $student) }}">@csrf<button class="button {{ $student->is_active ? 'button-danger' : 'button-primary' }}">{{ $student->is_active ? 'تعطيل الحساب' : 'تفعيل الحساب' }}</button></form>
    </div>
</div>

<section class="grid student-stats" aria-label="ملخص تعلم الطالب">
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">◫</span><div><strong>{{ $summary['courses'] }}</strong><span>دورات مسجلة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">✓</span><div><strong>{{ $summary['completedLessons'] }}</strong><span>دروس مكتملة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">↗</span><div><strong>{{ $summary['averageProgress'] }}%</strong><span>متوسط التقدم</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">★</span><div><strong>{{ $summary['passedAssessments'] }}</strong><span>تقييمات ناجحة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon" aria-hidden="true">ج</span><div><strong>{{ number_format($summary['totalPaid']) }}</strong><span>مدفوعات مؤكدة ج.م</span></div></article>
</section>

<section class="grid content-columns">
    <div class="stack">
        <section class="card">
            <div class="section-heading"><div><div class="eyebrow">التقدم الدراسي</div><h2>الدورات الحالية</h2></div><span class="badge draft">{{ $courses->count() }} دورات</span></div>
            <div class="grid">
                @forelse($courses as $item)
                    <article class="course-progress-card list-row" style="display:grid">
                        <div class="course-progress-head"><div><h3>{{ $item['enrollment']->course->title }}</h3><p>{{ $item['enrollment']->course->title_en }} · التحق {{ $item['enrollment']->enrolled_at->diffForHumans() }}</p></div><strong>{{ $item['progress'] }}%</strong></div>
                        <div class="progress-track" role="progressbar" aria-label="تقدم {{ $item['enrollment']->course->title }}" aria-valuenow="{{ $item['progress'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $item['progress'] }}%"></span></div>
                        <div class="progress-label"><span>{{ $item['completed'] }} من {{ $item['total'] }} دروس</span><span>{{ $item['progress'] === 100 ? 'مكتملة' : 'قيد التعلم' }}</span></div>
                        <div class="course-signals"><span class="signal">آخر تقييم: {{ $item['attempt'] ? $item['attempt']->score.'%' : 'لا يوجد' }}</span><span class="signal">{{ $item['attempt']?->passed ? 'اجتاز التقييم' : 'لم يجتز بعد' }}</span><span class="signal">{{ $item['certificate'] ? 'صدرت الشهادة' : 'لا توجد شهادة' }}</span></div>
                    </article>
                @empty
                    <div class="empty-state">لم يلتحق الطالب بأي دورة حتى الآن.</div>
                @endforelse
            </div>
        </section>

        <section class="card table-wrap">
            <div class="section-heading" style="padding:0 0 4px"><div><div class="eyebrow">النتائج</div><h2>محاولات التقييم</h2></div></div>
            <table><thead><tr><th>الدورة</th><th>النتيجة</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>
            @forelse($attempts as $attempt)<tr><td>{{ $attempt->assessment->course->title }}</td><td><strong>{{ $attempt->score }}%</strong></td><td><span class="badge {{ $attempt->passed ? 'published' : 'inactive' }}">{{ $attempt->passed ? 'ناجح' : 'لم يجتز' }}</span></td><td>{{ $attempt->submitted_at->format('Y/m/d') }}</td></tr>@empty<tr><td colspan="4"><div class="empty-state">لا توجد محاولات تقييم.</div></td></tr>@endforelse
            </tbody></table>
        </section>

        <section class="card table-wrap">
            <div class="section-heading" style="padding:0 0 4px"><div><div class="eyebrow">الجانب المالي</div><h2>الطلبات والمدفوعات</h2></div></div>
            <table><thead><tr><th>المحتوى</th><th>المبلغ</th><th>الحالة</th><th>المزود</th><th>التاريخ</th></tr></thead><tbody>
            @forelse($orders as $order)
                @php($productTitle = $order->subscriptionPlan?->name_ar ?? $order->bundle?->name ?? $order->course?->title ?? 'اشتراك أو طلب عام')
                <tr><td>{{ $productTitle }}</td><td>{{ number_format((float)$order->amount) }} {{ $order->currency }}</td><td><span class="badge {{ $order->status === 'completed' ? 'published' : ($order->status === 'failed' ? 'inactive' : 'draft') }}">{{ $order->status }}</span></td><td class="latin">{{ $order->payment_provider }}</td><td>{{ ($order->paid_at ?? $order->created_at)->format('Y/m/d') }}</td></tr>
            @empty<tr><td colspan="5"><div class="empty-state">لا توجد طلبات دفع لهذا الطالب.</div></td></tr>@endforelse
            </tbody></table>
        </section>
    </div>

    <aside class="stack profile-sidebar">
        <section class="card"><div class="section-heading"><div><div class="eyebrow">الأسرة</div><h2>أولياء الأمور</h2></div></div><div class="detail-list">@forelse($parents as $parent)<div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong>{{ $parent->name }}</strong><small class="latin">{{ $parent->email }}</small><small>{{ $parent->pivot->grade ?? 'الصف غير محدد' }}</small></div></div>@empty<div class="empty-state">لا يوجد ولي أمر مرتبط.</div>@endforelse</div></section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">آخر نشاط</div><h2>الدروس المكتملة</h2></div></div><div class="detail-list">@forelse($recentCompletions as $completion)<div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong>{{ $completion->lesson->title }}</strong><small>{{ $completion->lesson->title_en }}</small><small>{{ $completion->completed_at->diffForHumans() }}</small></div></div>@empty<div class="empty-state">لا يوجد نشاط تعلّم بعد.</div>@endforelse</div></section>
        <section class="card"><div class="section-heading"><div><div class="eyebrow">الإنجاز</div><h2>الشهادات</h2></div></div><div class="detail-list">@forelse($certificates as $certificate)<div class="detail-item"><span class="status-dot" aria-hidden="true"></span><div><strong>{{ $certificate->course->title }}</strong><small class="latin">{{ $certificate->public_id }}</small><small>صدرت {{ $certificate->issued_at->diffForHumans() }}</small></div></div>@empty<div class="empty-state">لم تصدر شهادات بعد.</div>@endforelse</div></section>
    </aside>
</section>
@endsection
