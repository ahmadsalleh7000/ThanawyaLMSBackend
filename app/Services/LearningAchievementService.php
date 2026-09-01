<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class LearningAchievementService
{
    public function summary(User $user, ?Collection $completions = null): array
    {
        $completions ??= $user->lessonCompletions()->get();
        $completed = $completions->count();
        $passed = $user->assessmentAttempts()->where('passed', true)->count();
        $certificates = $user->certificates()->whereNull('revoked_at')->count();

        return ['achievements' => collect([
            ['key' => 'first_lesson', 'progress' => min($completed, 1), 'target' => 1, 'earned' => $completed >= 1],
            ['key' => 'five_lessons', 'progress' => min($completed, 5), 'target' => 5, 'earned' => $completed >= 5],
            ['key' => 'ten_lessons', 'progress' => min($completed, 10), 'target' => 10, 'earned' => $completed >= 10],
            ['key' => 'first_pass', 'progress' => min($passed, 1), 'target' => 1, 'earned' => $passed >= 1],
            ['key' => 'first_certificate', 'progress' => min($certificates, 1), 'target' => 1, 'earned' => $certificates >= 1],
        ])];
    }
}
