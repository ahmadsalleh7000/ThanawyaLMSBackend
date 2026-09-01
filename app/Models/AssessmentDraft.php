<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assessment_id', 'user_id', 'answers', 'current_index', 'started_at', 'expires_at'])]
class AssessmentDraft extends Model
{
    protected function casts(): array
    {
        return ['answers' => 'array', 'current_index' => 'integer', 'started_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
