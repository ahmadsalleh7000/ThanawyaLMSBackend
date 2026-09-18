<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['assignment_id', 'user_id', 'body', 'file_path', 'file_original_name', 'file_mime', 'file_size', 'file_sha256', 'status', 'submitted_at', 'score', 'feedback', 'graded_by', 'graded_at'])]
class AssignmentSubmission extends Model
{
    protected function casts(): array
    {
        return ['file_size' => 'integer', 'submitted_at' => 'datetime', 'score' => 'integer', 'graded_at' => 'datetime'];
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
