<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'starts_on', 'ends_on', 'status', 'created_by', 'closed_by', 'closed_at', 'close_note'])]
class PaymentReconciliationPeriod extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];
    }

    public function lines()
    {
        return $this->hasMany(PaymentReconciliationLine::class, 'period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function alerts()
    {
        return $this->hasMany(ReconciliationAlert::class, 'period_id');
    }
}
