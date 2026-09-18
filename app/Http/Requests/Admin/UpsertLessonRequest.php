<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertLessonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['transcript', 'transcript_en', 'objectives', 'key_points'] as $field) {
            if ($this->has($field.'_text')) {
                $text = $this->normalizeUtf8String((string) $this->input($field.'_text'));
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
                $this->merge([$field => $lines ?: null]);
            } elseif ($this->has($field) && is_array($this->input($field))) {
                $this->merge([$field => $this->normalizeUtf8Value($this->input($field))]);
            }
        }

        foreach (['summary', 'content_pdf_title', 'content_pdf_description'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => $this->normalizeUtf8String((string) $this->input($field))]);
            }
        }

        if ($this->has('captions_text')) {
            $captions = collect(preg_split('/\R/', (string) $this->input('captions_text', '')))
                ->map(fn ($line) => trim($line))->filter()
                ->map(function (string $line): array {
                    [$timing, $text] = array_pad(explode('|', $line, 2), 2, '');
                    [$start, $end] = array_pad(explode('-->', trim($timing), 2), 2, '');

                    return ['start' => trim($start), 'end' => trim($end), 'text' => trim($text)];
                })->values()->all();
            $this->merge(['captions' => $captions ?: null]);
        }

        if ($this->has('completion_rules')) {
            $rules = (array) $this->input('completion_rules', []);
            $this->merge(['completion_rules' => [
                'require_content_opened' => filter_var($rules['require_content_opened'] ?? false, FILTER_VALIDATE_BOOL),
                'require_practice_passed' => filter_var($rules['require_practice_passed'] ?? false, FILTER_VALIDATE_BOOL),
                'minimum_video_seconds' => max(0, (int) ($rules['minimum_video_seconds'] ?? 0)),
            ]]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');
        $lesson = $this->route('lesson');
        $creating = $this->isMethod('post');
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'slug' => [$presence, 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('lessons')->where('course_id', $course->id)->ignore($lesson instanceof Lesson ? $lesson->id : null)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where(fn ($query) => $query
                ->where('academic_subject_id', $course->academic_subject_id)
                ->where(fn ($unitQuery) => $unitQuery->whereNull('course_id')->orWhere('course_id', $course->id)))],
            'title' => [$presence, 'string', 'max:255'],
            'title_en' => [$presence, 'string', 'max:255'],
            'duration_minutes' => [$presence, 'integer', 'min:1', 'max:1440'],
            'position' => [$presence, 'integer', 'min:1', Rule::unique('lessons')->where('course_id', $course->id)->ignore($lesson instanceof Lesson ? $lesson->id : null)],
            'is_preview' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'content_pdf_enabled' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'content_pdf_media_id' => ['nullable', Rule::exists('media_assets', 'id')->where(fn ($query) => $query
                ->where('lesson_id', $lesson instanceof Lesson ? $lesson->id : 0)
                ->whereIn('kind', ['lesson_pdf', 'resource'])
                ->where('mime_type', 'application/pdf'))],
            'content_pdf_url' => ['nullable', 'url', 'max:2048'],
            'content_pdf_title' => ['nullable', 'string', 'max:255'],
            'content_pdf_description' => ['nullable', 'string', 'max:1000'],
            'workbook_enabled' => ['sometimes', 'boolean'],
            'allow_print' => ['sometimes', 'boolean'],
            'allow_pdf_download' => ['sometimes', 'boolean'],
            'include_workbook_cover' => ['sometimes', 'boolean'],
            'include_workbook_info' => ['sometimes', 'boolean'],
            'include_workbook_toc' => ['sometimes', 'boolean'],
            'include_workbook_answer_key' => ['sometimes', 'boolean'],
            'objectives' => ['nullable', 'array'],
            'objectives.*' => ['string', 'max:500'],
            'key_points' => ['nullable', 'array'],
            'key_points.*' => ['string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'completion_rules' => ['nullable', 'array'],
            'completion_rules.require_content_opened' => ['sometimes', 'boolean'],
            'completion_rules.require_practice_passed' => ['sometimes', 'boolean'],
            'completion_rules.minimum_video_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'transcript' => ['nullable', 'array'],
            'transcript.*' => ['string', 'max:1000'],
            'transcript_en' => ['nullable', 'array'],
            'transcript_en.*' => ['string', 'max:1000'],
            'captions' => ['nullable', 'array', 'max:500'],
            'captions.*.start' => ['required', 'date_format:H:i:s.v'],
            'captions.*.end' => ['required', 'date_format:H:i:s.v'],
            'captions.*.text' => ['required', 'string', 'max:1000'],
        ];
    }

    private function normalizeUtf8String(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $converted === false ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $converted;
    }

    private function normalizeUtf8Value(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeUtf8String($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeUtf8Value($item), $value);
        }

        return $value;
    }
}
