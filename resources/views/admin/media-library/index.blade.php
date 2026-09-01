@extends('admin.layout')
@section('title', 'مكتبة الوسائط')
@section('content')
<header class="page-header media-library-hero">
    <div><div class="eyebrow">إدارة الأصول التعليمية</div><h1>مكتبة الوسائط</h1><p>ارفع الملف مرة واحدة، تحقق من سلامته، وأعد استخدامه في أكثر من درس.</p></div>
    <a class="button button-primary" href="#upload-media"><span aria-hidden="true">↑</span> رفع ملف</a>
</header>

<section class="media-library-stats" aria-label="ملخص مكتبة الوسائط">
    <article><span>▣</span><div><strong>{{ number_format($stats['files']) }}</strong><small>ملفات فعالة</small></div><em>{{ $stats['videos'] }} فيديو · {{ $stats['resources'] }} ملفات</em></article>
    <article><span>GB</span><div><strong>{{ $stats['bytes'] >= 1073741824 ? number_format($stats['bytes']/1073741824,2) : number_format($stats['bytes']/1048576,1) }}</strong><small>{{ $stats['bytes'] >= 1073741824 ? 'جيجابايت' : 'ميجابايت' }} مستخدمة</small></div></article>
    <article><span>○</span><div><strong>{{ number_format($stats['orphaned']) }}</strong><small>غير مرتبطة بدروس</small></div><a href="{{ route('admin.media-library.index',['usage'=>'orphaned']) }}">مراجعة</a></article>
    <article class="{{ $stats['failed'] ? 'has-warning' : '' }}"><span>!</span><div><strong>{{ number_format($stats['failed']) }}</strong><small>ملفات تحتاج تدخلاً</small></div>@if($stats['failed'])<a href="{{ route('admin.media-library.index',['health'=>'failed']) }}">معالجة</a>@endif</article>
</section>

<form class="card media-library-filter" method="get" role="search">
    <input type="hidden" name="view" value="{{ $display }}">
    <label class="search-field"><span class="sr-only">البحث في الملفات</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="{{ request('search') }}" placeholder="اسم الملف أو نوعه..."></label>
    <select name="kind" aria-label="نوع الملف"><option value="">كل الأنواع</option><option value="video" @selected(request('kind')==='video')>فيديو</option><option value="resource" @selected(request('kind')==='resource')>ملف تعليمي</option></select>
    <select name="health" aria-label="حالة المعالجة"><option value="">كل حالات المعالجة</option><option value="ready" @selected(request('health')==='ready')>جاهز</option><option value="processing" @selected(request('health')==='processing')>قيد المعالجة</option><option value="failed" @selected(request('health')==='failed')>فشل</option></select>
    <select name="usage" aria-label="حالة الاستخدام"><option value="">كل الاستخدامات</option><option value="attached" @selected(request('usage')==='attached')>مرتبطة بدروس</option><option value="orphaned" @selected(request('usage')==='orphaned')>غير مرتبطة</option></select>
    <select name="status" aria-label="حالة الأرشفة"><option value="active" @selected(request('status','active')==='active')>المكتبة النشطة</option><option value="archived" @selected(request('status')==='archived')>الأرشيف</option></select>
    <select name="sort" aria-label="الترتيب"><option value="latest" @selected(request('sort','latest')==='latest')>الأحدث</option><option value="name" @selected(request('sort')==='name')>الاسم</option><option value="size" @selected(request('sort')==='size')>الحجم</option><option value="usage" @selected(request('sort')==='usage')>الأكثر استخداماً</option></select>
    <button class="button button-secondary">تطبيق</button>
    @if(request()->hasAny(['search','kind','health','usage','status','sort']))<a class="button button-quiet" href="{{ route('admin.media-library.index',['view'=>$display]) }}">مسح</a>@endif
</form>

