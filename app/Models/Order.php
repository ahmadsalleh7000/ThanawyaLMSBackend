<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'user_id', 'beneficiary_user_id', 'course_id', 'product_type', 'course_bundle_id', 'subscription_plan_id', 'coupon_id', 'original_amount', 'discount_amount', 'amount', 'currency', 'status', 'assignment_status', 'payment_provider', 'payment_method', 'session_token_hash', 'provider_session_id', 'provider_payment_id', 'refund_provider_id', 'paid_at', 'assigned_at', 'failed_at', 'refunded_at', 'refund_reason', 'provider_payload'])]
class Order extends Model
{
    protected function casts(): array
    {
        return ['original_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'amount' => 'decimal:2', 'paid_at' => 'datetime', 'assigned_at' => 'datetime', 'failed_at' => 'datetime', 'refunded_at' => 'datetime', 'provider_payload' => 'encrypted:array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function bundle()
    {
        return $this->belongsTo(CourseBundle::class, 'course_bundle_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function redemption()
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function manualPaymentSubmissions()
    {
        return $this->hasMany(ManualPaymentSubmission::class);
    }

    public function purchasedCourses()
    {
        return $this->product_type === 'bundle' ? ($this->bundle?->courses ?? collect()) : collect([$this->course])->filter();
    }
}
