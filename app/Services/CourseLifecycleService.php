<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Validation\ValidationException;

class CourseLifecycleService
{
    public function __construct(private readonly CourseReadinessService $readiness) {}

    public function transition(Course $course, string $action): void
    {
        match ($action) {
            'publish' => $this->publish($course),
            'unpublish' => $course->update(['is_published' => false]),
            'archive' => $course->update(['is_published' => false, 'archived_at' => now()]),
            'restore' => $course->update(['archived_at' => null]),
            default => abort(404),
        };
    }

    private function publish(Course $course): void
    {
        $report = $this->readiness->inspect($course);
        if (! $report['canPublish']) {
            $messages = collect($report['items'])->where('status', 'blocking')->pluck('guidance')->values()->all();
            throw ValidationException::withMessages(['course' => $messages]);
        }

        $course->update(['is_published' => true]);
    }
}
