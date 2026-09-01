<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AnalyticsReportService
{
    public function generate(int $days): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();
        $enrollments = Enrollment::whereBetween('enrolled_at', [$start, $end])->get(['course_id', 'user_id', 'enrolled_at']);
        $payments = Order::where('status', 'completed')->whereBetween('paid_at', [$start, $end])->get(['amount', 'paid_at']);
        $completions = LessonCompletion::whereBetween('completed_at', [$start, $end])->get(['user_id', 'completed_at']);
        $attempts = AssessmentAttempt::whereBetween('submitted_at', [$start, $end])->get(['user_id', 'score', 'passed', 'submitted_at']);
        $certificates = Certificate::whereBetween('issued_at', [$start, $end])->get(['issued_at']);
        $students = User::students()->whereBetween('created_at', [$start, $end])->get(['created_at']);
        $activeLearners = $completions->pluck('user_id')->merge($attempts->pluck('user_id'))->unique()->count();
        $passed = $attempts->where('passed', true)->count();

        return [
            'period' => ['days' => $days, 'start' => $start, 'end' => $end],
            'metrics' => [
                'newStudents' => $students->count(),
                'enrollments' => $enrollments->count(),
                'revenue' => (float) $payments->sum('amount'),
                'activeLearners' => $activeLearners,
                'lessonCompletions' => $completions->count(),
                'assessmentAttempts' => $attempts->count(),
                'averageScore' => $attempts->isEmpty() ? 0 : round((float) $attempts->avg('score'), 1),
                'passRate' => $attempts->isEmpty() ? 0 : round(($passed / $attempts->count()) * 100, 1),
                'certificates' => $certificates->count(),
                'certificateRate' => $enrollments->isEmpty() ? 0 : min(100, round(($certificates->count() / $enrollments->count()) * 100, 1)),
            ],
            'trend' => $this->trend($start, $end, $enrollments, $payments, $completions),
            'topCourses' => Course::query()->withCount(['enrollments' => fn ($query) => $query->whereBetween('enrolled_at', [$start, $end])])->orderByDesc('enrollments_count')->limit(6)->get(['id', 'title', 'title_en']),
            'lowEngagementLessons' => Lesson::query()->with('course:id,title')->withCount(['completions' => fn ($query) => $query->whereBetween('completed_at', [$start, $end])])->whereHas('course', fn ($query) => $query->notArchived())->orderBy('completions_count')->orderBy('position')->limit(6)->get(),
        ];
    }

    private function trend(CarbonImmutable $start, CarbonImmutable $end, Collection $enrollments, Collection $payments, Collection $completions): Collection
    {
        $enrollmentsByDay = $enrollments->countBy(fn ($item) => $item->enrolled_at->toDateString());
        $paymentsByDay = $payments->groupBy(fn ($item) => $item->paid_at->toDateString())
            ->map(fn (Collection $orders): float => (float) $orders->sum('amount'));
        $completionsByDay = $completions->countBy(fn ($item) => $item->completed_at->toDateString());
        $maximum = 0;
        $points = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $point = [
                'date' => $date,
                'label' => $date->format('d/m'),
                'enrollments' => (int) $enrollmentsByDay->get($key, 0),
                'revenue' => (float) $paymentsByDay->get($key, 0),
                'completions' => (int) $completionsByDay->get($key, 0),
            ];
            $maximum = max($maximum, $point['enrollments'], $point['completions']);
            $points->push($point);
        }

        return $points->map(fn (array $point) => $point + [
            'enrollmentHeight' => $maximum ? max(4, (int) round(($point['enrollments'] / $maximum) * 100)) : 4,
            'completionHeight' => $maximum ? max(4, (int) round(($point['completions'] / $maximum) * 100)) : 4,
        ]);
    }
}
