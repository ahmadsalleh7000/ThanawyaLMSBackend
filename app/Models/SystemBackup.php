<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['disk', 'path', 'database_driver', 'status', 'size_bytes', 'content_sha256', 'is_encrypted', 'requested_by', 'completed_at', 'verified_at', 'error'])]
class SystemBackup extends Model
{
    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean', 'completed_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
