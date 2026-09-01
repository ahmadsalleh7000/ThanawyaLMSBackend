<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseScope;
use App\Models\AcademicSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'id' => $creating ? ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:courses,id'] : ['prohibited'],
            'title' => [$presence, 'string', 'max:255'],
            'title_en' => [$presence, 'string', 'max:255'],
            'summary' => [$presence, 'string', 'max:1000'],
            'summary_en' => [$presence, 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'description_en' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'category' => [$presence, 'string', 'max:100'],
            'scope' => [$presence, Rule::enum(CourseScope::class)],
            'academic_subject_id' => ['nullable', 'required_if:scope,academic', 'prohibited_if:scope,general', 'integer', Rule::exists('academic_subjects', 'id')->where('is_active', true)],
            'grade' => ['nullable', 'prohibited_if:scope,general', Rule::in(['grade-1', 'grade-2', 'grade-3'])],
            'semester' => ['nullable', 'prohibited_if:scope,general', Rule::in(['semester-1', 'semester-2'])],
            'unit' => ['nullable', 'string', 'max:150'],
            'track_type' => ['nullable', 'string', 'max:100'],
            'level' => [$presence, Rule::in(['Beginner', 'Intermediate', 'Advanced'])],
            'duration_minutes' => [$presence, 'integer', 'min:0', 'max:100000'],
            'price' => [$presence, 'numeric', 'min:0', 'max:999999.99'],
            'instructor' => [$presence, 'string', 'max:255'],
            'instructor_en' => [$presence, 'string', 'max:255'],
            'instructor_profile_id' => ['nullable', 'integer', 'exists:instructor_profiles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isMethod('post')) {
            return;
        }

        $scope = $this->input('scope', CourseScope::Academic->value);
        $prepared = ['scope' => $scope];
        if ($scope === CourseScope::Academic->value && ! $this->filled('academic_subject_id')) {
            $offering = AcademicSubject::query()->where('is_active', true)
                ->whereHas('term', fn ($query) => $query
                    ->where('code', $this->input('semester'))
                    ->whereHas('grade', fn ($grade) => $grade->where('code', $this->input('grade'))))
                ->first();
            if ($offering) {
                $prepared['academic_subject_id'] = $offering->id;
            }
        }
        $this->merge($prepared);
    }
}
