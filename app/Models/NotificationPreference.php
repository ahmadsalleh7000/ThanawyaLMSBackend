<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'payment_updates', 'course_updates', 'learning_reminders', 'parent_progress_summaries', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end', 'reminder_time', 'weekly_summary_day', 'timezone', 'last_learning_reminder_at', 'last_parent_summary_at'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return [
            'payment_updates' => 'boolean',
            'course_updates' => 'boolean',
            'learning_reminders' => 'boolean',
            'parent_progress_summaries' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
            'weekly_summary_day' => 'integer',
            'last_learning_reminder_at' => 'datetime',
            'last_parent_summary_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
