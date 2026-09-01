<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['order_id', 'user_id', 'channel', 'transaction_id', 'sender_identifier', 'transferred_at', 'submitted_amount', 'receipt_disk', 'receipt_path', 'receipt_original_name', 'receipt_mime', 'receipt_size', 'receipt_sha256', 'receipt_deleted_at', 'student_note', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class ManualPaymentSubmission extends Model
{
    protected function casts(): array
    {
        return ['transferred_at' => 'datetime', 'submitted_amount' => 'decimal:2', 'receipt_deleted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function matchingReceipts()
    {
        return $this->hasMany(self::class, 'receipt_sha256', 'receipt_sha256');
    }
}
