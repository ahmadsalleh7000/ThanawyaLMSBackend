<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\TrainingAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamSubmissionService
{
    public function __construct(private readonly ExamDeliveryService $delivery, private readonly ExamGradingService $grading) {}

    // Both HTTP submissions and the expiry worker take the same lock. The draft ID
    // also makes a browser submission safe to repeat after the worker has finished.
    public function submit(Assessment|Training $exam, int $userId, array $answers = [], ?int $draftId = null, bool $expiredOnly = false): AssessmentAttempt|TrainingAttempt|null
    {
        return DB::transaction(function () use ($exam, $userId, $answers, $draftId, $expiredOnly) {
            $exam = $exam->newQuery()->whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $isExam = $exam instanceof Assessment;
            $owner = $isExam ? 'user_id' : 'student_id';
            if ($draftId && $previous = $exam->attempts()->where($owner, $userId)->where('draft_id', $draftId)->first()) {
                return $expiredOnly ? null : $previous;
            }
            $draft = $exam->drafts()->where($owner, $userId)->when($draftId, fn ($q) => $q->whereKey($draftId))->first();
            $expired = $draft?->expires_at && $draft->expires_at->lte(now());
            if ($expiredOnly && ! $expired) {
                return null;
            }
            // Legacy untimed practice clients may still submit directly.
            abort_if(! $draft && ($isExam || $exam->duration_minutes !== null || $exam->allow_question_navigation === false || $draftId), 422, 'Start the assessment before submitting.');
            if (! $draft) {
                $this->delivery->ensureCanAttempt($exam, $userId);
            }
            $questions = $this->delivery->questions($exam, $userId, $draft?->question_snapshot);
            abort_if($questions->isEmpty(), 422, 'No enabled questions are available.');
            $answers = $expired ? ($draft->answers ?? []) : ($draft
                ? $this->grading->draftAnswers($answers, $questions, $draft, $exam->allow_question_navigation)
                : $this->grading->normalizeAnswers($answers, $questions));
            abort_if(! $expired && $exam->require_all_answers && count($answers) < $questions->count(), 422, __('Answer every question before submitting.'));
            $correct = $questions->filter(fn ($q) => $this->grading->answerIsCorrect($q, $answers[$q->id] ?? null))->count();
            $score = (int) round($correct / $questions->count() * 100);
            $passed = $score >= (int) ($exam->passing_score ?? 70);
            $attempt = $exam->attempts()->create([
                $owner => $userId, 'draft_id' => $draft?->id, 'question_snapshot' => $this->delivery->snapshot($questions),
                'answers' => $this->delivery->canonicalAnswers($answers, $questions), 'score' => $score,
                'submitted_at' => $expired ? $draft->expires_at : now(),
            ] + ($isExam ? ['passed' => $passed] : ['status' => $passed ? 'passed' : 'submitted', 'started_at' => $draft?->started_at ?? now()]));
            $draft?->delete();

            if ($passed) {
                $student = User::findOrFail($userId);
                if ($isExam) {
                    app(CertificateEligibilityService::class)->ensureIssued($student, $exam->course);
                } elseif ($exam->trainable_type === Lesson::class) {
                    $student->lessonWorkspaceStates()->updateOrCreate(['lesson_id' => $exam->trainable_id], ['practice_passed_at' => now()]);
                }
            }

            return $attempt;
        });
    }

    public function result(Assessment|Training $exam, AssessmentAttempt|TrainingAttempt $attempt): array
    {
        $isExam = $exam instanceof Assessment;
        $userId = $isExam ? $attempt->user_id : $attempt->student_id;
        $questions = $this->delivery->questions($exam, $userId, $attempt->question_snapshot);
        $answers = $this->delivery->displayedAnswers($attempt->answers ?? [], $questions);
        $review = $questions->map(fn ($q) => [
            'questionId' => $q->id, 'selected' => $answers[$q->id] ?? null,
            'correct' => $this->grading->answerIsCorrect($q, $answers[$q->id] ?? null),
            'correctOption' => $q->correct_option, 'correctOptions' => $q->correct_options, 'correctAnswer' => $q->correct_answer,
            'explanation' => $q->explanation, 'explanationAr' => $q->explanation_ar,
        ]);

        return [
            'attemptId' => $attempt->id, 'score' => $isExam ? $attempt->effective_score : (float) $attempt->score,
            'correct' => $review->where('correct', true)->count(), 'total' => $questions->count(),
            'passed' => $isExam ? $attempt->passed : $attempt->status === 'passed',
            'answers' => $answers,
            'certificateId' => $isExam ? Certificate::whereNull('revoked_at')->where('user_id', $userId)->where('course_id', $exam->course_id)->value('public_id') : null,
            'policy' => $this->delivery->state($exam, $userId),
            'reviewAllowed' => $exam->show_correct_answers,
            'explanationsAllowed' => $exam->show_correct_answers && $exam->show_explanations,
            'review' => $this->delivery->review($exam, $review),
        ];
    }
}
