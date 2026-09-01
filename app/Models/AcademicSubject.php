<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_term_id', 'subject_id', 'display_order', 'is_active'])]
class AcademicSubject extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class)->orderBy('position');
    }

    public function units()
    {
        return $this->hasMany(Unit::class)->orderBy('position');
    }
}
