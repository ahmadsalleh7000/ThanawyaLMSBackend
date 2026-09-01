<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fingerprint', 'type', 'severity', 'title', 'message', 'context', 'occurrences', 'first_seen_at', 'last_seen_at', 'resolved_at', 'resolved_by', 'resolution_note'])]
class ApplicationIncident extends Model
{
    protected function casts(): array
    {
        return ['context' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
