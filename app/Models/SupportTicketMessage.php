<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['support_ticket_id', 'user_id', 'body', 'attachment_disk', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'])]
class SupportTicketMessage extends Model
{
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
