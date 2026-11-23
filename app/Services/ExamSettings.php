<?php

namespace App\Services;

class ExamSettings
{
    public const BOOLEANS = ['randomize_questions', 'randomize_options', 'allow_retake_after_pass', 'show_correct_answers', 'show_explanations', 'require_all_answers'];

    public static function rules(bool $assessment = true): array
    {
        $rules = [
            'max_retries' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'retry_delay_minutes' => ['sometimes', 'integer', 'min:0', 'max:525600'],
            'question_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
        ];
        if (! $assessment) {
            $rules['duration_minutes'] = ['sometimes', 'nullable', 'integer', 'min:1', 'max:180'];
        }
        foreach (array_merge(self::BOOLEANS, ['allow_question_navigation']) as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
