<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['course_id', 'created_by', 'title', 'title_en', 'description', 'starts_at', 'ends_at', 'meeting_url', 'recording_media_id', 'status'])]
class LiveSession extends Model
{
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function recording()
    {
        return $this->belongsTo(MediaAsset::class, 'recording_media_id');
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
