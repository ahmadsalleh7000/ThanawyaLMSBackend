<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['live_session_id', 'student_id', 'marked_by', 'status', 'marked_at'])]
class LiveSessionAttendance extends Model
{
    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
