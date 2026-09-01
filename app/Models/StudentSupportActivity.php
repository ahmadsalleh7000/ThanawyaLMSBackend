<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_support_case_id', 'created_by', 'type', 'channel', 'body', 'outcome', 'due_at', 'completed_at'])]
class StudentSupportActivity extends Model
{
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function supportCase()
    {
        return $this->belongsTo(StudentSupportCase::class, 'student_support_case_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
