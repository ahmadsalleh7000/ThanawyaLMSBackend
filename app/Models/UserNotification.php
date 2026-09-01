<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'announcement_id', 'type', 'title', 'title_ar', 'message', 'message_ar', 'action_url', 'data', 'read_at'])]
class UserNotification extends Model
{
    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}
