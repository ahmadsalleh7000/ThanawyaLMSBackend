<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\StudentContentAccessService;
use App\Services\StudentPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ParentStudentController extends Controller
{
    public function __construct(
        private readonly StudentPlanningService $planning,
        private readonly StudentContentAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $students = $request->user()->linkedStudents()->get()->map(fn (User $student) => $this->summary($student, $student->pivot->grade));

        return response()->json(['data' => $students]);
    }

    public function show(Request $request, User $student): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewLearningReport', $student);
        $link = $request->user()->linkedStudents()->whereKey($student->id)->firstOrFail();

        return response()->json(['data' => $this->summary($student, $link->pivot->grade, true)]);
    }

    private function summary(User $student, ?string $grade, bool $detailed = false): array
    {
        $courses = Course::available()
            ->whereIn('id', $this->access->courseIdsFor($student))
            ->with('lessons')
            ->get();
        $courseIds = $courses->pluck('id');
        $completions = $student->lessonCompletions()->with('lesson.course:id,title,title_en')->latest('completed_at')->get();
        $completions = $completions->filter(fn ($completion) => $completion->lesson && $courseIds->contains($completion->lesson->course_id))->values();
        $completedLessonIds = $completions->pluck('lesson_id');
        $attempts = AssessmentAttempt::where('user_id', $student->id)->with('assessment.course:id,title,title_en')->latest('submitted_at')->get();
        $attempts = $attempts->filter(fn (AssessmentAttempt $attempt) => $attempt->assessment && $courseIds->contains($attempt->assessment->course_id))->values();
        $certifiedCourseIds = Certificate::where('user_id', $student->id)->whereNull('revoked_at')->pluck('course_id');
        $courseProgress = $courses->map(function (Course $course) use ($completedLessonIds, $attempts, $certifiedCourseIds, $detailed, $completions) {
            $lessonIds = $course->lessons->pluck('id');
            $completed = $lessonIds->intersect($completedLessonIds)->count();
            $progress = $lessonIds->isEmpty() ? 0 : (int) round(($completed / $lessonIds->count()) * 100);
            $attempt = $attempts->first(fn (AssessmentAttempt $item) => $item->assessment?->course_id === $course->id);
            $data = ['courseId' => $course->id, 'progress' => $progress, 'completedLessons' => $completed, 'totalLessons' => $lessonIds->count(), 'assessmentScore' => $attempt?->score, 'assessmentPassed' => $attempt?->passed ?? false, 'certificateIssued' => $certifiedCourseIds->contains($course->id)];
            if ($detailed) {
                $data['lastActivityAt'] = $completions->first(fn ($completion) => $lessonIds->contains($completion->lesson_id))?->completed_at?->toISOString();
            }

            return $data;
        });
        $average = $courseProgress->isEmpty() ? 0 : (int) round($courseProgress->avg('progress'));
        $lastActivityAt = $completions->first()?->completed_at;
        $weeklyCompleted = $completions->where('completed_at', '>=', now()->subDays(6)->startOfDay())->count();
        $previousWeeklyCompleted = $completions->whereBetween('completed_at', [now()->subDays(13)->startOfDay(), now()->subDays(7)->endOfDay()])->count();
        $streak = $this->currentStreak($completions->pluck('completed_at')->filter()->map(fn ($date) => $date->toDateString())->unique()->all());
        $latestAttempt = $attempts->first();
        $attentionStatus = ! $lastActivityAt ? 'not_started' : ($lastActivityAt->lt(now()->subDays(7)) || ($latestAttempt && ! $latestAttempt->passed) ? 'needs_attention' : 'on_track');

        $planning = $this->planning->for($student, false);
        $report = ['id' => (string) $student->id, 'name' => $student->name, 'grade' => $grade, 'progress' => $average, 'courses' => $courseProgress->pluck('courseId')->values(), 'courseProgress' => $courseProgress->values(), 'lastActivityAt' => $lastActivityAt?->toISOString(), 'weeklyCompletedLessons' => $weeklyCompleted, 'weeklyTrend' => $weeklyCompleted - $previousWeeklyCompleted, 'currentStreak' => $streak, 'certificateCount' => $certifiedCourseIds->count(), 'attentionStatus' => $attentionStatus, 'schedule' => ['todayTasks' => $planning['todayTasks']->take(2)->values(), 'deadlines' => $planning['deadlines']->take(3)->values(), 'weeklyGoal' => $planning['weeklyGoal']]];
        if ($detailed) {
            $report += [
                'learningMinutes' => $completions->sum(fn ($completion) => $completion->lesson?->duration_minutes ?? 0),
                'completedLessons' => $completions->count(),
                'weeklyActivity' => collect(range(6, 0))->map(function ($daysAgo) use ($completions) {
                    $date = Carbon::today()->subDays($daysAgo);

                    return ['date' => $date->toDateString(), 'completedLessons' => $completions->filter(fn ($completion) => $completion->completed_at?->isSameDay($date))->count()];
                }),
                'recentActivity' => $completions->take(5)->map(fn ($completion) => [
                    'lessonId' => $completion->lesson?->slug,
                    'lesson' => $completion->lesson?->title,
                    'lessonEn' => $completion->lesson?->title_en,
                    'courseId' => $completion->lesson?->course_id,
                    'course' => $completion->lesson?->course?->title,
                    'courseEn' => $completion->lesson?->course?->title_en,
                    'completedAt' => $completion->completed_at->toISOString(),
                ])->values(),
                'assessmentHistory' => $attempts->take(5)->map(fn (AssessmentAttempt $attempt) => [
                    'id' => $attempt->id,
                    'courseId' => $attempt->assessment?->course_id,
                    'course' => $attempt->assessment?->course?->title,
                    'courseEn' => $attempt->assessment?->course?->title_en,
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'submittedAt' => $attempt->submitted_at?->toISOString(),
                ])->values(),
            ];
        }

        return $report;
    }

    private function currentStreak(array $dates): int
    {
        $lookup = array_fill_keys($dates, true);
        $cursor = Carbon::today();
        if (! isset($lookup[$cursor->toDateString()])) {
            $cursor->subDay();
        }
        $streak = 0;
        while (isset($lookup[$cursor->toDateString()])) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
