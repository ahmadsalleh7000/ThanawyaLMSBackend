<?php

namespace App\Console\Commands;

use App\Models\ScheduledCourseAction;
use App\Services\CourseLifecycleService;
use Illuminate\Console\Command;
use Throwable;

class RunScheduledCourseActions extends Command
{
    protected $signature = 'courses:run-scheduled-actions';

    protected $description = 'Execute due course publish and unpublish actions';

    public function handle(CourseLifecycleService $lifecycle): int
    {
        ScheduledCourseAction::due()->with('course')->eachById(function (ScheduledCourseAction $scheduled) use ($lifecycle): void {
            try {
                $lifecycle->transition($scheduled->course, $scheduled->action);
                $scheduled->update(['status' => 'completed', 'completed_at' => now(), 'failure_reason' => null]);
            } catch (Throwable $exception) {
                $scheduled->update(['status' => 'failed', 'completed_at' => now(), 'failure_reason' => mb_substr($exception->getMessage(), 0, 2000)]);
            }
        });

        return self::SUCCESS;
    }
}
