<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'created_by', 'event', 'label', 'snapshot', 'restored_at'])]
class CourseVersion extends Model
{
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'restored_at' => 'datetime'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
