@extends('admin.layout')
@section('title', $assignment->exists ? 'تحرير مهمة الدرس' : 'إضافة مهمة درس')
@section('content')
<div class="assignment-admin">
<header class="page-header"><div><div class="eyebrow">مهام الدروس</div><h1>{{ $assignment->exists ? 'تحرير مهمة الدرس' : 'إضافة مهمة درس' }}</h1>@if($course)<p>{{ $course->title }} @if($assignment->exists) / {{ $assignment->lesson->title }}@endif</p>@endif</div><div class="actions-inline"><a class="button button-secondary" href="{{ route('admin.assignments.index') }}">كل المهام</a>@if($assignment->exists)<a class="button button-primary" href="{{ route('admin.assignments.submissions', $assignment) }}">حلول الطلاب</a>@endif</div></header>
@if(!$assignment->exists)
<form class="card form-grid" method="get" action="{{ route('admin.assignments.create') }}">
    <label>اختر الدورة<select name="course_id" required><option value="">اختر دورة</option>@foreach($courses as $item)<option value="{{ $item->id }}" @selected($course?->id === $item->id)>{{ $item->title }}</option>@endforeach</select></label><button class="button button-secondary">عرض دروس الدورة</button>
</form>
@endif
@if($assignment->exists || $course)
<form class="card form-grid" method="post" action="{{ $assignment->exists ? route('admin.assignments.update', $assignment) : route('admin.assignments.store') }}">
    @csrf @if($assignment->exists) @method('put') @endif
    @if(!$assignment->exists)<label class="full">الدرس<select name="lesson_id" required><option value="">اختر درسًا</option>@foreach($lessons as $lesson)<option value="{{ $lesson->id }}" @selected((string) old('lesson_id', $assignment->lesson_id) === (string) $lesson->id)>{{ $lesson->title }}</option>@endforeach</select></label>@endif
    <label>العنوان العربي<input name="title" value="{{ old('title', $assignment->title) }}" maxlength="200" required></label>
    <label lang="en">English title (optional)<input name="title_en" dir="ltr" value="{{ old('title_en', $assignment->title_en) }}" maxlength="200"></label>
    <label class="full">تعليمات المهمة<textarea name="description" rows="6" maxlength="5000">{{ old('description', $assignment->description) }}</textarea></label>
    <label class="full" lang="en">English instructions (optional)<textarea name="description_en" rows="6" dir="ltr" maxlength="5000">{{ old('description_en', $assignment->description_en) }}</textarea></label>
    <label>موعد التسليم ({{ config('app.timezone') }})<input type="datetime-local" name="due_at" value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}"><small>اتركه فارغًا إذا لم يوجد موعد. التسليم بعد الموعد يُسجل كمتأخر.</small></label>
    <label>الترتيب داخل الدرس<input type="number" name="position" value="{{ old('position', $assignment->position) }}" min="1" max="1000" required></label>
    <input type="hidden" name="is_published" value="0"><label class="full"><span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $assignment->is_published))> نشر المهمة للطلاب</span><small>يتطلب ظهور المهمة إتاحة الدورة والدرس للطالب.</small></label>
    <div class="full form-actions"><button class="button button-primary">حفظ المهمة</button></div>
</form>
@if($assignment->exists)
<section class="card"><h2>حذف المهمة</h2><p>يمكن حذف المسودات التي لم تستقبل حلولًا فقط. ألغِ النشر لإخفاء مهمة مع الاحتفاظ بحلول الطلاب.</p>
@if(!$assignment->is_published && !$assignment->submissions()->exists())<form method="post" action="{{ route('admin.assignments.destroy', $assignment) }}" data-confirm="هل تريد حذف هذه المهمة؟">@csrf @method('delete')<button class="button button-danger">حذف المهمة</button></form>@endif
</section>
@endif
@endif
</div>
@endsection
