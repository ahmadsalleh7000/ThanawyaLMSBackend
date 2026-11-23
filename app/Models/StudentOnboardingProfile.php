<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tour_status', 'tour_dismissed_at', 'learning_goals', 'student_id', 'grade', 'semester', 'learning_goal', 'experience_level', 'weekly_goal_minutes', 'leaderboard_opt_in', 'placement_answers', 'placement_score', 'recommended_level', 'recommended_course_id', 'completed_at', 'skipped_at'])]
class StudentOnboardingProfile extends Model
{
    protected function casts(): array
    {
        return ['tour_dismissed_at' => 'datetime', 'learning_goals' => 'array', 'leaderboard_opt_in' => 'boolean', 'placement_answers' => 'encrypted:array', 'placement_score' => 'integer', 'weekly_goal_minutes' => 'integer', 'completed_at' => 'datetime', 'skipped_at' => 'datetime'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recommendedCourse()
    {
        return $this->belongsTo(Course::class, 'recommended_course_id');
    }
}
