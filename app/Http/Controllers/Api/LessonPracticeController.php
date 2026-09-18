<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\TrainingAttempt;
use App\Models\User;
use App\Services\StudentContentAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonPracticeController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function show(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $lessonModel = $this->lesson($request, $course, $lesson);
        $this->ensureTraining($training, $lessonModel, $request->user());
        $training->load(['questions', 'quiz.questions']);

        return response()->json(['data' => $this->practicePayload($training)]);
    }

    public function submit(Request $request, Course $course, string $lesson, Training $training): JsonResponse
    {
        $lessonModel = $this->lesson($request, $course, $lesson);
        $this->ensureTraining($training, $lessonModel, $request->user());
        $training->load(['questions', 'quiz.questions']);
        $validated = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['integer', 'min:0']]);
        $questions = $training->questionSet();
        $allowedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $answers = collect($validated['answers'])->only($allowedIds)->all();
        $correct = $questions->filter(fn ($question) => isset($answers[$question->id]) && (int) $answers[$question->id] === $question->correct_option)->count();
        $score = (int) round(($correct / max($questions->count(), 1)) * 100);
        $passed = $score >= (int) ($training->passing_score ?: 70);

        DB::transaction(function () use ($request, $lessonModel, $training, $answers, $score, $passed): void {
            TrainingAttempt::create([
                'training_id' => $training->id,
                'student_id' => $request->user()->id,
                'answers' => $answers,
                'score' => $score,
                'status' => $passed ? 'passed' : 'submitted',
                'started_at' => now(),
                'submitted_at' => now(),
            ]);

            if ($passed) {
                $request->user()->lessonWorkspaceStates()->updateOrCreate(
                    ['lesson_id' => $lessonModel->id],
                    ['practice_passed_at' => now()],
                );
            }
        });

        return response()->json(['data' => $this->practicePayload($training) + [
            'result' => [
                'score' => $score,
                'correct' => $correct,
                'total' => $questions->count(),
                'passed' => $passed,
                'review' => $questions->map(fn ($question) => [
                    'questionId' => $question->id,
                    'selected' => $answers[$question->id] ?? null,
                    'correctOption' => $question->correct_option,
                    'explanation' => $question->explanation,
                    'explanationAr' => $question->explanation_ar,
                ])->values(),
            ],
        ]]);
    }

    private function lesson(Request $request, Course $course, string $lesson): Lesson
    {
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, 'An active subscription is required.');

        return $lessonModel;
    }

    private function ensureTraining(Training $training, Lesson $lesson, User $student): void
    {
        abort_unless($training->trainable_type === Lesson::class && (int) $training->trainable_id === (int) $lesson->id, 404);
        abort_unless($training->status === 'published', 404);
        abort_if($training->available_from?->isFuture(), 403, 'Practice is not available yet.');
        abort_if($training->available_until?->isPast(), 410, 'Practice is no longer available.');
        abort_unless($training->prerequisites()->get()->every(fn ($rule) => $student->trainingAttempts()->where('training_id', $rule->prerequisite_training_id)->where('score', '>=', $rule->minimum_score)->whereIn('status', ['passed', 'submitted'])->exists()), 403, 'Complete the prerequisite quiz first.');
    }

    private function practicePayload(Training $training): array
    {
        return [
            'id' => $training->id,
            'type' => $training->type,
            'title' => $training->title_ar,
            'titleEn' => $training->title_en,
            'instructions' => $training->instructions_ar,
            'instructionsEn' => $training->instructions_en,
            'passingScore' => (int) ($training->passing_score ?: 70),
            'questions' => $training->questionSet()->map(fn ($question) => [
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
