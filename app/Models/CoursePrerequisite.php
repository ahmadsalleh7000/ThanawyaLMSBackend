<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'prerequisite_course_id', 'minimum_progress'])]
class CoursePrerequisite extends Model
{
    protected function casts(): array
    {
        return ['minimum_progress' => 'integer'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function prerequisiteCourse()
    {
        return $this->belongsTo(Course::class, 'prerequisite_course_id');
    }
}
