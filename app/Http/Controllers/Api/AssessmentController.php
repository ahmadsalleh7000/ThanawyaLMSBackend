<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentDraft;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions')->firstOrFail();
        $this->ensureAvailable($assessment);
        $questions = $this->questionsFor($assessment, $request->user()->id);

        $draft = $assessment->drafts()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'id' => $assessment->id, 'courseId' => $course->id, 'duration' => $assessment->duration_minutes,
            'passingScore' => $assessment->passing_score,
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id, 'type' => $question->type ?: 'multiple_choice', 'topic' => $question->topic, 'topicAr' => $question->topic_ar,
                'difficulty' => $question->difficulty,
                'prompt' => $question->prompt, 'promptAr' => $question->prompt_ar,
                'options' => $question->options, 'optionsAr' => $question->options_ar,
            ]),
            'draft' => $draft ? $this->draftPayload($draft) : null,
            'attempts' => $assessment->attempts()->where('user_id', $request->user()->id)->latest('submitted_at')->limit(5)->get()
                ->map(fn ($attempt) => ['id' => $attempt->id, 'score' => $attempt->score, 'passed' => $attempt->passed, 'submittedAt' => $attempt->submitted_at?->toISOString()]),
        ]);
    }

    public function start(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->firstOrFail();
        $this->ensureAvailable($assessment);
        $draft = AssessmentDraft::firstOrCreate(
            ['assessment_id' => $assessment->id, 'user_id' => $request->user()->id],
            ['answers' => [], 'current_index' => 0, 'started_at' => now(), 'expires_at' => now()->addMinutes($assessment->duration_minutes)],
        );

        if ($draft->expires_at->isPast()) {
            $draft->update(['answers' => [], 'current_index' => 0, 'started_at' => now(), 'expires_at' => now()->addMinutes($assessment->duration_minutes)]);
        }

        return response()->json(['data' => $this->draftPayload($draft->fresh())]);
    }

    public function saveDraft(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions:id,assessment_id')->firstOrFail();
        $this->ensureAvailable($assessment);
        $draft = $assessment->drafts()->where('user_id', $request->user()->id)->firstOrFail();
        abort_if($draft->expires_at->isPast(), 422, 'Assessment time has expired.');
        $validated = $request->validate(['answers' => ['required', 'array'], 'current_index' => ['required', 'integer', 'min:0']]);
        $questions = $this->questionsFor($assessment, $request->user()->id);
        $draft->update(['answers' => $this->normalizeAnswers($validated['answers'], $questions), 'current_index' => min($validated['current_index'], max($questions->count() - 1, 0))]);

        return response()->json(['data' => $this->draftPayload($draft->fresh())]);
    }

    public function submit(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions')->firstOrFail();
        $this->ensureAvailable($assessment);
        $questions = $this->questionsFor($assessment, $request->user()->id);
        $draft = $assessment->drafts()->where('user_id', $request->user()->id)->first();
        $validated = $request->validate(['answers' => ['required', 'array']]);
        $answers = $draft?->expires_at?->isPast() ? ($draft->answers ?? []) : $this->normalizeAnswers($validated['answers'], $questions);
        $correct = $questions->filter(fn ($question) => $this->answerIsCorrect($question, $answers[$question->id] ?? null))->count();
        $score = (int) round(($correct / max($questions->count(), 1)) * 100);
        $passed = $score >= $assessment->passing_score;

        [$attempt, $certificate] = DB::transaction(function () use ($request, $course, $assessment, $questions, $answers, $score, $passed) {
            $submittedAnswers = collect($answers)->only($questions->pluck('id')->map(fn ($id) => (string) $id)->all())->all();
            $attempt = AssessmentAttempt::create(['assessment_id' => $assessment->id, 'user_id' => $request->user()->id, 'answers' => $submittedAnswers, 'score' => $score, 'passed' => $passed, 'submitted_at' => now()]);
            $assessment->drafts()->where('user_id', $request->user()->id)->delete();
            $lessonIds = $course->lessons()->pluck('id');
            $completed = $request->user()->lessonCompletions()->whereIn('lesson_id', $lessonIds)->count();
            $certificate = null;
            if ($passed && $lessonIds->isNotEmpty() && $completed === $lessonIds->count()) {
                $certificate = Certificate::firstOrCreate(['user_id' => $request->user()->id, 'course_id' => $course->id], ['public_id' => (string) Str::uuid(), 'assessment_attempt_id' => $attempt->id, 'issued_at' => now()]);
            }

            return [$attempt, $certificate];
        });

        return response()->json([
            'attemptId' => $attempt->id, 'score' => $score, 'correct' => $correct,
            'total' => $questions->count(), 'passed' => $passed,
            'certificateId' => $certificate?->public_id,
            'review' => $questions->map(fn ($question) => [
                'questionId' => $question->id, 'selected' => $answers[$question->id] ?? null,
                'correct' => $this->answerIsCorrect($question, $answers[$question->id] ?? null),
                'correctOption' => $question->correct_option, 'correctOptions' => $question->correct_options, 'correctAnswer' => $question->correct_answer, 'explanation' => $question->explanation,
                'explanationAr' => $question->explanation_ar,
            ]),
        ]);
    }

    private function draftPayload(AssessmentDraft $draft): array
    {
        return ['answers' => $draft->answers ?? [], 'currentIndex' => $draft->current_index, 'startedAt' => $draft->started_at->toISOString(), 'expiresAt' => $draft->expires_at->toISOString()];
    }

    private function ensureEnrolled(Request $request, Course $course): void
    {
        abort_unless($this->access->canAccessCourse($request->user(), $course), 403, 'An active subscription is required.');
    }

    private function ensureAvailable(Assessment $assessment): void
    {
        abort_if($assessment->available_from?->isFuture(), 403, 'Assessment is not available yet.');
        abort_if($assessment->due_at?->isPast(), 410, 'Assessment deadline has passed.');
    }

    private function questionsFor(Assessment $assessment, int $userId)
    {
        $questions = $assessment->questions;
        if ($assessment->randomize_questions) {
            $questions = $questions->sortBy(fn ($question) => sprintf('%u', crc32($assessment->id.':'.$userId.':'.$question->id)))->values();
        }

        return $assessment->question_limit ? $questions->take($assessment->question_limit)->values() : $questions->values();
    }

    private function normalizeAnswers(array $answers, $questions): array
    {
        $normalized = [];
        foreach ($questions as $question) {
            $value = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            if ($question->type === 'multiple_select') {
                if (! is_array($value)) {
                    continue;
                }
                $value = array_values(array_unique(array_map('intval', $value)));
                sort($value);
                $normalized[$question->id] = $value;
            } elseif (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
                if (is_string($value) && trim($value) !== '') {
                    $normalized[$question->id] = trim($value);
                }
            } elseif (is_numeric($value)) {
                $normalized[$question->id] = (int) $value;
            }
        }

        return $normalized;
    }

    private function answerIsCorrect($question, mixed $answer): bool
    {
        if ($question->type === 'multiple_select') {
            if (! is_array($answer)) {
                return false;
            }
            $expected = array_map('intval', $question->correct_options ?? []);
            sort($expected);
            $answer = array_map('intval', $answer);
            sort($answer);

            return $answer === $expected;
        }

        if (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
            return is_string($answer) && $this->normalizeTextAnswer($answer) === $this->normalizeTextAnswer((string) $question->correct_answer);
        }

        return $answer !== null && (int) $answer === (int) $question->correct_option;
    }

    private function normalizeTextAnswer(string $answer): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($answer)));
    }
}
