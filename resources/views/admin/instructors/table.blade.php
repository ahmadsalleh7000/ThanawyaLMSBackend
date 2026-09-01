<div class="card table-wrap content-list-table-wrap">
    <table class="content-list-table instructors-list-table">
        <thead><tr><th>المدرس</th><th>الحالة</th><th>اكتمال الملف</th><th>الدورات</th><th>الطلاب</th><th>التقييم</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
        <tbody>
        @forelse($instructors as $instructor)
            @php($enrollments=$instructor->courses->sum('enrollments_count'))
            @php($rating=$instructor->courses->whereNotNull('published_rating')->avg('published_rating') ?? 0)
            <tr class="{{ $instructor->is_active ? '' : 'muted-row' }}">
                <td><div class="table-primary-cell"><span class="table-record-icon instructor">{{ mb_substr($instructor->name,0,1) }}</span><div><strong>{{ $instructor->name }}</strong><small lang="en" dir="ltr">{{ $instructor->name_en }}</small><em class="latin">{{ $instructor->email ?: '—' }}</em></div></div></td>
                <td><span class="badge {{ $instructor->is_active ? 'active' : 'inactive' }}">{{ $instructor->is_active ? 'نشط' : 'غير نشط' }}</span></td>
                <td><div class="table-progress"><div><strong>{{ $instructor->profile_completion }}%</strong></div><div class="progress-track"><span style="width:{{ $instructor->profile_completion }}%"></span></div></div></td>
                <td><strong>{{ $instructor->courses_count }}</strong><small>{{ $instructor->courses->first()?->title ?: 'دون دورة' }}</small></td>
                <td><strong>{{ number_format($enrollments) }}</strong><small>مسجلون</small></td>
                <td><strong>{{ $rating ? number_format($rating,1) : '—' }}</strong><small>من 5</small></td>
                <td><div class="table-row-actions"><details class="table-action-menu"><summary class="icon-action" aria-label="تحرير {{ $instructor->name }}" title="تحرير">✎</summary><div class="table-action-panel"><form class="form-grid instructor-edit" method="post" action="{{ route('admin.instructors.update',$instructor) }}">@csrf @method('put') @include('admin.instructors.fields',['item'=>$instructor])<button class="button button-primary full">حفظ التعديلات</button></form></div></details><form method="post" action="{{ route('admin.instructors.toggle',$instructor) }}">@csrf<button class="icon-action" aria-label="{{ $instructor->is_active?'تعطيل':'تفعيل' }} {{ $instructor->name }}">{{ $instructor->is_active ? '×' : '✓' }}</button></form></div></td>
            </tr>
        @empty<tr><td colspan="7"><div class="empty-state"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف مدرساً.</span></div></td></tr>@endforelse
        </tbody>
    </table>
</div>
