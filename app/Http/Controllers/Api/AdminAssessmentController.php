<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAssessmentController extends Controller
{
    public function show(Course $course): JsonResponse
    {
        $assessment = $course->assessment()->with('questions')->first();

        return response()->json(['data' => $assessment ? $this->serialize($assessment) : null]);
    }

    public function save(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        abort_if($course->archived_at, 409, 'Archived courses cannot be edited.');
        $assessment = $course->assessment;
        abort_if($assessment?->is_published, 409, 'Unpublish the assessment before changing its configuration.');
        $validated = $request->validate(['duration_minutes' => ['required', 'integer', 'min:1', 'max:180'], 'passing_score' => ['required', 'integer', 'min:1', 'max:100'], 'available_from' => ['nullable', 'date'], 'due_at' => ['nullable', 'date', 'after:available_from']]);
        $before = $assessment?->attributesToArray();
        $assessment = Assessment::updateOrCreate(['course_id' => $course->id], $validated + ['is_published' => false]);
        $audit->record($request, $before ? 'assessment.updated' : 'assessment.created', $assessment, $before);

        return response()->json(['data' => $this->serialize($assessment->load('questions'))], $before ? 200 : 201);
    }

    public function addQuestion(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $assessment = $this->draftAssessment($course);
        $validated = $this->validateQuestion($request, $assessment);
        $question = $assessment->questions()->create($validated);
        $audit->record($request, 'assessment_question.created', $question);

        return response()->json(['data' => $this->question($question)], 201);
    }

    public function updateQuestion(Request $request, Course $course, AssessmentQuestion $question, AuditService $audit): JsonResponse
    {
        $assessment = $this->draftAssessment($course);
        $this->ensureQuestion($assessment, $question);
        $before = $question->attributesToArray();
        $question->update($this->validateQuestion($request, $assessment, $question, false));
        $audit->record($request, 'assessment_question.updated', $question, $before);

        return response()->json(['data' => $this->question($question)]);
    }

    public function deleteQuestion(Request $request, Course $course, AssessmentQuestion $question, AuditService $audit): JsonResponse
    {
        $assessment = $this->draftAssessment($course);
        $this->ensureQuestion($assessment, $question);
        $before = $question->attributesToArray();
        $audit->record($request, 'assessment_question.deleted', $question, $before);
        $question->delete();

        return response()->json(status: 204);
    }

    public function publish(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $assessment = $course->assessment()->with('questions')->firstOrFail();
        abort_if($course->archived_at, 409, 'Archived courses cannot publish assessments.');
        abort_unless($assessment->questions->isNotEmpty(), 422, 'Add at least one question before publishing.');
        $before = $assessment->attributesToArray();
        $assessment->update(['is_published' => true]);
        $audit->record($request, 'assessment.published', $assessment, $before);

        return response()->json(['data' => $this->serialize($assessment)]);
    }

    public function unpublish(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        $before = $assessment->attributesToArray();
        $assessment->update(['is_published' => false]);
        $audit->record($request, 'assessment.unpublished', $assessment, $before);

        return response()->json(['data' => $this->serialize($assessment->load('questions'))]);
    }

    public function reorder(Request $request, Course $course, AuditService $audit): JsonResponse
    {
        $assessment = $this->draftAssessment($course);
        $ids = $request->validate(['question_ids' => ['required', 'array'], 'question_ids.*' => ['integer', 'distinct']])['question_ids'];
        abort_unless($assessment->questions()->whereIn('id', $ids)->count() === $assessment->questions()->count() && count($ids) === $assessment->questions()->count(), 422, 'Provide every assessment question exactly once.');
        DB::transaction(function () use ($assessment, $ids) {
            $assessment->questions()->update(['position' => DB::raw('position + 100000')]);
            foreach ($ids as $index => $id) {
                $assessment->questions()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
        $audit->record($request, 'assessment_questions.reordered', $assessment);

        return response()->json(['data' => $assessment->questions()->get()->map(fn ($question) => $this->question($question))]);
    }

    public function attempts(Request $request, Course $course): JsonResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        $query = $assessment->attempts();
        $count = (clone $query)->count();
        $passed = (clone $query)->where('passed', true)->count();
        $attempts = $query->with('user:id,name,email')->latest('submitted_at')->limit(min(max($request->integer('limit', 50), 1), 100))->get();

        return response()->json(['data' => [
            'summary' => ['attempts' => $count, 'averageScore' => $count ? round((float) $assessment->attempts()->avg('score'), 1) : 0, 'passRate' => $count ? round(($passed / $count) * 100, 1) : 0],
            'attempts' => $attempts->map(fn ($attempt) => ['id' => $attempt->id, 'student' => $attempt->user->only(['id', 'name', 'email']), 'score' => $attempt->score, 'passed' => $attempt->passed, 'submittedAt' => $attempt->submitted_at->toISOString()]),
        ]]);
    }

    private function validateQuestion(Request $request, Assessment $assessment, ?AssessmentQuestion $question = null, bool $creating = true): array
    {
        $presence = $creating ? 'required' : 'sometimes';
        $textType = in_array($request->input('type'), ['short_answer', 'fill_in_blank'], true);
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['multiple_choice', 'multiple_select', 'short_answer', 'fill_in_blank'])],
            'topic' => [$presence, 'string', 'max:150'], 'topic_ar' => [$presence, 'string', 'max:150'],
            'prompt' => [$presence, 'string', 'max:3000'], 'prompt_ar' => [$presence, 'string', 'max:3000'],
            'options' => [$textType ? 'nullable' : $presence, 'array', 'min:2', 'max:6'], 'options.*' => ['string', 'max:1000'],
            'options_ar' => [$textType ? 'nullable' : $presence, 'array', 'min:2', 'max:6'], 'options_ar.*' => ['string', 'max:1000'],
            'correct_option' => ['nullable', 'integer', 'min:0'],
            'correct_options' => ['nullable', 'array', 'min:2', 'max:6', 'distinct'],
            'correct_options.*' => ['integer', 'min:0'],
            'correct_answer' => ['nullable', 'string', 'max:1000'],
            'explanation' => [$presence, 'string', 'max:3000'], 'explanation_ar' => [$presence, 'string', 'max:3000'],
            'position' => [$presence, 'integer', 'min:1', Rule::unique('assessment_questions')->where('assessment_id', $assessment->id)->ignore($question?->id)],
        ]);
        $options = $validated['options'] ?? $question?->options ?? [];
        $optionsAr = $validated['options_ar'] ?? $question?->options_ar ?? [];
        $type = $validated['type'] ?? $question?->type ?? 'multiple_choice';
        $correct = $validated['correct_option'] ?? $question?->correct_option;
        $correctOptions = $validated['correct_options'] ?? $question?->correct_options ?? [];
        $correctAnswer = $validated['correct_answer'] ?? $question?->correct_answer;
        abort_unless(count($options) === count($optionsAr), 422, 'Arabic and English option counts must match.');
        if (in_array($type, ['short_answer', 'fill_in_blank'], true)) {
            abort_unless(filled($correctAnswer), 422, 'Text questions require a correct answer.');
            $validated['type'] = $type;
            $validated['correct_option'] = null;
            $validated['correct_options'] = null;
        } elseif ($type === 'multiple_select') {
            abort_unless(count($correctOptions) >= 2 && collect($correctOptions)->every(fn ($option) => $option < count($options)), 422, 'Multiple-select answers must reference available options.');
            $validated['correct_option'] = null;
            $validated['correct_options'] = array_values(array_unique(array_map('intval', $correctOptions)));
        } else {
            abort_unless($correct !== null && $correct < count($options), 422, 'The correct option must reference an available answer.');
            $validated['type'] = 'multiple_choice';
            $validated['correct_options'] = null;
        }

        return $validated;
    }

    private function draftAssessment(Course $course): Assessment
    {
        abort_if($course->archived_at, 409, 'Archived courses cannot be edited.');
        $assessment = $course->assessment()->firstOrFail();
        abort_if($assessment->is_published, 409, 'Unpublish the assessment before editing questions.');

        return $assessment;
    }

    private function ensureQuestion(Assessment $assessment, AssessmentQuestion $question): void
    {
        abort_unless($question->assessment_id === $assessment->id, 404);
    }

    private function serialize(Assessment $assessment): array
    {
        return ['id' => $assessment->id, 'courseId' => $assessment->course_id, 'durationMinutes' => $assessment->duration_minutes, 'passingScore' => $assessment->passing_score, 'isPublished' => $assessment->is_published, 'availableFrom' => $assessment->available_from?->toISOString(), 'dueAt' => $assessment->due_at?->toISOString(), 'questions' => $assessment->relationLoaded('questions') ? $assessment->questions->map(fn ($question) => $this->question($question)) : []];
    }

    private function question(AssessmentQuestion $question): array
    {
        return ['id' => $question->id, 'type' => $question->type ?: 'multiple_choice', 'topic' => $question->topic, 'topicAr' => $question->topic_ar, 'prompt' => $question->prompt, 'promptAr' => $question->prompt_ar, 'options' => $question->options, 'optionsAr' => $question->options_ar, 'correctOption' => $question->correct_option, 'correctOptions' => $question->correct_options, 'correctAnswer' => $question->correct_answer, 'explanation' => $question->explanation, 'explanationAr' => $question->explanation_ar, 'position' => $question->position];
    }
}
