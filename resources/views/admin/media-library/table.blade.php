<div class="card table-wrap content-list-table-wrap">
    <table class="content-list-table media-list-table">
        <thead><tr><th>الملف</th><th>النوع</th><th>المعالجة</th><th>الحجم</th><th>الاستخدام</th><th>تاريخ الرفع</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
        <tbody>
        @forelse($items as $item)
            @php($extension = strtoupper(pathinfo($item->original_name, PATHINFO_EXTENSION) ?: ($item->kind === 'video' ? 'VIDEO' : 'FILE')))
            <tr class="{{ $item->archived_at ? 'muted-row' : '' }}">
                <td><div class="table-primary-cell"><span class="table-record-icon media">{{ $item->kind === 'video' ? '▶' : '▤' }}</span><div><strong>{{ $item->original_name }}</strong><small class="latin">{{ $item->mime_type }}</small><em>{{ $item->uploader?->name ?? 'بيانات سابقة' }}</em></div></div></td>
                <td><strong>{{ $item->kind === 'video' ? 'فيديو' : 'ملف تعليمي' }}</strong><small>{{ $extension }}</small></td>
                <td><span class="media-health media-health-{{ $item->processing_status }}"><i></i>{{ ['ready'=>'جاهز','processing'=>'قيد المعالجة','failed'=>'فشل'][$item->processing_status] ?? $item->processing_status }}</span></td>
                <td><strong>{{ $item->size_bytes >= 1048576 ? number_format($item->size_bytes/1048576,1).' MB' : number_format($item->size_bytes/1024).' KB' }}</strong></td>
                <td><strong>{{ $item->attachments_count }} ارتباطات</strong><small>{{ $item->attachments_count ? 'مستخدم في الدروس' : 'غير مرتبط' }}</small></td>
                <td><time datetime="{{ $item->created_at->toDateString() }}">{{ $item->created_at->format('Y/m/d') }}</time></td>
                <td><div class="table-row-actions">@if(!$item->archived_at && $item->processing_status === 'ready')<details class="table-action-menu"><summary class="icon-action" aria-label="ربط {{ $item->original_name }} بدرس" title="ربط بدرس">＋</summary><div class="table-action-panel compact-panel"><form class="stack" method="post" action="{{ route('admin.media-library.attach',$item) }}">@csrf<select name="lesson_id" required><option value="">اختر الدورة والدرس</option>@foreach($courses as $course)<optgroup label="{{ $course->title }}">@foreach($course->lessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach</optgroup>@endforeach</select><button class="button button-primary">تأكيد الربط</button></form></div></details>@endif<form method="post" action="{{ route('admin.media-library.verify',$item) }}">@csrf<button class="icon-action" aria-label="فحص {{ $item->original_name }}" title="فحص السلامة">✓</button></form>@if($item->attachments_count===0 && !$item->archived_at)<form method="post" action="{{ route('admin.media-library.archive',$item) }}" data-confirm="سيُخفى الملف من المكتبة النشطة دون حذفه من التخزين. متابعة؟">@csrf<button class="icon-action danger" aria-label="أرشفة {{ $item->original_name }}" title="أرشفة">×</button></form>@endif</div></td>
            </tr>
        @empty<tr><td colspan="7"><div class="empty-state"><strong>لا توجد ملفات مطابقة</strong><span>عدّل الفلاتر أو ارفع ملفاً جديداً.</span></div></td></tr>@endforelse
        </tbody>
    </table>
</div>
