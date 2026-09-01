<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lesson_id', 'media_library_item_id', 'kind', 'position', 'status', 'available_from', 'available_until', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class MediaAsset extends Model
{
    protected function casts(): array { return ['available_from' => 'datetime', 'available_until' => 'datetime']; }
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function libraryItem()
    {
        return $this->belongsTo(MediaLibraryItem::class, 'media_library_item_id');
    }
}
