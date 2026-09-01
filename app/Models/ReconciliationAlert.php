<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period_id', 'type', 'channel', 'severity', 'amount', 'message', 'detected_at', 'resolved_at', 'resolved_by', 'resolution_note'])]
class ReconciliationAlert extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'detected_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function period()
    {
        return $this->belongsTo(PaymentReconciliationPeriod::class, 'period_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
