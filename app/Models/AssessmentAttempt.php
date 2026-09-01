<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assessment_id', 'user_id', 'answers', 'score', 'passed', 'submitted_at'])]
class AssessmentAttempt extends Model
{
    protected function casts(): array
    {
        return ['answers' => 'array', 'passed' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
