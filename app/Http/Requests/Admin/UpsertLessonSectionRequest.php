<?php

namespace App\Http\Requests\Admin;

use App\Models\LessonSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertLessonSectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('items_text', '')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
        $tableRows = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('table_text', '')))
            ->map(fn ($row) => array_values(array_filter(array_map('trim', explode('|', $row)), fn ($cell) => $cell !== '')))
            ->filter(fn ($row) => count($row) > 1)
            ->values()
            ->all();
        $questionOptions = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('question_options_text', '')))
            ->map(fn ($option) => trim($option))
            ->filter()
            ->values()
            ->all();
        $groupQuestions = collect(preg_split('/\r\n|\r|\n/', (string) $this->input('question_group_text', '')))
            ->map(fn ($prompt) => trim($prompt))
            ->filter()
            ->values()
            ->map(fn ($prompt) => ['prompt' => $prompt])
            ->all();

        $settings = [];
        foreach (array_keys(LessonSection::DEFAULT_SETTINGS) as $key) {
            $settings[$key] = $this->boolean("settings.{$key}");
        }

        $this->merge([
            'content' => [
                'text' => trim((string) $this->input('content_text', '')),
                'items' => $items,
                'language' => trim((string) $this->input('language', '')),
                'formula' => trim((string) $this->input('formula', '')),
                'common_mistake' => [
                    'mistake' => trim((string) $this->input('mistake', '')),
                    'correction' => trim((string) $this->input('correction', '')),
                ],
                'worked_example' => [
                    'problem' => trim((string) $this->input('worked_problem', '')),
                    'thinking' => trim((string) $this->input('worked_thinking', '')),
                    'solution' => trim((string) $this->input('worked_solution', '')),
                    'explanation' => trim((string) $this->input('worked_explanation', '')),
                    'key_lesson' => trim((string) $this->input('worked_key_lesson', '')),
                ],
                'code_example' => [
                    'problem' => trim((string) $this->input('code_problem', '')),
                    'logic' => trim((string) $this->input('code_logic', '')),
                    'code' => trim((string) $this->input('code_body', '')),
                    'explanation' => trim((string) $this->input('code_explanation', '')),
                    'expected_output' => trim((string) $this->input('code_expected_output', '')),
                    'try_it' => trim((string) $this->input('code_try_it', '')),
                ],
                'table' => [
                    'rows' => $tableRows,
                ],
                'image' => [
                    'media_asset_id' => $this->filled('image_media_asset_id') ? (int) $this->input('image_media_asset_id') : null,
                    'url' => trim((string) $this->input('image_url', '')),
                    'alt' => trim((string) $this->input('image_alt', '')),
                    'caption' => trim((string) $this->input('image_caption', '')),
                    'alignment' => trim((string) $this->input('image_alignment', 'center')),
                    'print_width' => trim((string) $this->input('image_print_width', 'medium')),
                ],
                'question' => [
                    'type' => trim((string) $this->input('question_type', 'short_answer')),
                    'prompt' => trim((string) $this->input('question_prompt', '')),
                    'options' => $questionOptions,
                    'answer' => trim((string) $this->input('question_answer', '')),
                    'explanation' => trim((string) $this->input('question_explanation', '')),
                    'answer_space' => trim((string) $this->input('question_answer_space', 'medium')),
                ],
                'question_group' => [
                    'questions' => $groupQuestions,
                    'answer_space' => trim((string) $this->input('question_group_answer_space', 'medium')),
                ],
            ],
            'settings' => $settings,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(LessonSection::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['array'],
            'content.text' => ['nullable', 'string', 'max:20000'],
            'content.items' => ['array'],
            'content.items.*' => ['string', 'max:1000'],
            'content.language' => ['nullable', 'string', 'max:40'],
            'content.formula' => ['nullable', 'string', 'max:5000'],
            'content.common_mistake' => ['array'],
            'content.common_mistake.mistake' => ['nullable', 'string', 'max:5000'],
            'content.common_mistake.correction' => ['nullable', 'string', 'max:5000'],
            'content.worked_example' => ['array'],
            'content.worked_example.problem' => ['nullable', 'string', 'max:8000'],
            'content.worked_example.thinking' => ['nullable', 'string', 'max:8000'],
            'content.worked_example.solution' => ['nullable', 'string', 'max:8000'],
            'content.worked_example.explanation' => ['nullable', 'string', 'max:8000'],
            'content.worked_example.key_lesson' => ['nullable', 'string', 'max:5000'],
            'content.code_example' => ['array'],
            'content.code_example.problem' => ['nullable', 'string', 'max:8000'],
            'content.code_example.logic' => ['nullable', 'string', 'max:8000'],
            'content.code_example.code' => ['nullable', 'string', 'max:20000'],
            'content.code_example.explanation' => ['nullable', 'string', 'max:8000'],
            'content.code_example.expected_output' => ['nullable', 'string', 'max:8000'],
            'content.code_example.try_it' => ['nullable', 'string', 'max:8000'],
            'content.table' => ['array'],
            'content.table.rows' => ['array'],
            'content.table.rows.*' => ['array'],
            'content.table.rows.*.*' => ['string', 'max:500'],
            'content.image' => ['array'],
            'content.image.media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'content.image.url' => ['nullable', 'url', 'max:2000'],
            'content.image.alt' => ['nullable', 'string', 'max:255'],
            'content.image.caption' => ['nullable', 'string', 'max:500'],
            'content.image.alignment' => ['nullable', 'string', Rule::in(['start', 'center', 'end'])],
            'content.image.print_width' => ['nullable', 'string', Rule::in(['small', 'medium', 'large', 'full'])],
            'content.question' => ['array'],
            'content.question.type' => ['nullable', 'string', Rule::in(['multiple_choice', 'true_false', 'short_answer', 'complete', 'matching', 'essay'])],
            'content.question.prompt' => ['nullable', 'string', 'max:8000'],
            'content.question.options' => ['array'],
            'content.question.options.*' => ['string', 'max:1000'],
            'content.question.answer' => ['nullable', 'string', 'max:8000'],
            'content.question.explanation' => ['nullable', 'string', 'max:8000'],
            'content.question.answer_space' => ['nullable', 'string', Rule::in(['none', 'small', 'medium', 'large'])],
            'content.question_group' => ['array'],
            'content.question_group.questions' => ['array'],
            'content.question_group.questions.*.prompt' => ['string', 'max:8000'],
            'content.question_group.answer_space' => ['nullable', 'string', Rule::in(['none', 'small', 'medium', 'large'])],
            'settings' => ['array'],
            'settings.show_on_web' => ['boolean'],
            'settings.show_in_workbook' => ['boolean'],
            'settings.show_in_teacher_edition' => ['boolean'],
            'settings.avoid_page_break_inside' => ['boolean'],
            'settings.page_break_before' => ['boolean'],
            'settings.page_break_after' => ['boolean'],
        ];
    }
}
