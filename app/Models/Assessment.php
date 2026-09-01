<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'duration_minutes', 'passing_score', 'randomize_questions', 'question_limit', 'is_published', 'available_from', 'due_at'])]
class Assessment extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'randomize_questions' => 'boolean', 'available_from' => 'datetime', 'due_at' => 'datetime'];
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
