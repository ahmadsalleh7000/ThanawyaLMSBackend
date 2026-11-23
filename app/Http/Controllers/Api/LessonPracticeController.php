<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\TrainingDraft;
use App\Models\User;
use App\Services\ExamDeliveryService;
use App\Services\ExamGradingService;
use App\Services\ExamSubmissionService;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonPracticeController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access, private readonly ExamDeliveryService $delivery, private readonly ExamGradingService $grading, private readonly ExamSubmissionService $submissions) {}

    public function show(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $lessonModel = $this->lesson($request, $course, $lesson);
        $this->ensureTraining($training, $lessonModel, $request->user());
        $training->load(['questions', 'quiz.questions']);

        return response()->json(['data' => $this->practicePayload($training, $request->user()->id)]);
    }

    public function start(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $this->ensureTraining($training, $this->lesson($request, $course, $lesson), $request->user());
        $draft = DB::transaction(function () use ($training, $request) {
            $training = Training::whereKey($training->id)->lockForUpdate()->firstOrFail();
            if ($existing = $training->drafts()->where('student_id', $request->user()->id)->first()) {
                return $existing;
            }
            $this->delivery->ensureCanAttempt($training, $request->user()->id);
            $questions = $this->delivery->questions($training, $request->user()->id);
            abort_if($questions->isEmpty(), 422, 'No enabled questions are available.');
            $expires = $training->duration_minutes ? now()->addMinutes($training->duration_minutes) : null;
            if ($training->available_until && (! $expires || $training->available_until->lt($expires))) {
                $expires = $training->available_until;
            }

            return TrainingDraft::firstOrCreate(
                ['training_id' => $training->id, 'student_id' => $request->user()->id],
                ['question_snapshot' => $this->delivery->snapshot($questions), 'answers' => [], 'current_index' => 0, 'started_at' => now(), 'expires_at' => $expires],
            );
        });

        return response()->json(['data' => $this->draftPayload($draft)]);
    }

    public function saveDraft(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $this->ensureTraining($training, $this->lesson($request, $course, $lesson), $request->user());
        $data = $request->validate(['answers' => ['present', 'array'], 'current_index' => ['required', 'integer', 'min:0'], 'draft_id' => ['nullable', 'integer', 'min:1']]);
        $draft = DB::transaction(function () use ($training, $request, $data) {
            $training = Training::whereKey($training->id)->lockForUpdate()->firstOrFail();
            $draft = $training->drafts()->where('student_id', $request->user()->id)->when($data['draft_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->firstOrFail();
            abort_if($draft->expires_at?->lte(now()), 422, __('Assessment time has expired.'));
            $questions = $this->delivery->questions($training, $request->user()->id, $draft->question_snapshot);
            abort_if(! $training->allow_question_navigation && ($data['current_index'] < $draft->current_index || $data['current_index'] > $draft->current_index + 1), 422, 'Sequential question navigation is required.');
            $answers = $this->grading->draftAnswers($data['answers'], $questions, $draft, $training->allow_question_navigation);
            abort_if(! $training->allow_question_navigation && $training->require_all_answers && $data['current_index'] > $draft->current_index && ! array_key_exists($questions[$draft->current_index]->id, $answers), 422, 'Answer this question before continuing.');
            $draft->update(['answers' => $answers, 'current_index' => min($data['current_index'], max($questions->count() - 1, 0))]);

            return $draft;
        });

        return response()->json(['data' => $this->draftPayload($draft)]);
    }

    public function submit(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $this->ensureTraining($training, $this->lesson($request, $course, $lesson), $request->user(), $request->integer('draft_id') ?: null);
        $validated = $request->validate(['answers' => ['present', 'array'], 'draft_id' => ['nullable', 'integer', 'min:1']]);
        $attempt = $this->submissions->submit($training, $request->user()->id, $validated['answers'], $validated['draft_id'] ?? null);

        return response()->json(['data' => $this->practicePayload($training, $request->user()->id) + ['result' => $this->submissions->result($training, $attempt)]]);
    }

    private function draftPayload(TrainingDraft $draft): array
    {
        return ['id' => $draft->id, 'answers' => $draft->answers ?? [], 'currentIndex' => $draft->current_index, 'startedAt' => $draft->started_at->toISOString(), 'expiresAt' => $draft->expires_at?->toISOString()];
    }

    private function lesson(Request $request, Course $course, string $lesson): Lesson
    {
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, __('An active subscription is required.'));

        return $lessonModel;
    }

    private function ensureTraining(Training $training, Lesson $lesson, User $student, ?int $draftId = null): void
    {
        abort_unless($training->trainable_type === Lesson::class && (int) $training->trainable_id === (int) $lesson->id, 404);
        abort_unless($training->status === 'published', 404);
        abort_if($training->available_from?->isFuture(), 403, 'Practice is not available yet.');
        abort_if($training->available_until?->isPast() && ! $training->drafts()->where('student_id', $student->id)->exists() && ! ($draftId && $training->attempts()->where('student_id', $student->id)->where('draft_id', $draftId)->exists()), 410, 'Practice is no longer available.');
        abort_unless($training->prerequisites()->get()->every(fn ($rule) => $student->trainingAttempts()->where('training_id', $rule->prerequisite_training_id)->where('score', '>=', $rule->minimum_score)->whereIn('status', ['passed', 'submitted'])->exists()), 403, 'Complete the prerequisite quiz first.');
    }

    private function practicePayload(Training $training, int $userId): array
    {
        $draft = $training->drafts()->where('student_id', $userId)->first();

        return [
            'id' => $training->id,
            'type' => $training->type,
            'duration' => $training->duration_minutes,
            'allowQuestionNavigation' => $training->allow_question_navigation,
            'draft' => $draft ? $this->draftPayload($draft) : null,
            'attempts' => $training->attempts()->where('student_id', $userId)->whereNotNull('submitted_at')->latest('submitted_at')->limit(5)->get()->map(fn ($a) => ['id' => $a->id, 'score' => (float) $a->score, 'passed' => $a->status === 'passed', 'submittedAt' => $a->submitted_at->toISOString()]),
            'title' => $training->title_ar,
            'titleEn' => $training->title_en,
            'instructions' => $training->instructions_ar,
            'instructionsEn' => $training->instructions_en,
            'passingScore' => (int) ($training->passing_score ?: 70),
            'policy' => $this->delivery->state($training, $userId),
            'requireAllAnswers' => $training->require_all_answers,
            'questions' => $this->delivery->questions($training, $userId, $draft?->question_snapshot)->map(fn ($question) => [
                'id' => $question->id,
                'type' => $question->type,
                'difficulty' => $question->difficulty,
                'topic' => $question->topic,
                'topicAr' => $question->topic_ar,
                'prompt' => $question->prompt,
                'promptAr' => $question->prompt_ar,
                'options' => $question->options,
                'optionsAr' => $question->options_ar,
            ])->values(),
        ];
    }
}
