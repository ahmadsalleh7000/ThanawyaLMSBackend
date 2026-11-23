<div class="form-grid">
    <label>عدد مرات الإعادة بعد المحاولة الأولى<input type="number" name="max_retries" min="0" max="1000" value="{{ old('max_retries', $exam->max_retries) }}" placeholder="غير محدود"><small>0 = محاولة واحدة فقط. اتركه فارغًا لإعادة غير محدودة.</small></label>
    <label>الانتظار بين المحاولات بالدقائق<input type="number" name="retry_delay_minutes" min="0" max="525600" value="{{ old('retry_delay_minutes', $exam->retry_delay_minutes ?? 0) }}" required></label>
    <label>عدد الأسئلة لكل طالب<input type="number" name="question_limit" min="1" max="200" value="{{ old('question_limit', $exam->question_limit) }}" placeholder="كل الأسئلة المفعلة"></label>
    @if($exam instanceof \App\Models\Training)
        <label>مدة التدريب بالدقائق<input type="number" name="duration_minutes" min="1" max="180" value="{{ old('duration_minutes', $exam->duration_minutes) }}" placeholder="بدون مؤقت"><small>اتركه فارغًا لتدريب دون حد زمني. يبدأ المؤقت عند بدء الطالب وتُحفظ إجاباته تلقائيًا.</small></label>
    @endif
    @foreach([
        'allow_retake_after_pass' => ['السماح بالإعادة بعد النجاح', true],
        'randomize_questions' => ['ترتيب عشوائي للأسئلة لكل طالب', false],
        'randomize_options' => ['ترتيب عشوائي للاختيارات لكل طالب', false],
        'require_all_answers' => ['إلزام الطالب بإجابة كل الأسئلة قبل التسليم', false],
        'show_correct_answers' => ['عرض الإجابات الصحيحة بعد التسليم', true],
        'show_explanations' => ['عرض التوضيحات مع مراجعة الإجابات', true],
        'allow_question_navigation' => ['السماح بالتنقل والعودة بين الأسئلة', true],
    ] as $field => [$label, $default])
        <label><input type="hidden" name="{{ $field }}" value="0"><span><input style="width:auto" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $exam->$field ?? $default))> {{ $label }}</span></label>
    @endforeach
    <p class="helper-copy full">المحاولة الأولى لا تُحسب كإعادة. تُطبق حدود المحاولات على الخادم، ويظل ترتيب الأسئلة والاختيارات ثابتًا عند تحديث الصفحة. تُخفى التوضيحات عندما تكون مراجعة الإجابات معطلة. لا يُعاد ترتيب اختيارات الصح والخطأ.</p>
</div>
