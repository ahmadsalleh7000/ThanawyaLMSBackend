<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lesson_id', 'prerequisite_lesson_id'])]
class LessonPrerequisite extends Model
{
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function prerequisiteLesson()
    {
        return $this->belongsTo(Lesson::class, 'prerequisite_lesson_id');
    }
}
