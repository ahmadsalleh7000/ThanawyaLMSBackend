@extends('admin.layout')
@section('title', 'إعدادات الاختبار المتقدمة')
@section('content')
<a class="button button-quiet" href="{{ route('admin.exams.index') }}">← كل الاختبارات والامتحانات</a>
<header class="page-header"><div><div class="eyebrow">تدريب الدرس</div><h1>{{ $training->title_ar }}</h1><p>تحكم في المحاولات والتوزيع والمراجعة والأسئلة المتاحة لهذا الارتباط.</p></div><a class="button button-quiet" href="{{ $training->trainable instanceof \App\Models\Lesson ? route('admin.lessons.edit', [$training->trainable->course_id, $training->trainable, 'tab' => 'questions']) : route('admin.learning-paths.index') }}">العودة لمسار التعلم</a></header>
@if($training->status === 'published')<div class="errors">ألغِ النشر قبل تعديل الإعدادات والأسئلة.</div>@endif
<form method="post" action="{{ route('admin.learning-paths.state', ['training', $training->id]) }}">@csrf<input type="hidden" name="status" value="{{ $training->status === 'published' ? 'draft' : 'published' }}"><button class="button button-secondary">{{ $training->status === 'published' ? 'إلغاء النشر للتعديل' : 'نشر الاختبار' }}</button></form>
<form class="card stack" method="post" action="{{ route('admin.learning-paths.trainings.settings.update', $training) }}">
    @csrf @method('put')
    <fieldset class="stack" style="border:0;padding:0;min-width:0" @disabled($training->status === 'published')>
        <legend><h2>الإعدادات والمواعيد</h2></legend>
        <div class="form-grid">
            <label>درجة النجاح %<input type="number" name="passing_score" min="1" max="100" value="{{ old('passing_score', $training->passing_score ?: 70) }}" required></label>
            <label>متاح من<input type="datetime-local" name="available_from" value="{{ old('available_from', $training->available_from?->format('Y-m-d\TH:i')) }}"></label>
            <label>متاح حتى<input type="datetime-local" name="available_until" value="{{ old('available_until', $training->available_until?->format('Y-m-d\TH:i')) }}"></label>
        </div>
        @include('admin.assessments.partials.controls', ['exam' => $training])
        <h2>الأسئلة المفعلة</h2>
        <p class="helper-copy">إلغاء التحديد يعطل السؤال في هذا التدريب فقط. لن يتغير بنك الأسئلة أو ارتباطاته الأخرى.</p>
        <input type="hidden" name="enabled_question_ids" value="">
        @foreach($questions as $question)
            <label><span><input style="width:auto" type="checkbox" name="enabled_question_ids[]" value="{{ $question->id }}" @checked(in_array($question->id, old('enabled_question_ids', $questions->pluck('id')->diff($training->excluded_question_ids ?? [])->all()) ?: [])) @disabled(! $question->is_active)> {{ $question->prompt_ar }} {{ $question->is_active ? '' : '(معطل في بنك الأسئلة)' }}</span></label>
        @endforeach
        <button class="button button-primary">حفظ إعدادات الاختبار</button>
    </fieldset>
</form>
@endsection
