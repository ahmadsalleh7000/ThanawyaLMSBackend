@extends('admin.layout')
@section('title', 'تحرير السؤال')
@section('content')
<header class="page-header"><div><div class="eyebrow">{{ $course->title }}</div><h1>تحرير سؤال التقييم</h1><p>حافظ على تطابق الإجابات العربية والإنجليزية وحدد الإجابة الصحيحة بدقة.</p></div><a class="button button-quiet" href="{{ route('admin.courses.edit',$course) }}">العودة للدورة</a></header>
<form class="card form-grid" method="post" action="{{ route('admin.questions.update',[$course,$question]) }}">
    @csrf @method('put')
    <label>نوع السؤال<select name="type"><option value="single_choice" @selected($question->type==='single_choice')>اختيار واحد</option><option value="true_false" @selected($question->type==='true_false')>صح أو خطأ</option></select></label><label>الصعوبة<select name="difficulty">@foreach(['easy'=>'سهل','medium'=>'متوسط','hard'=>'صعب'] as $value=>$label)<option value="{{ $value }}" @selected($question->difficulty===$value)>{{ $label }}</option>@endforeach</select></label>
    <label>الموضوع بالعربية<input name="topic_ar" value="{{ old('topic_ar',$question->topic_ar) }}" required></label><label lang="en">English topic<input dir="ltr" lang="en" name="topic" value="{{ old('topic',$question->topic) }}" required></label>
    <label>السؤال بالعربية<textarea name="prompt_ar" required>{{ old('prompt_ar',$question->prompt_ar) }}</textarea></label><label lang="en">English question<textarea dir="ltr" lang="en" name="prompt" required>{{ old('prompt',$question->prompt) }}</textarea></label>
    <label>الإجابات العربية — سطر لكل إجابة<textarea name="options_ar_text" required>{{ old('options_ar_text',implode("\n",$question->options_ar)) }}</textarea></label><label lang="en">English answers — one per line<textarea dir="ltr" lang="en" name="options_text" required>{{ old('options_text',implode("\n",$question->options)) }}</textarea></label>
    <label>رقم الإجابة الصحيحة — يبدأ من 0<input type="number" name="correct_option" min="0" value="{{ old('correct_option',$question->correct_option) }}" required></label><label>الترتيب<input type="number" name="position" min="1" value="{{ old('position',$question->position) }}" required></label>
    <label>التوضيح بالعربية<textarea name="explanation_ar" required>{{ old('explanation_ar',$question->explanation_ar) }}</textarea></label><label lang="en">English explanation<textarea dir="ltr" lang="en" name="explanation" required>{{ old('explanation',$question->explanation) }}</textarea></label>
    <div class="full form-actions"><button class="button button-primary">حفظ السؤال</button></div>
</form>
@endsection
