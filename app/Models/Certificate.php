<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'user_id', 'course_id', 'assessment_attempt_id', 'issued_at', 'revoked_at', 'revocation_reason', 'revoked_by'])]
class Certificate extends Model
{
    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
