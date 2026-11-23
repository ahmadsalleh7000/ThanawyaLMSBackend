@extends('admin.layout')
@section('title', 'مهام الدروس')
@section('content')
<div class="assignment-admin">
<header class="page-header"><div><div class="eyebrow">إدارة التعلم</div><h1>مهام الدروس</h1><p>أنشئ المهام، حدد موعد التسليم، وراجع حلول الطلاب ودرجاتهم.</p></div><a class="button button-primary" href="{{ route('admin.assignments.create', array_filter(['course_id' => $filters['course_id'] ?? null, 'lesson_id' => $filters['lesson_id'] ?? null])) }}">إضافة مهمة</a></header>
@if($selectedLesson)<div class="card section-heading"><div><strong>{{ $selectedLesson->title }}</strong><p>{{ $selectedLesson->course->title }}</p></div><a class="button button-secondary" href="{{ route('admin.lessons.edit', [$selectedLesson->course, $selectedLesson]) }}">العودة إلى الدرس</a></div>@endif
<form method="get" class="card form-grid" role="search" aria-label="تصفية المهام">
    @if($selectedLesson)<input type="hidden" name="lesson_id" value="{{ $selectedLesson->id }}">@endif
    <label>البحث<input name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="150" placeholder="عنوان المهمة أو الدرس"></label>
    <label>الدورة<select name="course_id"><option value="">كل الدورات</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(($filters['course_id'] ?? '') === $course->id)>{{ $course->title }}</option>@endforeach</select></label>
    <label>الحالة<select name="status"><option value="">كل الحالات</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>منشورة</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>مسودة</option></select></label>
    <div class="actions-inline"><button class="button button-primary">بحث وتصفية</button><a class="button button-quiet" href="{{ route('admin.assignments.index') }}">كل المهام</a></div>
</form>
<section class="card table-wrap" aria-label="قائمة مهام الدروس">
    <p>{{ $assignments->total() }} مهمة</p>
    <table><thead><tr><th scope="col">المهمة</th><th scope="col">الدرس والدورة</th><th scope="col">التسليم</th><th scope="col">الحالة</th><th scope="col">المتابعة</th><th scope="col">الإدارة</th></tr></thead><tbody>
    @forelse($assignments as $assignment)
        <tr>
            <td><strong>{{ $assignment->title }}</strong>@if($assignment->title_en)<small dir="ltr" lang="en">{{ $assignment->title_en }}</small>@endif<small>الترتيب: {{ $assignment->position }}</small></td>
            <td><a href="{{ route('admin.lessons.edit', [$assignment->lesson->course, $assignment->lesson]) }}">{{ $assignment->lesson->title }}</a><small>{{ $assignment->lesson->course->title }}</small></td>
            <td>{{ $assignment->due_at?->format('Y/m/d H:i') ?? 'بدون موعد' }}@if($assignment->due_at?->isPast())<small>انتهى الموعد · يقبل التسليم المتأخر</small>@endif</td>
            <td><span class="badge {{ $assignment->is_published ? 'published' : 'draft' }}">{{ $assignment->is_published ? 'منشورة' : 'مسودة' }}</span></td>
            <td><strong>{{ $assignment->submissions_count }} تسليم</strong><small>{{ $assignment->pending_count }} بانتظار التصحيح</small></td>
            <td><div class="actions-inline"><a class="button button-secondary" href="{{ route('admin.assignments.edit', $assignment) }}">تحرير المهمة</a><a class="button button-quiet" href="{{ route('admin.assignments.submissions', $assignment) }}">حلول الطلاب</a></div></td>
        </tr>
    @empty<tr><td colspan="6"><div class="empty-state">لا توجد مهام مطابقة. أضف مهمة أو غيّر الفلاتر.</div></td></tr>@endforelse
    </tbody></table>
</section>
<div class="pagination">{{ $assignments->links() }}</div>
</div>
@endsection
