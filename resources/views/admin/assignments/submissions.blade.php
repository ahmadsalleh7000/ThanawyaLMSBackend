@extends('admin.layout')
@section('title', 'حلول مهام الدروس')
@section('content')
<div class="assignment-admin">
@php($statuses = ['submitted' => 'مُرسل', 'late' => 'تسليم متأخر', 'resubmitted' => 'تمت إعادة الإرسال', 'returned' => 'مطلوب تعديل', 'graded' => 'تم التصحيح'])
<header class="page-header"><div><div class="eyebrow">{{ $assignment->lesson->course->title }} / {{ $assignment->lesson->title }}</div><h1>{{ $assignment->title }}</h1><p>راجع الحلول، حمّل المرفقات، وصحح المهمة أو أعدها للطالب مع ملاحظات.</p></div><a class="button button-secondary" href="{{ route('admin.assignments.edit', $assignment) }}">تحرير المهمة</a></header>
<form class="card form-grid" method="get" role="search" aria-label="تصفية الحلول">
    <label>الطالب<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="150" placeholder="الاسم أو البريد الإلكتروني"></label>
    <label>الحالة<select name="status"><option value="">كل الحالات</option>@foreach($statuses as $status => $label)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>@endforeach</select></label><button class="button button-primary">تصفية الحلول</button>
</form>
<p>{{ $submissions->total() }} تسليم</p>
<div class="stack">
@forelse($submissions as $submission)
    <article class="card stack" aria-label="حل {{ $submission->user->name }}">
        <div class="section-heading"><div><h2>{{ $submission->user->name }}</h2><small>{{ $submission->user->email }}</small><p>تاريخ التسليم: {{ $submission->submitted_at->format('Y/m/d H:i') }} ({{ config('app.timezone') }})</p></div><span class="badge {{ $submission->status === 'graded' ? 'published' : 'draft' }}">{{ $statuses[$submission->status] ?? $submission->status }}</span></div>
        @if($submission->body)<p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $submission->body }}</p>@endif
        @if($submission->file_path)<a class="button button-secondary" href="{{ route('admin.assignments.file', [$assignment, $submission]) }}">تنزيل {{ $submission->file_original_name }}</a>@endif
        @if($submission->score !== null)<p><strong>الدرجة: {{ $submission->score }}%</strong></p>@endif
        @if($submission->feedback)<p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $submission->feedback }}</p>@endif
        <form class="form-grid" method="post" action="{{ route('admin.assignments.review', [$assignment, $submission]) }}">
            @csrf @method('patch')
            <input type="hidden" name="submitted_at" value="{{ $submission->submitted_at->toISOString() }}">
            <input type="hidden" name="review_version" value="{{ app(\App\Services\AssignmentWorkflowService::class)->reviewVersion($submission) }}">
            <label>الدرجة من 100<input type="number" name="score" min="0" max="100" step="1" value="{{ $submission->score }}"><small>مطلوبة عند حفظ الدرجة.</small></label>
            <label class="full">ملاحظات للطالب<textarea name="feedback" rows="3" maxlength="5000">{{ $submission->feedback }}</textarea><small>مطلوبة عند إعادة الحل للتعديل.</small></label>
            <div class="full actions-inline"><button class="button button-primary" name="action" value="grade">حفظ الدرجة</button><button class="button button-secondary" name="action" value="return">إعادة للتعديل</button></div>
        </form>
    </article>
@empty<div class="card empty-state">لا توجد حلول مطابقة.</div>@endforelse
</div>
<div class="pagination">{{ $submissions->links() }}</div>
</div>
@endsection
