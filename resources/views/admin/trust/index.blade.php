@extends('admin.layout')
@section('title', 'الثقة والشهادات')
@section('content')
<header class="page-header quality-page-hero trust-page-hero">
    <div><div class="eyebrow">ضمان الجودة والمصداقية</div><h1>الثقة والشهادات</h1><p>راجع آراء الطلاب واحمِ مصداقية الشهادات الصادرة من المنصة.</p></div>
</header>

<section class="grid stats-grid trust-stats quality-kpis" aria-label="ملخص الثقة والجودة">
    <article class="card compact-stat"><span class="stat-icon">◷</span><div><strong>{{ number_format($counts['pending']) }}</strong><span>تقييمات بانتظار المراجعة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">★</span><div><strong>{{ number_format($counts['published']) }}</strong><span>تقييمات منشورة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">✓</span><div><strong>{{ number_format($counts['activeCertificates']) }}</strong><span>شهادات فعّالة</span></div></article>
    <article class="card compact-stat"><span class="stat-icon">×</span><div><strong>{{ number_format($counts['revokedCertificates']) }}</strong><span>شهادات ملغاة</span></div></article>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-heading"><div><div class="eyebrow">صوت المتعلم</div><h2>مراجعة تقييمات الدورات</h2></div></div>
    <nav class="filter-pills quality-filter-pills" aria-label="حالة التقييمات">
        @foreach([''=>'الكل','pending'=>'قيد المراجعة','published'=>'منشور','rejected'=>'مرفوض'] as $value=>$label)
            <a class="{{ request('status','')===$value?'active':'' }}" href="{{ route('admin.trust.index',array_filter(['status'=>$value])) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="review-grid">
        @forelse($reviews as $review)
            <article class="review-card review-status-{{ $review->status }}">
                <div class="review-top"><div><strong>{{ $review->user->name }}</strong><small>{{ $review->course->title }}</small></div><span class="badge {{ $review->status==='published'?'published':($review->status==='rejected'?'inactive':'draft') }}">{{ ['pending'=>'قيد المراجعة','published'=>'منشور','rejected'=>'مرفوض'][$review->status] }}</span></div>
                <div class="stars" aria-label="{{ $review->rating }} من 5">{{ str_repeat('★',$review->rating) }}<span>{{ str_repeat('☆',5-$review->rating) }}</span></div>
                <p>{{ $review->comment }}</p>
                <small>{{ $review->created_at->diffForHumans() }}</small>
                <form class="moderation-form" method="post" action="{{ route('admin.trust.reviews.moderate',$review) }}">@csrf
                    <input name="moderation_note" value="{{ $review->moderation_note }}" placeholder="ملاحظة داخلية اختيارية" maxlength="1000">
                    <div class="actions"><button class="button button-primary" name="status" value="published">نشر</button><button class="button button-danger" name="status" value="rejected">رفض</button></div>
                </form>
            </article>
        @empty<div class="empty-state">لا توجد تقييمات ضمن هذه الحالة.</div>@endforelse
    </div>
    <div class="pagination">{{ $reviews->links() }}</div>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-heading"><div><div class="eyebrow">سجل الاعتماد</div><h2>الشهادات الصادرة</h2></div><a class="button button-quiet" href="{{ route('admin.trust.index',['revoked'=>request('revoked')?null:1]) }}">{{ request('revoked')?'عرض الفعّالة':'عرض الملغاة' }}</a></div>
    <div class="table-wrap"><table><thead><tr><th>الطالب</th><th>الدورة</th><th>المعرّف</th><th>تاريخ الإصدار</th><th>الحالة والإجراء</th></tr></thead><tbody>
        @forelse($certificates as $certificate)<tr><td class="cell-title"><strong>{{ $certificate->user->name }}</strong><small>{{ $certificate->user->email }}</small></td><td>{{ $certificate->course->title }}</td><td class="latin">{{ $certificate->public_id }}</td><td>{{ $certificate->issued_at->format('Y/m/d') }}</td><td>@if($certificate->revoked_at)<span class="badge inactive">ملغاة</span><small class="block-note">{{ $certificate->revocation_reason }}</small>@else<form class="inline-revoke" method="post" action="{{ route('admin.trust.certificates.revoke',$certificate) }}" data-confirm="هل أنت متأكد من إلغاء هذه الشهادة؟">@csrf<input name="reason" required minlength="10" maxlength="2000" placeholder="سبب الإلغاء"><button class="button button-danger">إلغاء</button></form>@endif</td></tr>
        @empty<tr><td colspan="5"><div class="empty-state">لا توجد شهادات ضمن هذا العرض.</div></td></tr>@endforelse
    </tbody></table></div><div class="pagination">{{ $certificates->links() }}</div>
</section>
@endsection
