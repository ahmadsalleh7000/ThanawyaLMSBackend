<?php

namespace App\Services;

use App\Models\StudentSupportCase;
use App\Models\User;
use Carbon\CarbonImmutable;

class StudentRiskService
{
    public function scan(): int
    {
        $detected = 0;
        User::students()->where('is_active', true)
            ->withCount(['enrollments as active_enrollments_count' => fn ($query) => $query->active()])
            ->withMax('lessonCompletions', 'completed_at')
            ->withAvg('assessmentAttempts', 'score')
            ->chunkById(200, function ($students) use (&$detected): void {
                foreach ($students as $student) {
                    if ($student->active_enrollments_count === 0) {
                        continue;
                    }
                    [$score, $signals] = $this->score($student);
                    if ($score < 40) {
                        continue;
                    }
                    $case = StudentSupportCase::firstOrNew(['student_id' => $student->id]);
                    if ($case->exists && $case->status === 'resolved') {
                        continue;
                    }
                    $case->fill(['status' => $case->status ?: 'open', 'risk_level' => $score >= 70 ? 'high' : 'medium', 'risk_score' => $score, 'risk_signals' => $signals, 'detected_at' => now()])->save();
                    $detected++;
                }
            });

        return $detected;
    }

    private function score(User $student): array
    {
        $score = 0;
        $signals = [];
        $lastActivity = $student->lesson_completions_max_completed_at ? CarbonImmutable::parse($student->lesson_completions_max_completed_at) : null;
        if (! $lastActivity || $lastActivity->lt(now()->subDays(14))) {
            $score += 45;
            $signals[] = ['key' => 'inactivity', 'label' => 'لا يوجد إكمال درس خلال 14 يوماً'];
        }
        if ($student->assessment_attempts_avg_score !== null && (float) $student->assessment_attempts_avg_score < 60) {
            $score += 40;
            $signals[] = ['key' => 'low_scores', 'label' => 'متوسط التقييمات أقل من 60%'];
        }
        if ($student->active_enrollments_count >= 3) {
            $score += 15;
            $signals[] = ['key' => 'course_load', 'label' => 'مسجل في ثلاثة مسارات فعالة أو أكثر'];
        }

        return [min(100, $score), $signals];
    }
}
