<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\Course;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAssessmentController extends Controller
{
    public function editQuestion(Course $course, AssessmentQuestion $question): View
    {
        $this->ensureQuestion($course, $question);

        return view('admin.courses.question', ['course' => $course, 'question' => $question]);
    }

    public function store(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment;
        abort_if($assessment?->is_published, 409, 'Unpublish before editing.');
        $data = $request->validate(['duration_minutes' => ['required', 'integer', 'min:1', 'max:180'], 'passing_score' => ['required', 'integer', 'min:1', 'max:100'], 'available_from' => ['nullable', 'date'], 'due_at' => ['nullable', 'date', 'after:available_from'], 'randomize_questions' => ['sometimes', 'boolean'], 'question_limit' => ['nullable', 'integer', 'min:1', 'max:200']]);
        $data['randomize_questions'] = $request->boolean('randomize_questions');
        $before = $assessment?->attributesToArray();
        $assessment = Assessment::updateOrCreate(['course_id' => $course->id], $data + ['is_published' => false]);
        $audit->record($request, $before ? 'assessment.updated' : 'assessment.created', $assessment, $before);

        return back()->with('success', 'تم حفظ إعدادات التقييم.');
    }

    public function toggleState(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment()->with('questions')->firstOrFail();
        if (! $assessment->is_published) {
            abort_unless($assessment->questions->isNotEmpty(), 422, 'Add a question before publishing.');
        }
        $before = $assessment->attributesToArray();
        $assessment->update(['is_published' => ! $assessment->is_published]);
        $audit->record($request, $assessment->is_published ? 'assessment.published' : 'assessment.unpublished', $assessment, $before);

        return back()->with('success', 'تم تحديث حالة التقييم.');
    }

    public function delivery(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        abort_if($assessment->is_published, 409, 'Unpublish before changing delivery settings.');
        $data = $request->validate(['randomize_questions' => ['sometimes', 'boolean'], 'question_limit' => ['nullable', 'integer', 'min:1', 'max:200']]);
        $before = $assessment->attributesToArray();
        $assessment->update(['randomize_questions' => $request->boolean('randomize_questions'), 'question_limit' => $data['question_limit'] ?? null]);
        $audit->record($request, 'assessment.delivery_updated', $assessment, $before);

        return back()->with('success', 'تم تحديث طريقة عرض الأسئلة.');
    }

    public function schedule(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        $data = $request->validate([
            'available_from' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
        abort_if(
            ! empty($data['available_from']) && ! empty($data['due_at']) && $data['due_at'] <= $data['available_from'],
            422,
            'The due date must be after the availability date.',
        );
        $before = $assessment->only(['available_from', 'due_at']);
        $assessment->update($data);
        $audit->record($request, 'assessment.schedule_updated', $assessment, $before);

        return back()->with('success', 'تم تحديث موعد التقييم.');
    }

    public function storeQuestion(Request $request, Course $course, AuditService $audit): RedirectResponse
    {
        $assessment = $course->assessment()->firstOrFail();
        abort_if($assessment->is_published, 409, 'Unpublish before editing questions.');
        $question = $assessment->questions()->create($this->questionData($request, $assessment));
        $audit->record($request, 'assessment_question.created', $question);

        return back()->with('success', 'تمت إضافة السؤال.');
    }

    public function destroyQuestion(Request $request, Course $course, AssessmentQuestion $question, AuditService $audit): RedirectResponse
    {
        abort_unless($question->assessment?->course_id === $course->id, 404);
        abort_if($question->assessment->is_published, 409);
        $before = $question->attributesToArray();
        $audit->record($request, 'assessment_question.deleted', $question, $before);
        $question->delete();

        return back()->with('success', 'تم حذف السؤال.');
    }

    public function updateQuestion(Request $request, Course $course, AssessmentQuestion $question, AuditService $audit): RedirectResponse
    {
        $this->ensureQuestion($course, $question);
        abort_if($question->assessment->is_published, 409, 'Unpublish before editing questions.');
        $data = $this->questionData($request, $question->assessment, $question);
        $before = $question->attributesToArray();
        $question->update($data);
        $audit->record($request, 'assessment_question.updated', $question, $before);

        return back()->with('success', 'تم تحديث السؤال.');
    }

    public function moveQuestion(Request $request, Course $course, AssessmentQuestion $question, string $direction, AuditService $audit): RedirectResponse
    {
        $this->ensureQuestion($course, $question);
        abort_if($question->assessment->is_published, 409);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $adjacent = $question->assessment->questions()->where('position', $operator, $question->position)->orderBy('position', $order)->first();
        if (! $adjacent) {
            return back();
        }
        $before = ['question' => $question->attributesToArray(), 'adjacent' => $adjacent->attributesToArray()];
        $adjacentPosition = $adjacent->position;
        DB::transaction(function () use ($question, $adjacent, $adjacentPosition): void {
            $position = $question->position;
            $question->update(['position' => 1000000]);
            $adjacent->update(['position' => $position]);
            $question->update(['position' => $adjacentPosition]);
        });
        $audit->record($request, 'assessment_questions.reordered', $question->assessment, $before);

        return back()->with('success', 'تم تحديث ترتيب الأسئلة.');
    }

    public function attempts(Course $course): View
    {
        $assessment = $course->assessment()->firstOrFail();
        $query = $assessment->attempts();
        $count = (clone $query)->count();
        $passed = (clone $query)->where('passed', true)->count();

        $attemptCollection = $assessment->attempts()->get(['answers']);
        $questionPerformance = $assessment->questions->map(function (AssessmentQuestion $question) use ($attemptCollection): array {
            $answered = $attemptCollection->filter(fn ($attempt) => array_key_exists((string) $question->id, $attempt->answers) || array_key_exists($question->id, $attempt->answers));
            $correct = $answered->filter(fn ($attempt) => (int) ($attempt->answers[$question->id] ?? $attempt->answers[(string) $question->id]) === $question->correct_option)->count();

            return ['question' => $question, 'answered' => $answered->count(), 'accuracy' => $answered->isEmpty() ? 0 : round($correct / $answered->count() * 100, 1)];
        })->sortBy('accuracy')->values();

        return view('admin.courses.attempts', [
            'course' => $course,
            'assessment' => $assessment,
            'attempts' => $query->with('user:id,name,email')->latest('submitted_at')->paginate(30),
            'summary' => [
                'attempts' => $count,
                'average' => $count ? round((float) $assessment->attempts()->avg('score'), 1) : 0,
                'passRate' => $count ? round(($passed / $count) * 100, 1) : 0,
            ],
            'questionPerformance' => $questionPerformance,
        ]);
    }

    private function questionData(Request $request, Assessment $assessment, ?AssessmentQuestion $question = null): array
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['single_choice', 'true_false'])], 'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])], 'topic' => ['required', 'string', 'max:150'], 'topic_ar' => ['required', 'string', 'max:150'], 'prompt' => ['required', 'string', 'max:3000'], 'prompt_ar' => ['required', 'string', 'max:3000'], 'options_text' => ['nullable', 'required_unless:type,true_false', 'string'], 'options_ar_text' => ['nullable', 'required_unless:type,true_false', 'string'], 'correct_option' => ['required', 'integer', 'min:0'], 'explanation' => ['required', 'string', 'max:3000'], 'explanation_ar' => ['required', 'string', 'max:3000'], 'position' => ['required', 'integer', 'min:1', Rule::unique('assessment_questions')->where('assessment_id', $assessment->id)->ignore($question?->id)]]);
        $data['type'] ??= 'single_choice';
        $data['difficulty'] ??= 'medium';
        [$options, $optionsAr] = $data['type'] === 'true_false' ? [['True', 'False'], ['صحيح', 'خطأ']] : [$this->lines($data['options_text']), $this->lines($data['options_ar_text'])];
        abort_unless(count($options) >= 2 && count($options) === count($optionsAr), 422, 'Arabic and English options must match.');
        abort_unless($data['correct_option'] < count($options), 422, 'Invalid correct answer.');
        unset($data['options_text'], $data['options_ar_text']);

        return $data + ['options' => $options, 'options_ar' => $optionsAr];
    }

    private function ensureQuestion(Course $course, AssessmentQuestion $question): void
    {
        abort_unless($question->assessment?->course_id === $course->id, 404);
    }

    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $value)))));
    }
}
