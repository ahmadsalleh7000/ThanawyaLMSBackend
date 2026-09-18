<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'academic_term_id', 'teacher_id', 'name', 'code', 'status'])]
class Cohort extends Model
{
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function academicTerm()
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'cohort_members', 'cohort_id', 'student_id')->withPivot('added_by')->withTimestamps();
    }
}
