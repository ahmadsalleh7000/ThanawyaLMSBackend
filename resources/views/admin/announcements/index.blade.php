@extends('admin.layout')
@section('title', 'التواصل والإعلانات')
@section('content')
<header class="page-header community-page-hero announcement-page-hero"><div><div class="eyebrow">تواصل موجّه وقابل للقياس</div><h1>التواصل والإعلانات</h1><p>أنشئ رسائل واضحة، وجّهها بدقة، وتابع وصولها.</p></div><a class="button button-primary" href="#announcement-composer"><span aria-hidden="true">＋</span> إعلان جديد</a></header>

<section class="community-stats announcement-page-stats" aria-label="ملخص الإعلانات">
    <a href="{{ route('admin.announcements.index',['status'=>'draft']) }}"><span class="community-stat-icon">✎</span><div><strong>{{ $counts['draft'] }}</strong><small>مسودات</small></div><em>بانتظار الإكمال</em></a>
    <a href="{{ route('admin.announcements.index',['status'=>'scheduled']) }}"><span class="community-stat-icon">◷</span><div><strong>{{ $counts['scheduled'] }}</strong><small>مجدولة</small></div><em>نشر تلقائي</em></a>
    <a href="{{ route('admin.announcements.index',['status'=>'published']) }}"><span class="community-stat-icon">✓</span><div><strong>{{ $counts['published'] }}</strong><small>منشورة</small></div><em>تم إرسالها</em></a>
</section>

