<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['uploaded_by', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'processing_status', 'processing_error', 'archived_at'])]
class MediaLibraryItem extends Model
{
    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->where('processing_status', 'ready');
    }

    public function attachments()
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
