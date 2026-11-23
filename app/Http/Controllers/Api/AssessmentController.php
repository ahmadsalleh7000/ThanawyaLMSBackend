<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentDraft;
use App\Models\Course;
use App\Services\ExamDeliveryService;
use App\Services\ExamGradingService;
use App\Services\ExamSubmissionService;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access, private readonly ExamDeliveryService $delivery, private readonly ExamGradingService $grading, private readonly ExamSubmissionService $submissions) {}

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions')->firstOrFail();
        $this->ensureAvailable($assessment, $request->user()->id);
        $draft = $assessment->drafts()->where('user_id', $request->user()->id)->first();
        $questions = $this->delivery->questions($assessment, $request->user()->id, $draft?->question_snapshot);

        return response()->json([
            'id' => $assessment->id, 'courseId' => $course->id, 'duration' => $assessment->duration_minutes,
            'passingScore' => $assessment->passing_score,
            'policy' => $this->delivery->state($assessment, $request->user()->id),
            'allowQuestionNavigation' => $assessment->allow_question_navigation,
            'requireAllAnswers' => $assessment->require_all_answers,
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id, 'type' => $question->type ?: 'multiple_choice', 'topic' => $question->topic, 'topicAr' => $question->topic_ar,
                'difficulty' => $question->difficulty,
                'prompt' => $question->prompt, 'promptAr' => $question->prompt_ar,
                'options' => $question->options, 'optionsAr' => $question->options_ar,
            ]),
            'draft' => $draft ? $this->draftPayload($draft) : null,
            'attempts' => $assessment->attempts()->where('user_id', $request->user()->id)->latest('submitted_at')->limit(5)->get()
                ->map(fn ($attempt) => ['id' => $attempt->id, 'score' => $attempt->effective_score, 'passed' => $attempt->passed, 'submittedAt' => $attempt->submitted_at?->toISOString()]),
        ]);
    }

    public function start(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->firstOrFail();
        $this->ensureAvailable($assessment, $request->user()->id);
        $draft = DB::transaction(function () use ($assessment, $request) {
            $assessment = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if ($existing = $assessment->drafts()->where('user_id', $request->user()->id)->first()) {
                return $existing;
            }
            $this->delivery->ensureCanAttempt($assessment, $request->user()->id);
            $questions = $this->questionsFor($assessment, $request->user()->id);
            abort_if($questions->isEmpty(), 422, 'No enabled questions are available.');

            // An expired draft must be submitted before another attempt starts.
            return AssessmentDraft::firstOrCreate(
                ['assessment_id' => $assessment->id, 'user_id' => $request->user()->id],
                ['question_snapshot' => $this->delivery->snapshot($questions), 'answers' => [], 'current_index' => 0, 'started_at' => now(), 'expires_at' => $assessment->due_at && $assessment->due_at->lt(now()->addMinutes($assessment->duration_minutes)) ? $assessment->due_at : now()->addMinutes($assessment->duration_minutes)],
            );
        });

        return response()->json(['data' => $this->draftPayload($draft->fresh())]);
    }

    public function saveDraft(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions')->firstOrFail();
        $this->ensureAvailable($assessment, $request->user()->id);
        $validated = $request->validate(['answers' => ['present', 'array'], 'current_index' => ['required', 'integer', 'min:0'], 'draft_id' => ['nullable', 'integer', 'min:1']]);
        $draft = DB::transaction(function () use ($assessment, $request, $validated) {
            Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $draft = $assessment->drafts()->where('user_id', $request->user()->id)->when($validated['draft_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->firstOrFail();
            abort_if($draft->expires_at->lte(now()), 422, __('Assessment time has expired.'));
            $questions = $this->delivery->questions($assessment, $request->user()->id, $draft->question_snapshot);
            abort_if(! $assessment->allow_question_navigation && ($validated['current_index'] < $draft->current_index || $validated['current_index'] > $draft->current_index + 1), 422, 'Sequential question navigation is required.');
            $answers = $this->grading->draftAnswers($validated['answers'], $questions, $draft, $assessment->allow_question_navigation);
            abort_if(! $assessment->allow_question_navigation && $assessment->require_all_answers && $validated['current_index'] > $draft->current_index && ! array_key_exists($questions[$draft->current_index]->id, $answers), 422, 'Answer this question before continuing.');
            $draft->update(['answers' => $answers, 'current_index' => min($validated['current_index'], max($questions->count() - 1, 0))]);

            return $draft;
        });

        return response()->json(['data' => $this->draftPayload($draft->fresh())]);
    }

    public function submit(Request $request, Course $course): JsonResponse
    {
        $this->ensureEnrolled($request, $course);
        $assessment = Assessment::whereBelongsTo($course)->where('is_published', true)->with('questions')->firstOrFail();
        $this->ensureAvailable($assessment, $request->user()->id, $request->integer('draft_id') ?: null);
        $validated = $request->validate(['answers' => ['present', 'array'], 'draft_id' => ['nullable', 'integer', 'min:1']]);
        $attempt = $this->submissions->submit($assessment, $request->user()->id, $validated['answers'], $validated['draft_id'] ?? null);

        return response()->json($this->submissions->result($assessment, $attempt));
    }

    private function draftPayload(AssessmentDraft $draft): array
    {
        return ['id' => $draft->id, 'answers' => $draft->answers ?? [], 'currentIndex' => $draft->current_index, 'startedAt' => $draft->started_at->toISOString(), 'expiresAt' => $draft->expires_at->toISOString()];
    }

    private function ensureEnrolled(Request $request, Course $course): void
    {
        abort_unless($this->access->canAccessCourse($request->user(), $course), 403, __('An active subscription is required.'));
    }

    private function ensureAvailable(Assessment $assessment, int $userId, ?int $draftId = null): void
    {
        abort_if($assessment->available_from?->isFuture(), 403, __('Assessment is not available yet.'));
        abort_if($assessment->due_at?->isPast() && ! $assessment->drafts()->where('user_id', $userId)->exists() && ! ($draftId && $assessment->attempts()->where('user_id', $userId)->where('draft_id', $draftId)->exists()), 410, __('Assessment deadline has passed.'));
    }

    private function questionsFor(Assessment $assessment, int $userId)
    {
        return $this->delivery->questions($assessment, $userId);
    }
}
