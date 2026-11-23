<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'duration_minutes', 'passing_score', 'randomize_questions', 'question_limit', 'is_published', 'available_from', 'due_at', 'max_retries', 'retry_delay_minutes', 'allow_retake_after_pass', 'randomize_options', 'show_correct_answers', 'show_explanations', 'require_all_answers', 'allow_question_navigation'])]
class Assessment extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'randomize_questions' => 'boolean', 'available_from' => 'datetime', 'due_at' => 'datetime', 'allow_retake_after_pass' => 'boolean', 'randomize_options' => 'boolean', 'show_correct_answers' => 'boolean', 'show_explanations' => 'boolean', 'require_all_answers' => 'boolean', 'max_retries' => 'integer', 'retry_delay_minutes' => 'integer', 'question_limit' => 'integer', 'allow_question_navigation' => 'boolean'];
    }

    public function questions()
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function drafts()
    {
        return $this->hasMany(AssessmentDraft::class);
    }
}
