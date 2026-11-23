<?php

namespace App\Services;

use App\Models\QuestionBankQuestion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionAuthoringService
{
    public function data(Request $request): array
    {
        $request->merge(['type' => $request->input('type') ?: 'single_choice', 'difficulty' => $request->input('difficulty') ?: 'medium']);
        if ($request->filled('correct_option_number')) {
            $request->validate(['correct_option_number' => ['integer', 'min:1', 'max:20']]);
            $request->merge(['correct_option' => $request->integer('correct_option_number') - 1]);
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(QuestionBankQuestion::TYPES)], 'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'topic' => ['required', 'string', 'max:150'], 'topic_ar' => ['required', 'string', 'max:150'],
            'prompt' => ['required', 'string', 'max:3000'], 'prompt_ar' => ['required', 'string', 'max:3000'],
            'options_text' => ['nullable', 'required_if:type,single_choice,multiple_select', 'string'], 'options_ar_text' => ['nullable', 'required_if:type,single_choice,multiple_select', 'string'],
            'correct_option' => ['nullable', 'required_if:type,single_choice,true_false', 'integer', 'min:0'], 'correct_options_text' => ['nullable', 'required_if:type,multiple_select', 'string', 'max:100'], 'correct_answer' => ['nullable', 'required_if:type,short_answer,fill_in_blank', 'string', 'max:1000'], 'explanation' => ['required', 'string', 'max:3000'], 'explanation_ar' => ['required', 'string', 'max:3000'],
        ]);
        if (in_array($data['type'], ['short_answer', 'fill_in_blank'], true)) {
            $options = $optionsAr = [];
            $data['correct_option'] = null;
            $data['correct_options'] = null;
            $data['correct_answer'] = trim($data['correct_answer']);
        } else {
            [$options, $optionsAr] = $data['type'] === 'true_false'
                ? [['True', 'False'], ['صحيح', 'خطأ']]
                : [$this->lines($data['options_text']), $this->lines($data['options_ar_text'])];
            abort_unless(count($options) >= 2 && count($options) <= 20 && count($options) === count($optionsAr), 422, 'أدخل من 2 إلى 20 اختيارًا متطابقًا بالعربية والإنجليزية.');
            $data['correct_answer'] = null;
            if ($data['type'] === 'multiple_select') {
                $indices = preg_split('/[\s,،]+/u', trim($data['correct_options_text']), -1, PREG_SPLIT_NO_EMPTY);
                abort_unless($indices && collect($indices)->every(fn ($index) => ctype_digit($index) && (int) $index >= 1 && (int) $index <= count($options)), 422, 'حدد أرقام الاختيارات الصحيحة من 1 إلى عدد الاختيارات.');
                $data['correct_options'] = array_values(array_unique(array_map(fn ($index) => (int) $index - 1, $indices)));
                $data['correct_option'] = null;
            } else {
                abort_unless($data['correct_option'] < count($options), 422, 'حدد إجابة صحيحة من الاختيارات المتاحة.');
                $data['correct_options'] = null;
            }
        }
        unset($data['options_text'], $data['options_ar_text'], $data['correct_options_text']);

        return $data + ['options' => $options, 'options_ar' => $optionsAr];
    }

    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $value)))));
    }
}
