<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'course_id', 'rating', 'comment', 'status', 'moderated_by', 'moderated_at', 'moderation_note'])]
class CourseReview extends Model
{
    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
