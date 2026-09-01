<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['enrollment_id', 'actor_id', 'action', 'from_course_id', 'to_course_id', 'reason', 'metadata'])]
class EnrollmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
