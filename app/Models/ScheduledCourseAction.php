<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'created_by', 'action', 'run_at', 'status', 'completed_at', 'failure_reason'])]
class ScheduledCourseAction extends Model
{
    protected function casts(): array
    {
        return ['run_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('run_at', '<=', now());
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
