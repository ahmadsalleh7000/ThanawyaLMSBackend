<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

class InvoiceService
{
    public function issue(Order $order): Invoice
    {
        $order->loadMissing(['user', 'course', 'bundle.courses', 'subscriptionPlan']);
        $lines = $order->product_type === 'subscription'
            ? [['subscription_plan_id' => $order->subscription_plan_id, 'name' => $order->subscriptionPlan->name_en, 'name_ar' => $order->subscriptionPlan->name_ar]]
            : $order->purchasedCourses()->map(fn ($course) => ['course_id' => $course->id, 'name' => $course->title_en, 'name_ar' => $course->title])->all();

        return Invoice::firstOrCreate(['order_id' => $order->id], ['number' => 'INV-'.now()->format('Ym').'-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT), 'buyer_name' => $order->user->name, 'buyer_email' => $order->user->email, 'line_items' => $lines, 'subtotal' => (float) $order->original_amount > 0 ? $order->original_amount : $order->amount, 'discount' => $order->discount_amount, 'total' => $order->amount, 'currency' => $order->currency, 'issued_at' => now()]);
    }
}
