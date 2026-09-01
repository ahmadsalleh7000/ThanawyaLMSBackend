<?php

namespace App\Models;

use App\Enums\GuardianRelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['parent_id', 'student_id', 'relationship_type', 'relationship_details', 'grade', 'status', 'linked_at', 'invited_at', 'accepted_at', 'verified_at', 'rejected_at', 'revoked_at'])]
class ParentStudentLink extends Model
{
    protected function casts(): array
    {
        return [
            'relationship_type' => GuardianRelationshipType::class,
            'linked_at' => 'datetime',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
