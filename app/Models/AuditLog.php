<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
