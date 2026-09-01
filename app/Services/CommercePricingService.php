<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CommercePricingService
{
    public function quote(User $user, ?Course $course, ?CourseBundle $bundle, ?string $couponCode, ?SubscriptionPlan $subscriptionPlan = null): array
    {
        $subtotal = (float) ($subscriptionPlan?->price ?? $bundle?->price ?? $course?->price ?? 0);
        if ($subtotal <= 0) {
            throw ValidationException::withMessages(['product' => 'This product does not require payment.']);
        }

        return $this->quoteAmount($subtotal, $couponCode, $user, $course, $bundle, $subscriptionPlan);
    }

    public function quoteSubscriptionPlan(SubscriptionPlan $plan, ?string $couponCode): array
    {
        return $this->quoteAmount((float) $plan->price, $couponCode, null, null, null, $plan);
    }

    private function quoteAmount(float $subtotal, ?string $couponCode, ?User $user, ?Course $course, ?CourseBundle $bundle, ?SubscriptionPlan $subscriptionPlan): array
    {
        $coupon = null;
        $discount = 0.0;
        if ($couponCode) {
            $coupon = Coupon::where('code', mb_strtoupper(trim($couponCode)))->first();
            $this->validateCoupon($coupon, $user, $course, $bundle, $subscriptionPlan, $subtotal);
            $discount = $coupon->type === 'percent' ? round($subtotal * ((float) $coupon->value / 100), 2) : (float) $coupon->value;
            $discount = min($discount, max(0, $subtotal));
        }

        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => round($subtotal - $discount, 2), 'coupon' => $coupon];
    }

    public function reserve(Coupon $coupon, User $user, Order $order, float $discount): CouponRedemption
    {
        $lockedCoupon = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->first();
        $order->loadMissing(['course', 'bundle', 'subscriptionPlan']);
        $this->validateCoupon($lockedCoupon, $user, $order->course, $order->bundle, $order->subscriptionPlan, (float) $order->original_amount);

        return CouponRedemption::create(['coupon_id' => $lockedCoupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => $discount, 'status' => 'reserved']);
    }

    private function validateCoupon(?Coupon $coupon, ?User $user, ?Course $course, ?CourseBundle $bundle, ?SubscriptionPlan $subscriptionPlan, float $subtotal): void
    {
        if (! $coupon || ! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon is invalid or inactive.']);
        }
        if ($subtotal < (float) $coupon->minimum_amount) {
            throw ValidationException::withMessages(['coupon_code' => 'Order does not meet the coupon minimum.']);
        }
        if ($coupon->max_redemptions && $coupon->redemptions()->whereIn('status', ['reserved', 'redeemed'])->count() >= $coupon->max_redemptions) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon redemption limit reached.']);
        }
        if ($user && $coupon->redemptions()->where('user_id', $user->id)->whereIn('status', ['reserved', 'redeemed'])->count() >= $coupon->max_per_user) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon already used by this account.']);
        }
        if ($subscriptionPlan && $coupon->subscriptionPlans()->exists() && ! $coupon->subscriptionPlans()->whereKey($subscriptionPlan->id)->exists()) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon does not apply to this subscription plan.']);
        }
        if ($subscriptionPlan && $coupon->courses()->exists() && ! $coupon->subscriptionPlans()->exists()) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon does not apply to this product.']);
        }
        if (! $subscriptionPlan && $coupon->courses()->exists() && ($bundle || ! $coupon->courses()->whereKey($course?->id)->exists())) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon does not apply to this product.']);
        }
        if (! $subscriptionPlan && $coupon->subscriptionPlans()->exists()) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon does not apply to this product.']);
        }
    }
}
