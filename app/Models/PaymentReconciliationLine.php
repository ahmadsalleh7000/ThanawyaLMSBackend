<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period_id', 'channel', 'expected_inflow', 'expected_refunds', 'actual_inflow', 'actual_refunds', 'approved_count', 'refund_count', 'note'])]
class PaymentReconciliationLine extends Model
{
    protected function casts(): array
    {
        return ['expected_inflow' => 'decimal:2', 'expected_refunds' => 'decimal:2', 'actual_inflow' => 'decimal:2', 'actual_refunds' => 'decimal:2'];
    }

    public function period()
    {
        return $this->belongsTo(PaymentReconciliationPeriod::class, 'period_id');
    }

    public function getVarianceAttribute(): float
    {
        return round(((float) $this->actual_inflow - (float) $this->actual_refunds) - ((float) $this->expected_inflow - (float) $this->expected_refunds), 2);
    }
}
