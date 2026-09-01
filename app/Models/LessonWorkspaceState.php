<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'lesson_id', 'note', 'is_bookmarked', 'playback_seconds', 'playback_rate', 'content_opened_at', 'practice_passed_at'])]
class LessonWorkspaceState extends Model
{
    protected function casts(): array
    {
        return ['is_bookmarked' => 'boolean', 'playback_seconds' => 'integer', 'playback_rate' => 'float', 'content_opened_at' => 'datetime', 'practice_passed_at' => 'datetime'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
