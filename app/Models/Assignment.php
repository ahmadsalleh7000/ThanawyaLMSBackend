<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lesson_id', 'title', 'title_en', 'description', 'description_en', 'due_at', 'is_published', 'position'])]
class Assignment extends Model
{
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'is_published' => 'boolean'];
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
