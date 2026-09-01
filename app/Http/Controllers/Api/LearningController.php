<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\StudentContentAccessService;
use App\Services\StudentPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LearningController extends Controller
{
    public function __construct(private readonly StudentContentAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $enrolledIds = $this->access->courseIdsFor($request->user());
        $completed = $request->user()->lessonCompletions()->with('lesson:id,course_id,slug')->get()->groupBy('lesson.course_id')->map(fn ($items) => $items->pluck('lesson.slug')->values());

        $workspace = $request->user()->lessonWorkspaceStates()->with('lesson:id,course_id,slug')->get()
            ->filter(fn ($state) => $state->lesson)
            ->mapWithKeys(fn ($state) => [$state->lesson->course_id.'.'.$state->lesson->slug => $this->serializeWorkspace($state)]);

        $subscriptions = $request->user()->studentSubscriptions()->active()->with([
            'term.academicYear', 'term.grade',
            'term.academicSubjects' => fn ($query) => $query->where('is_active', true)->orderBy('display_order'),
            'term.academicSubjects.subject',
            'term.academicSubjects.courses' => fn ($query) => $query->available()->with('lessons'),
        ])->get();
        $academicScopes = $subscriptions->map(fn ($subscription) => [
            'subscriptionId' => $subscription->id,
            'status' => $subscription->status,
            'startsAt' => $subscription->starts_at?->toISOString(),
            'endsAt' => $subscription->ends_at?->toISOString(),
            'academicYear' => ['code' => $subscription->term->academicYear->code, 'name' => $subscription->term->academicYear->name_ar, 'nameEn' => $subscription->term->academicYear->name_en],
            'grade' => ['code' => $subscription->term->grade->code, 'name' => $subscription->term->grade->name_ar, 'nameEn' => $subscription->term->grade->name_en],
            'semester' => ['code' => $subscription->term->code, 'name' => $subscription->term->name_ar, 'nameEn' => $subscription->term->name_en],
            'subjects' => $subscription->term->academicSubjects->map(fn ($offering) => [
                'id' => $offering->id,
                'code' => $offering->subject->code,
                'name' => $offering->subject->name_ar,
                'nameEn' => $offering->subject->name_en,
                'courses' => $offering->courses->map(fn ($course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'titleEn' => $course->title_en,
                    'lessonsTotal' => $course->lessons->where('status', 'published')->count(),
                    'lessonsCompleted' => $completed->get($course->id, collect())->count(),
                ])->values(),
            ])->values(),
        ])->values();

        return response()->json(['enrolledIds' => $enrolledIds, 'completedLessons' => $completed, 'lessonWorkspace' => $workspace, 'academicScopes' => $academicScopes]);
    }

    public function complete(Request $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, 'An active subscription is required.');
        $workspace = $request->user()->lessonWorkspaceStates()->where('lesson_id', $lessonModel->id)->first();
        $rules = $lessonModel->completion_rules ?: [];
        abort_if(($rules['require_content_opened'] ?? false) && ! $workspace?->content_opened_at, 422, 'Open the lesson content before completing this lesson.');
        abort_if(($rules['require_practice_passed'] ?? false) && ! $workspace?->practice_passed_at, 422, 'Pass the lesson practice before completing this lesson.');
        abort_if((int) ($rules['minimum_video_seconds'] ?? 0) > (int) ($workspace?->playback_seconds ?? 0), 422, 'Spend more time with the lesson video before completing this lesson.');
        $completion = $request->user()->lessonCompletions()->firstOrCreate(['lesson_id' => $lessonModel->id], ['completed_at' => now()]);

        $count = $request->user()->lessonCompletions()->count();
        $celebration = $completion->wasRecentlyCreated && in_array($count, [1, 5, 10, 25], true) ? 'lessons_'.$count : null;

        return response()->json(['courseId' => $course->id, 'lessonId' => $lessonModel->slug, 'completedAt' => $completion->completed_at, 'celebration' => $celebration]);
    }

    public function updateWorkspace(Request $request, Course $course, string $lesson): JsonResponse
    {
        $lessonModel = Lesson::whereBelongsTo($course)->where('slug', $lesson)->firstOrFail();
        abort_unless($this->access->canAccessLesson($request->user(), $lessonModel), 403, 'An active subscription is required.');
        $validated = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'is_bookmarked' => ['sometimes', 'boolean'],
            'playback_seconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
            'playback_rate' => ['sometimes', 'numeric', 'in:0.75,1,1.25,1.5,1.75,2'],
            'content_opened' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('content_opened', $validated)) {
            $contentOpened = (bool) $validated['content_opened'];
            unset($validated['content_opened']);
            if ($contentOpened) {
                $validated['content_opened_at'] = now();
            }
        }
        $state = $request->user()->lessonWorkspaceStates()->updateOrCreate(
            ['lesson_id' => $lessonModel->id],
            $validated,
        );

        return response()->json(['data' => $this->serializeWorkspace($state)]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $completions = $user->lessonCompletions()->with('lesson:id,course_id,duration_minutes')->get();
        $attempts = $user->assessmentAttempts()->latest('submitted_at')->get();
        $certificateCount = $user->certificates()->whereNull('revoked_at')->count();
        $activityDates = $completions->pluck('completed_at')->merge($attempts->pluck('submitted_at'))
            ->filter()->map(fn ($date) => $date->toDateString())->unique()->values();
        $streak = $this->currentStreak($activityDates->all());
        $completedCount = $completions->count();
        $passedCount = $attempts->where('passed', true)->count();

        return response()->json(['data' => [
            'studyMinutes' => $completions->sum(fn ($completion) => $completion->lesson?->duration_minutes ?? 0),
            'completedLessons' => $completedCount,
            'passedAssessments' => $passedCount,
            'certificateCount' => $certificateCount,
            'currentStreak' => $streak,
            'bestAssessmentScore' => (int) ($attempts->max('score') ?? 0),
            'achievements' => collect([
                ['key' => 'first_lesson', 'progress' => min($completedCount, 1), 'target' => 1, 'earned' => $completedCount >= 1],
                ['key' => 'five_lessons', 'progress' => min($completedCount, 5), 'target' => 5, 'earned' => $completedCount >= 5],
                ['key' => 'first_pass', 'progress' => min($passedCount, 1), 'target' => 1, 'earned' => $passedCount >= 1],
                ['key' => 'three_day_streak', 'progress' => min($streak, 3), 'target' => 3, 'earned' => $streak >= 3],
                ['key' => 'first_certificate', 'progress' => min($certificateCount, 1), 'target' => 1, 'earned' => $certificateCount >= 1],
            ]),
        ]]);
    }

    public function planner(Request $request, StudentPlanningService $planning): JsonResponse
    {
        return response()->json(['data' => $planning->for($request->user())]);
    }

    public function leaderboardPreference(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $profile = $request->user()->onboardingProfile()->firstOrCreate();
        $profile->update(['leaderboard_opt_in' => $data['enabled']]);

        return response()->json(['data' => ['enabled' => (bool) $profile->leaderboard_opt_in]]);
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

    private function serializeWorkspace($state): array
    {
        return [
            'note' => $state->note ?? '',
            'isBookmarked' => (bool) $state->is_bookmarked,
            'playbackSeconds' => (int) $state->playback_seconds,
            'playbackRate' => (float) $state->playback_rate,
            'contentOpened' => (bool) $state->content_opened_at,
            'practicePassed' => (bool) $state->practice_passed_at,
            'updatedAt' => $state->updated_at?->toISOString(),
        ];
    }
}
