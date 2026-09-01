<?php

namespace App\Console\Commands;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendEngagementNotifications extends Command
{
    protected $signature = 'engagement:send-notifications';

    protected $description = 'Send due learning reminders and weekly parent progress summaries';

    public function handle(): int
    {
        $sent = 0;
        NotificationPreference::with('user')->chunkById(100, function ($preferences) use (&$sent): void {
            foreach ($preferences as $preference) {
                $now = now($preference->timezone);
                if ($this->isQuiet($preference, $now)) {
                    continue;
                }
                if ($preference->user->role === User::ROLE_STUDENT) {
                    $sent += $this->sendLearningReminder($preference, $now);
                }
                if ($preference->user->role === User::ROLE_PARENT) {
                    $sent += $this->sendParentSummary($preference, $now);
                }
            }
        });
        $this->info("Sent {$sent} engagement notifications.");

        return self::SUCCESS;
    }

    private function sendLearningReminder(NotificationPreference $preference, Carbon $now): int
    {
        $student = $preference->user;
        if (! $preference->learning_reminders || $now->format('H:i') < substr($preference->reminder_time, 0, 5) || $preference->last_learning_reminder_at?->isSameDay($now)) {
            return 0;
        }
        if (! $student->enrollments()->active()->exists() || $student->lessonCompletions()->whereDate('completed_at', $now->toDateString())->exists()) {
            return 0;
        }
        $student->userNotifications()->create([
            'type' => 'learning_reminder',
            'title' => 'Your next lesson is ready',
            'title_ar' => 'درسك التالي جاهز',
            'message' => 'A short learning step today keeps your weekly goal moving.',
            'message_ar' => 'خطوة تعلم قصيرة اليوم تحافظ على تقدم هدفك الأسبوعي.',
            'action_url' => '/planner',
        ]);
        $preference->update(['last_learning_reminder_at' => now()]);

        return 1;
    }

    private function sendParentSummary(NotificationPreference $preference, Carbon $now): int
    {
        $parent = $preference->user;
        if (! $preference->parent_progress_summaries || $now->dayOfWeek !== $preference->weekly_summary_day || $now->format('H:i') < '09:00' || $preference->last_parent_summary_at?->greaterThanOrEqualTo($now->copy()->startOfWeek())) {
            return 0;
        }
        $students = $parent->linkedStudents()->get();
        if ($students->isEmpty()) {
            return 0;
        }
        $completed = $students->sum(fn (User $student) => $student->lessonCompletions()->where('completed_at', '>=', $now->copy()->startOfWeek())->count());
        $parent->userNotifications()->create([
            'type' => 'parent_summary',
            'title' => 'Your weekly family learning summary',
            'title_ar' => 'ملخص تعلم الأسرة الأسبوعي',
            'message' => "Your linked students completed {$completed} lessons this week.",
            'message_ar' => "أكمل طلابك المرتبطون {$completed} دروس هذا الأسبوع.",
            'action_url' => '/parent-dashboard',
            'data' => ['completedLessons' => $completed, 'studentCount' => $students->count()],
        ]);
        $preference->update(['last_parent_summary_at' => now()]);

        return 1;
    }

    private function isQuiet(NotificationPreference $preference, Carbon $now): bool
    {
        if (! $preference->quiet_hours_enabled) {
            return false;
        }
        $time = $now->format('H:i');
        $start = substr($preference->quiet_hours_start, 0, 5);
        $end = substr($preference->quiet_hours_end, 0, 5);

        return $start <= $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
    }
}
