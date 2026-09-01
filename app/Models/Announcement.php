<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by', 'title', 'title_ar', 'message', 'message_ar', 'target_type', 'course_id', 'grade', 'action_url', 'status', 'publish_at', 'expires_at', 'published_at', 'cancelled_at'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'expires_at' => 'datetime', 'published_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')->where('publish_at', '<=', now())
            ->where(fn ($inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
