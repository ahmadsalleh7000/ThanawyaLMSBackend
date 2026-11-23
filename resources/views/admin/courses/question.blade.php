@extends('admin.layout')
@section('title', 'تحرير السؤال')
@section('content')
<header class="page-header"><div><div class="eyebrow">{{ $course->title }}</div><h1>تحرير سؤال التقييم</h1><p>حافظ على تطابق الإجابات العربية والإنجليزية وحدد الإجابة الصحيحة بدقة.</p></div><a class="button button-quiet" href="{{ route('admin.courses.edit',$course) }}">العودة للدورة</a></header>
<form class="card form-grid" method="post" action="{{ route('admin.questions.update',[$course,$question]) }}">
    @csrf @method('put')
    @include('admin.question-bank.fields', ['item' => $question])
    <label>الترتيب<input type="number" name="position" min="1" value="{{ old('position', $question->position) }}" required></label>
    <div class="full form-actions"><button class="button button-primary">حفظ السؤال</button></div>
</form>
@endsection
