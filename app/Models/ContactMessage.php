<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['public_reference', 'user_id', 'name', 'email', 'subject', 'message', 'admin_note', 'last_reply_body', 'status', 'handled_by', 'replied_by', 'locale', 'ip_hash', 'resolved_at', 'replied_at'])]
class ContactMessage extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ContactMessage $message): void {
            $message->public_reference ??= self::generatePublicReference();
        });
    }

    public static function generatePublicReference(): string
    {
        do {
            $reference = 'CONTACT-'.Str::upper(Str::random(8));
        } while (self::where('public_reference', $reference)->exists());

        return $reference;
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
