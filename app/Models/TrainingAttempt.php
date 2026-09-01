<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['training_id', 'student_id', 'answers', 'score', 'status', 'started_at', 'submitted_at'])]
class TrainingAttempt extends Model
{
    protected function casts(): array { return ['answers' => 'array', 'score' => 'decimal:2', 'started_at' => 'datetime', 'submitted_at' => 'datetime']; }
    public function training() { return $this->belongsTo(Training::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
}
