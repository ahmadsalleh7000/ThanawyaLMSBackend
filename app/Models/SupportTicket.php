<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'user_id', 'assigned_to', 'subject', 'category', 'priority', 'status', 'last_message_at', 'resolved_at'])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest();
    }
}
