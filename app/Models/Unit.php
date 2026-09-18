<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_subject_id', 'course_id', 'slug', 'title_ar', 'title_en', 'position', 'status', 'available_from', 'available_until'])]
class Unit extends Model
{
    protected function casts(): array
    {
        return ['available_from' => 'datetime', 'available_until' => 'datetime'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function academicSubject()
    {
        return $this->belongsTo(AcademicSubject::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function trainings()
    {
        return $this->morphMany(Training::class, 'trainable')->orderBy('position');
    }
}
