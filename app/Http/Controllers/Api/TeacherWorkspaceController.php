<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertLessonCommentRequest;
use App\Http\Resources\LessonCommentResource;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\StudentSupportCase;
use App\Models\TeacherCourseAssignment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherWorkspaceController extends Controller
{
    public function courses(Request $request): JsonResponse
    {
        $assignments = TeacherCourseAssignment::query()
            ->active()
            ->where('teacher_id', $request->user()->id)
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $assignments->map(fn (TeacherCourseAssignment $assignment) => [
                'assignmentId' => $assignment->id,
                'courseId' => $assignment->course->id,
                'title' => $assignment->course->title,
                'titleEn' => $assignment->course->title_en,
                'isPublished' => $assignment->course->is_published,
                'lessonCount' => $assignment->course->lessons_count,
            ])->values(),
        ]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        abort_unless(
            TeacherCourseAssignment::query()
                ->active()
                ->where('teacher_id', $request->user()->id)
                ->where('course_id', $course->id)
                ->exists(),
            403,
            'This course is not assigned to the authenticated teacher.'
        );

        $course->load(['units', 'lessons.unit']);

        return response()->json([
            'data' => [
                'id' => $course->id,
                'title' => $course->title,
                'titleEn' => $course->title_en,
                'summary' => $course->summary,
                'summaryEn' => $course->summary_en,
                'isPublished' => $course->is_published,
                'units' => $course->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'title' => $unit->title_ar,
                    'titleEn' => $unit->title_en,
                    'position' => $unit->position,
                ])->values(),
                'lessons' => $course->lessons->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'slug' => $lesson->slug,
                    'title' => $lesson->title,
                    'titleEn' => $lesson->title_en,
                    'unitId' => $lesson->unit_id,
                    'position' => $lesson->position,
                    'status' => $lesson->status,
                    'commentsEnabled' => $lesson->comments_enabled,
                ])->values(),
            ],
        ]);
    }

    public function questions(Request $request): JsonResponse
    {
        $courseIds = TeacherCourseAssignment::query()
            ->active()
            ->where('teacher_id', $request->user()->id)
            ->pluck('course_id');
        $questions = LessonComment::query()
            ->whereNull('parent_id')
            ->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->with(['lesson:id,course_id,slug,title,title_en', 'lesson.course:id,title,title_en', 'user:id,name,role', 'replies.user:id,name,role'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($questions->items())->map(function (LessonComment $question) use ($request): array {
                return [
                    'course' => [
                        'id' => $question->lesson->course->id,
                        'title' => $question->lesson->course->title,
                        'titleEn' => $question->lesson->course->title_en,
                    ],
                    'lesson' => [
                        'id' => $question->lesson->id,
                        'slug' => $question->lesson->slug,
                        'title' => $question->lesson->title,
                        'titleEn' => $question->lesson->title_en,
                    ],
                    'question' => (new LessonCommentResource($question))->resolve($request),
                ];
            })->values(),
            'meta' => [
                'currentPage' => $questions->currentPage(),
                'lastPage' => $questions->lastPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    public function outcomes(Request $request, Course $course): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $assessment = $course->assessment;
        if (! $assessment) {
            return response()->json(['data' => ['summary' => ['learners' => 0, 'attempts' => 0, 'passRate' => 0, 'atRisk' => 0], 'learners' => []]]);
        }

        $learners = Enrollment::query()
            ->active()
            ->where('course_id', $course->id)
            ->with('user:id,name')
            ->get();
        $attempts = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('user_id', $learners->pluck('user_id'))
            ->latest('submitted_at')
            ->get()
            ->groupBy('user_id');
        $latest = $learners->map(fn (Enrollment $enrollment) => $attempts->get($enrollment->user_id)?->first());
        $submitted = $latest->filter();

        return response()->json(['data' => [
            'summary' => [
                'learners' => $learners->count(),
                'attempts' => $submitted->count(),
                'passRate' => $submitted->isEmpty() ? 0 : round(($submitted->where('passed', true)->count() / $submitted->count()) * 100, 1),
                'atRisk' => $latest->filter(fn (?AssessmentAttempt $attempt) => ! $attempt || ! $attempt->passed)->count(),
            ],
            'learners' => $learners->map(function (Enrollment $enrollment) use ($attempts): array {
                $attempt = $attempts->get($enrollment->user_id)?->first();

                return [
                    'id' => $enrollment->user_id,
                    'name' => $enrollment->user->name,
                    'score' => $attempt?->score,
                    'passed' => $attempt?->passed,
                    'submittedAt' => $attempt?->submitted_at?->toISOString(),
                    'status' => ! $attempt ? 'not_started' : ($attempt->passed ? 'on_track' : 'needs_attention'),
                ];
            })->values(),
        ]]);
    }

    public function analytics(Request $request, Course $course): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $assessment = $course->assessment;
        if (! $assessment) {
            return response()->json(['data' => ['questions' => []]]);
        }
        $studentIds = Enrollment::query()->active()->where('course_id', $course->id)->pluck('user_id');
        $attempts = AssessmentAttempt::query()->where('assessment_id', $assessment->id)->whereIn('user_id', $studentIds)->get();
        $questions = $assessment->questions()->get();

        return response()->json(['data' => ['questions' => $questions->map(function (AssessmentQuestion $question) use ($attempts): array {
            $responses = $attempts->map(fn (AssessmentAttempt $attempt) => $this->answerForQuestion($attempt, $question))->filter(fn ($answer) => $answer !== null);
            $incorrect = $attempts->filter(fn (AssessmentAttempt $attempt) => ! $this->answerIsCorrect($question, $this->answerForQuestion($attempt, $question)));
            $wrongCounts = [];
            if (in_array($question->type, [null, 'multiple_choice', 'single_choice'], true)) {
                foreach ($responses as $answer) {
                    if (is_numeric($answer) && (int) $answer !== (int) $question->correct_option) {
                        $option = (string) (int) $answer;
                        $wrongCounts[$option] = ($wrongCounts[$option] ?? 0) + 1;
                    }
                }
                arsort($wrongCounts);
            }
            $wrongOptions = collect(array_slice($wrongCounts, 0, 3, true))
                ->map(fn ($count, $option) => ['option' => (int) $option, 'count' => $count])
                ->values();

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'promptAr' => $question->prompt_ar,
                'attempts' => $responses->count(),
                'incorrect' => $incorrect->count(),
                'missRate' => $responses->isEmpty() ? 0 : round(($incorrect->count() / $responses->count()) * 100, 1),
                'commonWrongOptions' => $wrongOptions,
            ];
        })->sortByDesc('missRate')->values()]]);
    }

    public function gradebook(Request $request, Course $course): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $assessment = $course->assessment;
        $learners = Enrollment::query()
            ->active()
            ->where('course_id', $course->id)
            ->with('user:id,name,email')
            ->get();
        $attempts = $assessment
            ? AssessmentAttempt::query()
                ->where('assessment_id', $assessment->id)
                ->whereIn('user_id', $learners->pluck('user_id'))
                ->latest('submitted_at')
                ->get()
                ->groupBy('user_id')
            : collect();
        $assignments = Assignment::query()
            ->where('is_published', true)
            ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
            ->get(['id', 'title', 'title_en']);
        $assignmentSubmissions = AssignmentSubmission::query()
            ->whereIn('user_id', $learners->pluck('user_id'))
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->latest('submitted_at')
            ->get()
            ->groupBy(fn (AssignmentSubmission $submission) => $submission->user_id.'-'.$submission->assignment_id);

        return response()->json(['data' => [
            'assessment' => $assessment ? ['id' => $assessment->id, 'passingScore' => $assessment->passing_score] : null,
            'assignments' => $assignments->map(fn (Assignment $assignment) => ['id' => $assignment->id, 'title' => $assignment->title, 'titleEn' => $assignment->title_en])->values(),
            'rows' => $learners->map(function (Enrollment $enrollment) use ($attempts, $assignments, $assignmentSubmissions): array {
                $attempt = $attempts->get($enrollment->user_id)?->first();
                $outcomes = $assignments->map(function (Assignment $assignment) use ($enrollment, $assignmentSubmissions): array {
                    $submission = $assignmentSubmissions->get($enrollment->user_id.'-'.$assignment->id)?->first();

                    return ['assignmentId' => $assignment->id, 'title' => $assignment->title, 'titleEn' => $assignment->title_en, 'status' => $submission?->status ?? 'not_started', 'score' => $submission?->score];
                })->values();
                $gradedScores = $outcomes->whereNotNull('score')->pluck('score');

                return [
                    'student' => ['id' => $enrollment->user->id, 'name' => $enrollment->user->name, 'email' => $enrollment->user->email],
                    'attempts' => $attempts->get($enrollment->user_id)?->count() ?? 0,
                    'attemptId' => $attempt?->id,
                    'score' => $attempt?->manual_score ?? $attempt?->score,
                    'passed' => $attempt?->passed,
                    'feedback' => $attempt?->manual_feedback,
                    'manuallyReviewed' => $attempt?->manually_reviewed_at !== null,
                    'submittedAt' => $attempt?->submitted_at?->toISOString(),
                    'assignmentOutcomes' => $outcomes,
                    'assignmentsSubmitted' => $outcomes->whereIn('status', ['submitted', 'late', 'returned', 'resubmitted', 'graded'])->count(),
                    'assignmentsGraded' => $gradedScores->count(),
                    'assignmentAverage' => $gradedScores->isEmpty() ? null : round($gradedScores->avg(), 1),
                ];
            })->values(),
        ]]);
    }

    public function exportGradebook(Request $request, Course $course): StreamedResponse
    {
        $this->ensureAssigned($request, $course);
        $assessment = $course->assessment;
        $learners = Enrollment::query()
            ->active()
            ->where('course_id', $course->id)
            ->with('user:id,name,email')
            ->get();
        $attempts = $assessment
            ? AssessmentAttempt::query()
                ->where('assessment_id', $assessment->id)
                ->whereIn('user_id', $learners->pluck('user_id'))
                ->latest('submitted_at')
                ->get()
                ->groupBy('user_id')
            : collect();
        $assignments = Assignment::query()
            ->where('is_published', true)
            ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
            ->get(['id']);
        $assignmentSubmissions = AssignmentSubmission::query()
            ->whereIn('user_id', $learners->pluck('user_id'))
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->latest('submitted_at')
            ->get()
            ->groupBy(fn (AssignmentSubmission $submission) => $submission->user_id.'-'.$submission->assignment_id);

        return response()->streamDownload(function () use ($learners, $attempts, $assignments, $assignmentSubmissions): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Student ID', 'Student name', 'Email', 'Attempts', 'Latest score', 'Passed', 'Submitted at', 'Assignments submitted', 'Assignments graded', 'Assignment average']);
            foreach ($learners as $enrollment) {
                $attempt = $attempts->get($enrollment->user_id)?->first();
                $outcomes = $assignments->map(fn (Assignment $assignment) => $assignmentSubmissions->get($enrollment->user_id.'-'.$assignment->id)?->first())->filter();
                $gradedScores = $outcomes->whereNotNull('score')->pluck('score');
                fputcsv($handle, [
                    $enrollment->user->id,
                    $enrollment->user->name,
                    $enrollment->user->email,
                    $attempts->get($enrollment->user_id)?->count() ?? 0,
                    $attempt?->manual_score ?? $attempt?->score,
                    $attempt?->passed === null ? '' : ($attempt->passed ? 'Yes' : 'No'),
                    $attempt?->submitted_at?->toISOString(),
                    $outcomes->count(),
                    $gradedScores->count(),
                    $gradedScores->isEmpty() ? '' : round($gradedScores->avg(), 1),
                ]);
            }
            fclose($handle);
        }, "{$course->id}-gradebook.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reviewAttempt(Request $request, Course $course, AssessmentAttempt $attempt, AuditService $audit): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $assessment = $course->assessment;
        abort_unless($assessment && $attempt->assessment_id === $assessment->id, 404);
        abort_unless(Enrollment::query()->active()->where('course_id', $course->id)->where('user_id', $attempt->user_id)->exists(), 404);
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $attempt->attributesToArray();
        $attempt->update([
            'manual_score' => $data['score'],
            'manual_feedback' => $data['feedback'] ?? null,
            'manually_reviewed_by' => $request->user()->id,
            'manually_reviewed_at' => now(),
            'passed' => $data['score'] >= $assessment->passing_score,
        ]);
        $audit->record($request, 'teacher.assessment_attempt.reviewed', $attempt, $before);

        return response()->json(['data' => [
            'attemptId' => $attempt->id,
            'score' => $attempt->manual_score,
            'passed' => $attempt->passed,
            'feedback' => $attempt->manual_feedback,
            'reviewedAt' => $attempt->manually_reviewed_at?->toISOString(),
        ]]);
    }

    public function createAssignment(Request $request, Course $course, string $lesson, AuditService $audit): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $lessonModel = Lesson::query()->whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'title_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'], 'description_en' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'], 'is_published' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $assignment = $lessonModel->assignments()->create($data + ['position' => $data['position'] ?? (($lessonModel->assignments()->max('position') ?? 0) + 1)]);
        $audit->record($request, 'teacher.assignment.created', $assignment);

        return response()->json(['data' => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'titleEn' => $assignment->title_en,
            'description' => $assignment->description,
            'descriptionEn' => $assignment->description_en,
            'dueDate' => $assignment->due_at?->toISOString(),
            'isPublished' => $assignment->is_published,
            'position' => $assignment->position,
        ]], 201);
    }

    public function submissions(Request $request, Course $course): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $submissions = AssignmentSubmission::query()
            ->whereHas('assignment.lesson', fn ($query) => $query->where('course_id', $course->id))
            ->with(['assignment:id,lesson_id,title,title_en', 'user:id,name,email'])
            ->latest('submitted_at')->limit(100)->get();

        return response()->json(['data' => $submissions->map(fn (AssignmentSubmission $submission) => $this->submission($submission))->values()]);
    }

    public function gradeSubmission(Request $request, Course $course, AssignmentSubmission $submission, AuditService $audit): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $submission->loadMissing(['assignment.lesson', 'user:id,name,email']);
        abort_unless($submission->assignment?->lesson?->course_id === $course->id, 404);
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:100'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        $before = $submission->attributesToArray();
        $submission->update(['score' => $data['score'], 'feedback' => $data['feedback'] ?? null, 'graded_by' => $request->user()->id, 'graded_at' => now(), 'status' => 'graded']);
        $audit->record($request, 'teacher.assignment_submission.graded', $submission, $before);
        $submission->user?->userNotifications()->create(['type' => 'assignment_graded', 'title' => 'Assignment graded', 'title_ar' => 'تم تصحيح المهمة', 'message' => $submission->assignment->title, 'message_ar' => $submission->assignment->title, 'action_url' => '/course-player/'.$course->id]);

        return response()->json(['data' => $this->submission($submission->refresh()->load(['assignment:id,lesson_id,title,title_en', 'user:id,name,email']))]);
    }

    public function returnSubmission(Request $request, Course $course, AssignmentSubmission $submission, AuditService $audit): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        $submission->loadMissing(['assignment.lesson', 'user:id,name,email']);
        abort_unless($submission->assignment?->lesson?->course_id === $course->id, 404);
        $data = $request->validate(['feedback' => ['required', 'string', 'min:1', 'max:5000']]);
        $before = $submission->attributesToArray();
        $submission->update(['status' => 'returned', 'feedback' => $data['feedback'], 'score' => null, 'graded_by' => $request->user()->id, 'graded_at' => now()]);
        $audit->record($request, 'teacher.assignment_submission.returned', $submission, $before);
        $submission->user?->userNotifications()->create(['type' => 'assignment_returned', 'title' => 'Assignment returned for revision', 'title_ar' => 'تمت إعادة المهمة للمراجعة', 'message' => $submission->assignment->title, 'message_ar' => $submission->assignment->title, 'action_url' => '/course-player/'.$course->id]);

        return response()->json(['data' => $this->submission($submission->refresh()->load(['assignment:id,lesson_id,title,title_en', 'user:id,name,email']))]);
    }

    public function submissionFile(Request $request, Course $course, AssignmentSubmission $submission): BinaryFileResponse
    {
        $this->ensureAssigned($request, $course);
        $submission->loadMissing('assignment.lesson');
        abort_unless($submission->assignment?->lesson?->course_id === $course->id, 404);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return response()->download(Storage::disk('local')->path($submission->file_path), $submission->file_original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function submission(AssignmentSubmission $submission): array
    {
        return ['id' => $submission->id, 'assignment' => ['id' => $submission->assignment->id, 'title' => $submission->assignment->title, 'titleEn' => $submission->assignment->title_en], 'student' => ['id' => $submission->user->id, 'name' => $submission->user->name, 'email' => $submission->user->email], 'body' => $submission->body, 'fileName' => $submission->file_original_name, 'fileMime' => $submission->file_mime, 'fileSize' => $submission->file_size, 'status' => $submission->status, 'score' => $submission->score, 'feedback' => $submission->feedback, 'submittedAt' => $submission->submitted_at?->toISOString(), 'gradedAt' => $submission->graded_at?->toISOString()];
    }

    public function escalateToGuardians(Request $request, Course $course, User $student, AuditService $audit): JsonResponse
    {
        $this->ensureAssigned($request, $course);
        abort_unless($student->role === User::ROLE_STUDENT, 404);
        abort_unless($student->enrollments()->active()->where('course_id', $course->id)->exists(), 403, 'The student is not active in this course.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $case = StudentSupportCase::firstOrNew(['student_id' => $student->id]);
        $case->fill([
            'status' => 'open',
            'risk_level' => $case->risk_level ?: 'medium',
            'risk_score' => max((int) $case->risk_score, 50),
            'risk_signals' => array_values(array_merge($case->risk_signals ?? [], [['key' => 'teacher_escalation', 'label' => $data['reason']]])),
            'detected_at' => $case->detected_at ?: now(),
        ])->save();
        $activity = $case->activities()->create(['created_by' => $request->user()->id, 'type' => 'parent_escalation', 'channel' => 'in_app', 'body' => $data['reason']]);
        foreach ($student->linkedParents as $parent) {
            $parent->userNotifications()->create([
                'type' => 'student_support',
                'title' => 'Student follow-up requested',
                'title_ar' => 'مطلوب متابعة الطالب',
                'message' => $data['reason'],
                'message_ar' => $data['reason'],
                'action_url' => '/parent/students/'.$student->id,
            ]);
        }
        $audit->record($request, 'teacher.guardian_escalation.created', $activity);

        return response()->json(['data' => ['caseId' => $case->id, 'activityId' => $activity->id, 'notifiedGuardians' => $student->linkedParents()->count()]], 201);
    }

    public function replyToQuestion(
        UpsertLessonCommentRequest $request,
        Course $course,
        string $lesson,
        LessonComment $comment,
        AuditService $audit
    ): JsonResponse {
        $this->ensureAssigned($request, $course);
        $lessonModel = Lesson::query()->whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($comment->lesson_id === $lessonModel->id, 404);
        abort_if($comment->parent_id !== null, 422, 'Replies can only be added to a top-level comment.');
        abort_unless($lessonModel->comments_enabled, 409, 'Comments are disabled for this lesson.');
        $reply = $lessonModel->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'content' => $request->validated('content'),
        ]);
        if ($comment->user_id !== $request->user()->id) {
            $comment->loadMissing('user');
            $comment->user?->userNotifications()->create([
                'type' => 'lesson_comment',
                'title' => 'Your question received a reply',
                'title_ar' => 'تم الرد على سؤالك',
                'message' => $lessonModel->title,
                'message_ar' => $lessonModel->title,
                'action_url' => '/course-player/'.$course->id.'?lesson='.$lessonModel->slug,
                'data' => ['courseId' => $course->id, 'lessonId' => $lessonModel->id, 'commentId' => $comment->id],
            ]);
        }
        $audit->record($request, 'teacher.lesson_comment.replied', $reply);

        return response()->json(['data' => new LessonCommentResource($reply->load(['lesson:id,comments_enabled', 'user:id,name,role']))], 201);
    }

    private function ensureAssigned(Request $request, Course $course): void
    {
        abort_unless(
            TeacherCourseAssignment::query()->active()->where('teacher_id', $request->user()->id)->where('course_id', $course->id)->exists(),
            403,
            'This course is not assigned to the authenticated teacher.'
        );
    }

    private function answerIsCorrect(AssessmentQuestion $question, mixed $answer): bool
    {
        if ($question->type === 'multiple_select') {
            if (! is_array($answer)) {
                return false;
            }
            $expected = array_map('intval', $question->correct_options ?? []);
            $answer = array_map('intval', $answer);
            sort($expected);
            sort($answer);

            return $answer === $expected;
        }

        if (in_array($question->type, ['short_answer', 'fill_in_blank'], true)) {
            return is_string($answer)
                && $this->normalizeTextAnswer($answer) === $this->normalizeTextAnswer((string) $question->correct_answer);
        }

        return $answer !== null && (int) $answer === (int) $question->correct_option;
    }

    private function answerForQuestion(AssessmentAttempt $attempt, AssessmentQuestion $question): mixed
    {
        return collect($attempt->answers ?? [])->get((string) $question->getKey());
    }

    private function normalizeTextAnswer(string $answer): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($answer)));
    }
}
