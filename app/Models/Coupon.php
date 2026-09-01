<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'type', 'value', 'minimum_amount', 'max_redemptions', 'max_per_user', 'starts_at', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'minimum_amount' => 'decimal:2', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'coupon_courses');
    }

    public function subscriptionPlans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'coupon_subscription_plan');
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
