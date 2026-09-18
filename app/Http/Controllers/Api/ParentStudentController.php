<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LiveSession;
use App\Models\LiveSessionAttendance;
use App\Models\Order;
use App\Models\StudentSubscription;
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

    public function assignments(Request $request, User $student): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewLearningReport', $student);
        $request->user()->linkedStudents()->whereKey($student->id)->firstOrFail();
        $courseIds = $this->access->courseIdsFor($student);
        $assignments = Assignment::query()->where('is_published', true)->whereHas('lesson', fn ($query) => $query->whereIn('course_id', $courseIds))->with(['lesson.course:id,title,title_en', 'submissions' => fn ($query) => $query->where('user_id', $student->id)])->get();

        return response()->json(['data' => $assignments->map(fn (Assignment $assignment) => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'titleEn' => $assignment->title_en,
            'courseId' => $assignment->lesson->course_id,
            'course' => $assignment->lesson->course->title,
            'courseEn' => $assignment->lesson->course->title_en,
            'status' => $assignment->submissions->first()?->status ?? 'ready',
            'score' => $assignment->submissions->first()?->score,
            'feedback' => $assignment->submissions->first()?->feedback,
            'submittedAt' => $assignment->submissions->first()?->submitted_at?->toISOString(),
        ])->values()]);
    }

    public function attendance(Request $request, User $student): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewLearningReport', $student);
        $request->user()->linkedStudents()->whereKey($student->id)->firstOrFail();
        $sessions = LiveSession::query()->whereIn('course_id', $this->access->courseIdsFor($student))->where('status', 'published')->with('course:id,title,title_en')->latest('starts_at')->limit(50)->get();
        $statuses = LiveSessionAttendance::query()->where('student_id', $student->id)->whereIn('live_session_id', $sessions->pluck('id'))->get()->keyBy('live_session_id');

        return response()->json(['data' => $sessions->map(fn (LiveSession $session) => ['id' => $session->id, 'title' => $session->title, 'titleEn' => $session->title_en, 'course' => $session->course->title, 'courseEn' => $session->course->title_en, 'startsAt' => $session->starts_at?->toISOString(), 'status' => $statuses->get($session->id)?->status ?? 'unmarked'])->values()]);
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
        $report = ['id' => (string) $student->id, 'name' => $student->name, 'grade' => $grade, 'progress' => $average, 'courses' => $courseProgress->pluck('courseId')->values(), 'courseProgress' => $courseProgress->values(), 'lastActivityAt' => $lastActivityAt?->toISOString(), 'weeklyCompletedLessons' => $weeklyCompleted, 'weeklyTrend' => $weeklyCompleted - $previousWeeklyCompleted, 'currentStreak' => $streak, 'certificateCount' => $certifiedCourseIds->count(), 'attentionStatus' => $attentionStatus, 'subscription' => $this->subscriptionSummary($student), 'schedule' => ['todayTasks' => $planning['todayTasks']->take(2)->values(), 'deadlines' => $planning['deadlines']->take(3)->values(), 'weeklyGoal' => $planning['weeklyGoal']]];
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

    private function subscriptionSummary(User $student): array
    {
        $subscriptions = $student->studentSubscriptions()
            ->with(['plan:id,code,name_ar,name_en,billing_period,currency', 'term:id,code,name_ar,name_en'])
            ->latest('starts_at')->get();
        $active = $subscriptions->filter(fn (StudentSubscription $subscription) => $subscription->status === 'active'
            && (! $subscription->ends_at || $subscription->ends_at->isFuture()));
        $orders = $student->orders()->where('product_type', 'subscription')
            ->with('subscriptionPlan:id,code,name_ar,name_en,billing_period,currency')
            ->latest()->limit(10)->get();
        $actionable = $orders->first(fn (Order $order) => in_array($order->status, ['pending', 'submitted', 'needs_information'], true));

        return [
            'state' => match (true) {
                $active->isNotEmpty() => 'active',
                $actionable?->status === 'submitted' => 'pending_review',
                $actionable?->status === 'needs_information' => 'rejected',
                $actionable?->status === 'pending' => 'pending_payment',
                $subscriptions->isNotEmpty() => 'expired',
                default => 'none',
            },
            'current' => $active->isEmpty() ? null : [
                'plan' => $active->first()->plan?->only(['code', 'name_ar', 'name_en', 'billing_period', 'currency']),
                'startsAt' => $active->min('starts_at')?->toISOString(),
                'endsAt' => $active->filter(fn ($subscription) => $subscription->ends_at)->max('ends_at')?->toISOString(),
            ],
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->public_id,
                'status' => $order->status,
                'amount' => (float) $order->amount,
                'currency' => $order->currency,
                'plan' => $order->subscriptionPlan?->only(['code', 'name_ar', 'name_en', 'billing_period', 'currency']),
                'createdAt' => $order->created_at?->toISOString(),
            ])->values(),
        ];
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
