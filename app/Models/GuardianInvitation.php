<?php

namespace App\Models;

use App\Enums\GuardianInvitationStatus;
use App\Enums\GuardianRelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'parent_user_id', 'parent_phone_e164', 'relationship_type', 'relationship_details', 'token_hash', 'status', 'expires_at', 'accepted_at', 'rejected_at', 'last_sent_at', 'guardian_account_was_created'])]
#[Hidden(['token_hash'])]
class GuardianInvitation extends Model
{
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'relationship_type' => GuardianRelationshipType::class,
            'status' => GuardianInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'guardian_account_was_created' => 'boolean',
        ];
    }
}