<div class="media-library-workspace">
    <main>
        <div class="media-results-head"><div><strong>{{ $items->total() }} ملفاً</strong><span>{{ request('status') === 'archived' ? 'الأصول المؤرشفة محفوظة ولا يمكن ربطها' : 'أصول متاحة للإدارة وإعادة الاستخدام' }}</span></div><div class="listing-head-tools"><span>صفحة {{ $items->currentPage() }} من {{ $items->lastPage() }}</span><x-admin.view-switcher :display="$display" /></div></div>
        @if($display === 'table')
        @include('admin.media-library.table')
        @else
        <div class="media-library-grid">
            @forelse($items as $item)
            @php($extension = strtoupper(pathinfo($item->original_name, PATHINFO_EXTENSION) ?: ($item->kind === 'video' ? 'VIDEO' : 'FILE')))
            <article class="card library-item {{ $item->archived_at ? 'library-item-archived' : '' }}">
                <header class="media-card-head">
                    <span class="media-kind {{ $item->kind }}"><b>{{ $item->kind === 'video' ? '▶' : '▤' }}</b><small>{{ $extension }}</small></span>
                    <div class="media-file-title"><strong title="{{ $item->original_name }}">{{ $item->original_name }}</strong><small>{{ $item->mime_type }}</small></div>
                    <span class="media-health media-health-{{ $item->processing_status }}"><i></i>{{ ['ready'=>'جاهز','processing'=>'قيد المعالجة','failed'=>'فشل'][$item->processing_status] ?? $item->processing_status }}</span>
                </header>
                <div class="media-file-facts"><span><small>الحجم</small><strong>{{ $item->size_bytes >= 1048576 ? number_format($item->size_bytes/1048576,1).' MB' : number_format($item->size_bytes/1024).' KB' }}</strong></span><span><small>الاستخدام</small><strong>{{ $item->attachments_count }} ارتباطات</strong></span><span><small>تاريخ الرفع</small><strong>{{ $item->created_at->format('Y/m/d') }}</strong></span></div>
                <div class="media-uploader"><span class="account-avatar">{{ mb_substr($item->uploader?->name ?? 'ن',0,1) }}</span><div><small>رفع بواسطة</small><strong>{{ $item->uploader?->name ?? 'بيانات سابقة' }}</strong></div></div>
                @if($item->attachments_count)
                <div class="media-attachments"><small>مستخدم في</small>@foreach($item->attachments->take(2) as $attachment)<span>{{ $attachment->lesson?->course?->title }} · {{ $attachment->lesson?->title }}</span>@endforeach @if($item->attachments_count > 2)<em>+{{ $item->attachments_count - 2 }} استخدامات أخرى</em>@endif</div>
                @else<div class="media-orphan-note"><span>○</span><div><strong>غير مرتبط بعد</strong><small>يمكن ربطه بدرس أو أرشفته إذا لم تعد تحتاجه.</small></div></div>@endif
                @if($item->processing_error)<div class="media-processing-error"><strong>تعذر التحقق من الملف</strong><span>{{ $item->processing_error }}</span></div>@endif
                @if(!$item->archived_at && $item->processing_status === 'ready')
                <details class="attach-media"><summary><span>＋</span> ربط بدرس</summary><form class="stack attach-media-form" method="post" action="{{ route('admin.media-library.attach',$item) }}">@csrf<select name="lesson_id" required><option value="">اختر الدورة والدرس</option>@foreach($courses as $course)<optgroup label="{{ $course->title }}">@foreach($course->lessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach</optgroup>@endforeach</select><button class="button button-primary">تأكيد الربط</button></form></details>
                @endif
                <footer><form method="post" action="{{ route('admin.media-library.verify',$item) }}">@csrf<button class="button button-quiet">فحص السلامة</button></form>@if($item->attachments_count===0 && !$item->archived_at)<form method="post" action="{{ route('admin.media-library.archive',$item) }}" data-confirm="سيُخفى الملف من المكتبة النشطة دون حذفه من التخزين. متابعة؟">@csrf<button class="button button-danger">أرشفة</button></form>@endif</footer>
            </article>
            @empty<div class="card empty-state media-empty"><strong>لا توجد ملفات مطابقة</strong><span>عدّل الفلاتر أو ارفع أصلاً تعليمياً جديداً.</span><a class="button button-primary" href="#upload-media">رفع ملف</a></div>@endforelse
        </div>
        @endif
        <div class="pagination">{{ $items->links() }}</div>
    </main>

    <aside id="upload-media">
        <section class="card media-upload">
            <div class="section-heading"><div><div class="eyebrow">أصل جديد</div><h2>رفع إلى المكتبة</h2></div></div>
            <form class="stack" method="post" enctype="multipart/form-data" action="{{ route('admin.media-library.store') }}">@csrf
                <label>نوع الأصل<select name="kind"><option value="video">فيديو MP4 أو WebM</option><option value="resource">PDF، ZIP أو مستند</option></select></label>
                <label class="media-dropzone"><input type="file" name="file" required><span aria-hidden="true">↑</span><strong>اختر ملفاً من جهازك</strong><small>أو اسحبه إلى هذه المنطقة</small></label>
                <div class="upload-rules"><span>الفيديو حتى 512MB</span><span>الملفات حتى 25MB</span><span>يُحسب توقيع SHA-256</span></div>
                <button class="button button-primary">رفع وحفظ الأصل</button>
            </form>
        </section>
    </aside>
</div>
@endsection
