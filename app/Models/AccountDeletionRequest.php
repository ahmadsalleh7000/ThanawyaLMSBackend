<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'status', 'reason', 'scheduled_for', 'reviewed_by', 'reviewed_at', 'review_note', 'cancelled_at', 'processed_at', 'legal_records_until', 'legal_records_pruned_at', 'anonymization_reference', 'processing_error', 'policy_version'])]
class AccountDeletionRequest extends Model
{
    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime', 'processed_at' => 'datetime', 'legal_records_until' => 'datetime', 'legal_records_pruned_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
