<?php

namespace App\Services;

use App\Models\Course;
use App\Models\StudentOnboardingProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StudentPlanningService
{
    public function for(User $student, bool $includeLeaderboard = true): array
    {
        $courses = Course::available()->whereIn('id', app(StudentContentAccessService::class)->courseIdsFor($student))->with(['lessons', 'assessment'])->get();
        $completions = $student->lessonCompletions()->with('lesson:id,course_id,duration_minutes')->get();
        $attemptedAssessmentIds = $student->assessmentAttempts()->pluck('assessment_id');
        $completedLessonIds = $completions->pluck('lesson_id');
        $todayTasks = $courses->map(function ($course) use ($completedLessonIds, $student) {
            $lesson = $course->lessons->first(fn ($item) => app(StudentContentAccessService::class)->canAccessLesson($student, $item) && ! $completedLessonIds->contains($item->id));

            return $lesson ? ['type' => 'lesson', 'id' => $lesson->slug, 'courseId' => $course->id, 'title' => $lesson->title, 'titleEn' => $lesson->title_en, 'course' => $course->title, 'courseEn' => $course->title_en, 'durationMinutes' => $lesson->duration_minutes, 'url' => '/course-player/'.$course->id.'?lesson='.$lesson->slug] : null;
        })->filter()->take(3)->values();
        $deadlines = $courses->map(fn ($course) => $course->assessment)->filter(fn ($assessment) => $assessment?->is_published && $assessment->due_at && ! $attemptedAssessmentIds->contains($assessment->id) && $assessment->due_at->isFuture())
            ->sortBy('due_at')->take(8)->values()->map(fn ($assessment) => ['id' => $assessment->id, 'courseId' => $assessment->course_id, 'course' => $assessment->course->title, 'courseEn' => $assessment->course->title_en, 'dueAt' => $assessment->due_at->toISOString(), 'availableFrom' => $assessment->available_from?->toISOString(), 'url' => '/course-assessment/'.$assessment->course_id]);
        $weekStart = now()->startOfWeek();
        $weeklyMinutes = (int) $completions->where('completed_at', '>=', $weekStart)->sum(fn ($completion) => $completion->lesson?->duration_minutes ?? 0);
        $goal = $student->onboardingProfile?->weekly_goal_minutes ?? 120;
        $attemptDates = $student->assessmentAttempts()->get(['submitted_at'])->pluck('submitted_at');
        $activity = $completions->pluck('completed_at')->merge($attemptDates)->filter();
        $history = collect(range(27, 0))->map(function ($daysAgo) use ($activity) {
            $date = Carbon::today()->subDays($daysAgo);

            return ['date' => $date->toDateString(), 'active' => $activity->contains(fn ($item) => $item->isSameDay($date)), 'count' => $activity->filter(fn ($item) => $item->isSameDay($date))->count()];
        });
        $summary = app(LearningAchievementService::class)->summary($student, $completions);

        return ['todayTasks' => $todayTasks, 'deadlines' => $deadlines, 'weeklyGoal' => ['targetMinutes' => $goal, 'completedMinutes' => $weeklyMinutes, 'percent' => min(100, (int) round($weeklyMinutes / max($goal, 1) * 100))], 'streak' => ['current' => $this->streak($history), 'history' => $history], 'achievements' => $summary['achievements'], 'leaderboard' => $includeLeaderboard ? $this->leaderboard($student) : null];
    }

    private function leaderboard(User $viewer): array
    {
        $enabled = (bool) $viewer->onboardingProfile?->leaderboard_opt_in;
        if (! $enabled) {
            return ['enabled' => false, 'entries' => []];
        }
        $entries = StudentOnboardingProfile::where('leaderboard_opt_in', true)->with('student:id,name')->get()->map(function ($profile) {
            $minutes = $profile->student->lessonCompletions()->where('completed_at', '>=', now()->startOfWeek())->with('lesson:id,duration_minutes')->get()->sum(fn ($item) => $item->lesson?->duration_minutes ?? 0);
            $parts = preg_split('/\s+/', trim($profile->student->name));

            return ['studentId' => $profile->student_id, 'name' => $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : ''), 'minutes' => (int) $minutes];
        })->sortByDesc('minutes')->values()->take(20)->map(fn ($entry, $index) => $entry + ['rank' => $index + 1, 'isCurrentUser' => $entry['studentId'] === $viewer->id])->values();

        return ['enabled' => true, 'entries' => $entries];
    }

    private function streak(Collection $history): int
    {
        $lookup = $history->where('active', true)->pluck('date')->flip();
        $cursor = Carbon::today();
        if (! $lookup->has($cursor->toDateString())) {
            $cursor->subDay();
        }
        $count = 0;
        while ($lookup->has($cursor->toDateString())) {
            $count++;
            $cursor->subDay();
        }

        return $count;
    }
}