<section class="grid content-columns communication-layout community-workspace">
    <main class="stack">
        <div class="community-controlbar"><nav class="filter-pills community-filter-pills" aria-label="حالة الإعلانات">@foreach([''=>'الكل','draft'=>'مسودة','scheduled'=>'مجدول','published'=>'منشور','cancelled'=>'ملغى'] as $value=>$label)<a class="{{ request('status','')===$value?'active':'' }}" href="{{ route('admin.announcements.index',array_filter(['status'=>$value])) }}">{{ $label }}</a>@endforeach</nav><div class="community-results"><strong>{{ $announcements->total() }} إعلاناً</strong><span>صفحة {{ $announcements->currentPage() }} من {{ $announcements->lastPage() }}</span></div></div>
        <div class="announcement-list">
        @forelse($announcements as $announcement)
            @php($readRate=$announcement->notifications_count ? round($announcement->read_count/$announcement->notifications_count*100) : 0)
            <article class="card announcement-card announcement-status-{{ $announcement->status }}">
                <header class="announcement-heading"><div class="announcement-title-row"><span class="announcement-type-icon" aria-hidden="true">{{ $announcement->status === 'published' ? '✓' : ($announcement->status === 'scheduled' ? '◷' : '✎') }}</span><div><div class="announcement-labels"><span class="badge {{ ['draft'=>'draft','scheduled'=>'active','published'=>'published','cancelled'=>'inactive'][$announcement->status] }}">{{ ['draft'=>'مسودة','scheduled'=>'مجدول','published'=>'منشور','cancelled'=>'ملغى'][$announcement->status] }}</span><span class="audience-pill">{{ ['all'=>'كل المستخدمين','students'=>'الطلاب','parents'=>'أولياء الأمور','course'=>'دورة محددة','grade'=>'صف دراسي'][$announcement->target_type] }}</span></div><h2>{{ $announcement->title_ar }}</h2><small lang="en" dir="ltr">{{ $announcement->title }}</small></div></div></header>
                <p>{{ $announcement->message_ar }}</p>
                @if($announcement->status==='published')<div class="delivery-meter"><div><span>معدل القراءة</span><strong>{{ $readRate }}%</strong></div><div class="progress-track" role="progressbar" aria-label="معدل قراءة الإعلان" aria-valuenow="{{ $readRate }}" aria-valuemin="0" aria-valuemax="100"><i style="width:{{ $readRate }}%"></i></div><small>{{ $announcement->read_count }} قرأوا من {{ $announcement->notifications_count }} مستلم</small></div>@endif
                <footer><div class="announcement-author"><span>{{ mb_substr($announcement->creator?->name ?? 'ن',0,1) }}</span><small><strong>{{ $announcement->creator?->name ?? 'النظام' }}</strong>{{ $announcement->created_at->diffForHumans() }}@if($announcement->publish_at && $announcement->status==='scheduled') · النشر {{ $announcement->publish_at->format('Y/m/d H:i') }}@endif</small></div>@if(in_array($announcement->status,['draft','scheduled']))<div class="actions"><form method="post" action="{{ route('admin.announcements.publish',$announcement) }}">@csrf<button class="button button-primary">نشر الآن</button></form><form method="post" action="{{ route('admin.announcements.cancel',$announcement) }}">@csrf<button class="button button-quiet danger-text">إلغاء</button></form></div>@else<span class="announcement-delivery-state">{{ $announcement->status === 'published' ? 'تم التسليم' : 'تم إيقاف الرسالة' }}</span>@endif</footer>
            </article>
        @empty<div class="card empty-state community-empty"><span class="empty-state-icon" aria-hidden="true">✉</span><strong>لا توجد إعلانات ضمن هذه الحالة</strong><span>اختر حالة أخرى أو أنشئ رسالة جديدة.</span></div>@endforelse
        </div><div class="pagination">{{ $announcements->links() }}</div>
    </main>
    <aside id="announcement-composer"><details class="card community-side-card announcement-composer" @if($errors->any()) open @endif><summary><span><small>رسالة جديدة</small><strong>إنشاء إعلان</strong></span><b aria-hidden="true">＋</b></summary><p>اكتب نسختين واضحتين، ثم اختر الجمهور ووقت الإرسال.</p>
        <form class="stack" method="post" action="{{ route('admin.announcements.store') }}">@csrf
            <div class="bilingual-fields"><label>العنوان العربي<input name="title_ar" value="{{ old('title_ar') }}" required maxlength="255"></label><label lang="en">English title<input dir="ltr" name="title" value="{{ old('title') }}" required maxlength="255"></label></div>
            <label>الرسالة العربية<textarea name="message_ar" required maxlength="5000">{{ old('message_ar') }}</textarea></label><label lang="en">English message<textarea dir="ltr" name="message" required maxlength="5000">{{ old('message') }}</textarea></label>
            <label>الجمهور<select name="target_type" data-audience-select><option value="all">كل الطلاب وأولياء الأمور</option><option value="students">كل الطلاب</option><option value="parents">كل أولياء الأمور</option><option value="course">طلاب دورة محددة</option><option value="cohort">فصل محدد</option><option value="grade">صف دراسي</option></select></label>
            <div class="bilingual-fields"><label>الدورة<select name="course_id"><option value="">عند الحاجة</option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label><label>الفصل<select name="cohort_id"><option value="">عند الحاجة</option>@foreach($cohorts as $cohort)<option value="{{ $cohort->id }}">{{ $cohort->name }}</option>@endforeach</select></label><label>الصف<select name="grade"><option value="">عند الحاجة</option><option value="grade-1">الأول الثانوي</option><option value="grade-2">الثاني الثانوي</option><option value="grade-3">الثالث الثانوي</option></select></label></div>
            <label>رابط الإجراء<input dir="ltr" name="action_url" value="{{ old('action_url') }}" placeholder="/courses"></label>
            <label class="checkbox-row"><input type="checkbox" name="is_banner" value="1" @checked(old('is_banner'))> عرض بارز في لوحة المستخدم حتى القراءة أو انتهاء الصلاحية</label>
            <div class="bilingual-fields"><label>موعد النشر<input dir="ltr" type="datetime-local" name="publish_at" value="{{ old('publish_at') }}"></label><label>الانتهاء<input dir="ltr" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"></label></div>
            <div class="composer-actions"><button class="button button-quiet" name="action" value="draft">حفظ مسودة</button><button class="button button-secondary" name="action" value="schedule">جدولة</button><button class="button button-primary" name="action" value="publish">نشر الآن</button></div>
        </form>
    </details></aside>
</section>
@endsection
