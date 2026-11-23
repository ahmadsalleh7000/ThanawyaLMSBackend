<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentAssessmentHubService
{
    public function __construct(private readonly StudentContentAccessService $access, private readonly ExamDeliveryService $delivery) {}

    public function for(User $student): Collection
    {
        $courseIds = $this->access->courseIdsFor($student);
        $own = fn ($q) => $q->where('user_id', $student->id);
        $ownTraining = fn ($q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at');
        $assessments = Assessment::where(fn ($q) => $q->where(fn ($q) => $q->where('is_published', true)->whereIn('course_id', $courseIds))->orWhereHas('attempts', $own))
            ->with(['course', 'drafts' => $own, 'attempts' => fn ($q) => $own($q)->select('id', 'assessment_id', 'user_id', 'score', 'manual_score', 'passed', 'submitted_at')])
            ->withCount(['questions' => fn ($q) => $q->where('is_active', true)])->get();
        $trainings = Training::where('trainable_type', Lesson::class)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'published')->whereHasMorph('trainable', [Lesson::class], fn ($l) => $l->where('status', 'published')->whereIn('course_id', $courseIds)))->orWhereHas('attempts', $ownTraining))
            ->with([
                'trainable.course', 'trainable.unit', 'prerequisites',
                'drafts' => fn ($q) => $q->where('student_id', $student->id),
                'questions' => fn ($q) => $q->select('question_bank_questions.id', 'is_active'),
                'quiz.questions' => fn ($q) => $q->select('question_bank_questions.id', 'is_active'),
                'attempts' => fn ($q) => $ownTraining($q)->select('id', 'training_id', 'student_id', 'score', 'status', 'submitted_at'),
            ])->get();
        $prerequisiteScores = $student->trainingAttempts()->whereNotNull('submitted_at')->whereIn('status', ['passed', 'submitted'])
            ->selectRaw('training_id, MAX(score) as best_score')->groupBy('training_id')->pluck('best_score', 'training_id');
        $courseAccess = [];
        $lessonAccess = [];

        return $assessments->concat($trainings)->map(function ($exam) use ($student, $prerequisiteScores, &$courseAccess, &$lessonAccess) {
            $isExam = $exam instanceof Assessment;
            $lesson = $isExam ? null : $exam->trainable;
            $course = $isExam ? $exam->course : $lesson?->course;
            if (! $course) {
                return null;
            }
            $courseAccess[$course->id] ??= $this->access->canAccessCourse($student, $course);
            $attempts = $exam->attempts->sortByDesc('submitted_at')->values();
            $policy = $this->delivery->state($exam, $student->id, $attempts);
            $draft = $exam->drafts->first();
            $questions = $isExam ? $exam->questions_count : $exam->questionSet()->count();
            $questions = $draft?->question_snapshot !== null ? count($draft->question_snapshot) : ($exam->question_limit ? min($exam->question_limit, $questions) : $questions);
            $deadline = $isExam ? $exam->due_at : $exam->available_until;
            $published = $isExam ? $exam->is_published : $exam->status === 'published';
            $reason = null;
            if (! $courseAccess[$course->id]) {
                $reason = 'access_required';
            } elseif (! $published) {
                $reason = 'unpublished';
            } elseif (! $isExam) {
                $lessonAccess[$lesson->id] ??= $this->access->canAccessLesson($student, $lesson);
                if (! $lessonAccess[$lesson->id]) {
                    $reason = 'lesson_locked';
                } elseif (! $exam->prerequisites->every(fn ($rule) => isset($prerequisiteScores[$rule->prerequisite_training_id]) && $prerequisiteScores[$rule->prerequisite_training_id] >= $rule->minimum_score)) {
                    $reason = 'prerequisites';
                }
            }
            if (! $reason && $exam->available_from?->isFuture()) {
                $reason = 'upcoming';
            }
            if (! $reason && $deadline?->isPast() && ! $draft) {
                $reason = 'closed';
            }
            if (! $reason && ! $draft && ! $policy['canAttempt']) {
                $reason = $policy['blockedReason'];
            }
            if (! $reason && ! $draft && ! $questions) {
                $reason = 'not_ready';
            }
            $passed = $attempts->contains(fn ($attempt) => $isExam ? $attempt->passed : $attempt->status === 'passed');
            $result = $passed ? 'passed' : ($draft ? 'in_progress' : ($attempts->isEmpty() ? 'not_started' : 'needs_review'));

            return [
                'id' => $exam->id, 'key' => ($isExam ? 'exam-' : 'quiz-').$exam->id,
                'type' => $isExam ? 'exam' : 'quiz',
                'title' => $isExam ? $course->title : $exam->title_ar,
                'titleEn' => $isExam ? $course->title_en : $exam->title_en,
                'course' => ['id' => $course->id, 'title' => $course->title, 'titleEn' => $course->title_en],
                'lesson' => $lesson ? ['id' => $lesson->slug, 'title' => $lesson->title, 'titleEn' => $lesson->title_en] : null,
                'questionCount' => $questions, 'durationMinutes' => $exam->duration_minutes,
                'passingScore' => (int) ($exam->passing_score ?: 70),
                'bestScore' => $attempts->isEmpty() ? null : (float) $attempts->max(fn ($attempt) => $isExam ? $attempt->effective_score : $attempt->score),
                'latestScore' => $attempts->isEmpty() ? null : (float) ($isExam ? $attempts->first()->effective_score : $attempts->first()->score),
                'lastSubmittedAt' => $attempts->first()?->submitted_at?->toISOString(),
                'attemptsCount' => $attempts->count(), 'result' => $result,
                'availableFrom' => $exam->available_from?->toISOString(), 'dueAt' => $deadline?->toISOString(),
                'canStart' => $reason === null, 'blockedReason' => $reason, 'hasDraft' => (bool) $draft,
                'policy' => $policy,
                'url' => ($isExam ? '/course-assessment/'.rawurlencode($course->id) : '/course-player/'.rawurlencode($course->id).'?'.http_build_query(['lesson' => $lesson->slug, 'tab' => 'practice', 'solvePractice' => $exam->id])),
            ];
        })->filter()->sortBy(fn ($item) => [! $item['canStart'], $item['result'] === 'passed', $item['dueAt'] ?? '9999', $item['course']['id'], $item['type'], $item['id']])->values();
    }
}
