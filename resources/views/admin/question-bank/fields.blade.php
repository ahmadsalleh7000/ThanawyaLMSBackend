<div class="bank-field-row full">
    <label>نوع السؤال<select name="type" required><option value="single_choice" @selected(old('type',$item?->type)==='single_choice')>اختيار واحد</option><option value="true_false" @selected(old('type',$item?->type)==='true_false')>صح أو خطأ</option></select></label>
    <label>درجة الصعوبة<select name="difficulty" required>@foreach(['easy'=>'سهل','medium'=>'متوسط','hard'=>'صعب'] as $value=>$label)<option value="{{ $value }}" @selected(old('difficulty',$item?->difficulty ?? 'medium')===$value)>{{ $label }}</option>@endforeach</select></label>
</div>
<label>الموضوع بالعربية<input name="topic_ar" value="{{ old('topic_ar',$item?->topic_ar) }}" placeholder="مثال: الحلقات" required></label>
<label lang="en">English topic<input dir="ltr" name="topic" value="{{ old('topic',$item?->topic) }}" placeholder="Example: Loops" required></label>
<label>نص السؤال بالعربية<textarea name="prompt_ar" placeholder="اكتب سؤالاً واضحاً ومباشراً" required>{{ old('prompt_ar',$item?->prompt_ar) }}</textarea></label>
<label lang="en">English question<textarea dir="ltr" name="prompt" placeholder="Write a clear, direct question" required>{{ old('prompt',$item?->prompt) }}</textarea></label>
<label>الإجابات العربية <small>إجابة واحدة في كل سطر</small><textarea name="options_ar_text" placeholder="الإجابة الأولى&#10;الإجابة الثانية">{{ old('options_ar_text',$item ? implode("\n",$item->options_ar) : '') }}</textarea></label>
<label lang="en">English answers <small>One answer per line</small><textarea dir="ltr" name="options_text" placeholder="First answer&#10;Second answer">{{ old('options_text',$item ? implode("\n",$item->options) : '') }}</textarea></label>
<fieldset class="bank-correct-options full">
    <legend>الإجابة الصحيحة</legend>
    @foreach([0,1,2,3] as $index)
        <label><input type="radio" name="correct_option" value="{{ $index }}" @checked((int) old('correct_option',$item?->correct_option ?? 0) === $index)><span>الاختيار {{ $index + 1 }}</span></label>
    @endforeach
</fieldset>
<label>شرح الإجابة بالعربية<textarea name="explanation_ar" placeholder="اشرح لماذا هذه الإجابة صحيحة" required>{{ old('explanation_ar',$item?->explanation_ar) }}</textarea></label>
<label lang="en">English explanation<textarea dir="ltr" name="explanation" placeholder="Explain why this answer is correct" required>{{ old('explanation',$item?->explanation) }}</textarea></label>
