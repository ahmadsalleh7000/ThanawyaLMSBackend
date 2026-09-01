<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['coupon_id', 'user_id', 'order_id', 'discount_amount', 'status', 'redeemed_at'])]
class CouponRedemption extends Model
{
    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime'];
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
