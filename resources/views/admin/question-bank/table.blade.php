<div class="card table-wrap content-list-table-wrap">
    <table class="content-list-table questions-list-table">
        <thead><tr><th>السؤال</th><th>الموضوع</th><th>النوع</th><th>الصعوبة</th><th>الاستخدام</th><th>الحالة</th><th><span class="sr-only">الإجراءات</span></th></tr></thead>
        <tbody>
        @forelse($questions as $question)
            <tr class="{{ $question->is_active ? '' : 'muted-row' }}">
                <td><div class="table-question"><strong>{{ $question->prompt_ar }}</strong><small lang="en" dir="ltr">{{ $question->prompt }}</small></div></td>
                <td><strong>{{ $question->topic_ar }}</strong><small lang="en" dir="ltr">{{ $question->topic }}</small></td>
                <td>{{ $question->type === 'true_false' ? 'صح أو خطأ' : 'اختيار واحد' }}</td>
                <td><span class="table-difficulty difficulty-{{ $question->difficulty }}">{{ ['easy'=>'سهل','medium'=>'متوسط','hard'=>'صعب'][$question->difficulty] }}</span></td>
                <td><strong>{{ $question->assessment_questions_count }}</strong><small>تقييمات</small></td>
                <td><span class="badge {{ $question->is_active ? 'active' : 'inactive' }}">{{ $question->is_active ? 'فعال' : 'معطل' }}</span></td>
                <td><div class="table-row-actions"><details class="table-action-menu"><summary class="icon-action" aria-label="تحرير السؤال" title="تحرير">✎</summary><div class="table-action-panel"><form class="form-grid bank-edit" method="post" action="{{ route('admin.question-bank.update',$question) }}">@csrf @method('put') @include('admin.question-bank.fields',['item'=>$question])<button class="button button-primary full">حفظ التعديلات</button></form></div></details><form method="post" action="{{ route('admin.question-bank.toggle',$question) }}">@csrf<button class="icon-action" aria-label="{{ $question->is_active ? 'تعطيل' : 'تفعيل' }} السؤال">{{ $question->is_active ? '×' : '✓' }}</button></form></div></td>
            </tr>
        @empty<tr><td colspan="7"><div class="empty-state"><strong>لا توجد نتائج</strong><span>غيّر الفلاتر أو أضف سؤالاً جديداً.</span></div></td></tr>@endforelse
        </tbody>
    </table>
</div>
