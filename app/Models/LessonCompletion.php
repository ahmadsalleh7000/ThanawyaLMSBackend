<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'lesson_id', 'completed_at'])]
class LessonCompletion extends Model
{
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
