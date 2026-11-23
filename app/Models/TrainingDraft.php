<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question_snapshot', 'training_id', 'student_id', 'answers', 'current_index', 'started_at', 'expires_at'])]
class TrainingDraft extends Model
{
    protected $hidden = ['question_snapshot'];

    protected function casts(): array
    {
        return ['question_snapshot' => 'array', 'answers' => 'array', 'current_index' => 'integer', 'started_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function training()
    {
        return $this->belongsTo(Training::class);
    }
}
