<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['number', 'order_id', 'buyer_name', 'buyer_email', 'line_items', 'subtotal', 'discount', 'total', 'currency', 'issued_at'])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return ['line_items' => 'array', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'issued_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
