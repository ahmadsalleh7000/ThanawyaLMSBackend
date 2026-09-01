<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderRefundService
{
    public function __construct(private readonly EnrollmentLifecycleService $enrollments) {}

    public function refund(Order $order, string $reason, User $actor): Order
    {
        if ($order->status !== 'completed') {
            throw ValidationException::withMessages(['refund' => 'Only completed payments can be refunded.']);
        }

        $refundId = match ($order->payment_provider) {
            'stripe' => $this->refundStripe($order),
            'local' => $this->refundLocal(),
            default => throw ValidationException::withMessages(['refund' => 'This payment provider does not support refunds.']),
        };

        return DB::transaction(function () use ($order, $reason, $refundId, $actor): Order {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages(['refund' => 'This order is no longer refundable.']);
            }
            $locked->update(['status' => 'refunded', 'assignment_status' => 'revoked', 'refunded_at' => now(), 'refund_provider_id' => $refundId, 'refund_reason' => $reason]);
            $locked->loadMissing(['beneficiary', 'course', 'bundle.courses']);
            $beneficiary = $locked->beneficiary ?? $locked->user;
            foreach ($locked->purchasedCourses() as $course) {
                $hasAlternativeAccess = Order::where(function ($query) use ($beneficiary) {
                    $query->where('beneficiary_user_id', $beneficiary->id)
                        ->orWhere(fn ($legacy) => $legacy->whereNull('beneficiary_user_id')->where('user_id', $beneficiary->id));
                })
                    ->where('status', 'completed')
                    ->where('id', '!=', $locked->id)
                    ->where(function ($query) use ($course) {
                        $query->where('course_id', $course->id)
                            ->orWhereHas('bundle.courses', fn ($courses) => $courses->where('courses.id', $course->id));
                    })->exists();
                if (! $hasAlternativeAccess) {
                    $enrollment = $beneficiary->enrollments()->where('course_id', $course->id)->first();
                    if ($enrollment) {
                        $this->enrollments->cancel($enrollment, $actor, 'Payment refunded: '.$reason);
                    }
                }
            }
            $locked->user->userNotifications()->create([
                'type' => 'payment', 'title' => 'Payment refunded', 'title_ar' => 'تم رد المبلغ',
                'message' => 'Your payment refund has been confirmed.', 'message_ar' => 'تم تأكيد رد مبلغ الدورة.',
                'action_url' => '/payment-history',
            ]);

            return $locked->refresh();
        });
    }

    private function refundStripe(Order $order): string
    {
        if (! $order->provider_payment_id) {
            throw ValidationException::withMessages(['refund' => 'The Stripe payment reference is missing.']);
        }
        $refund = $this->stripe()->asForm()->post('/refunds', [
            'payment_intent' => $order->provider_payment_id,
            'metadata[order_id]' => $order->public_id,
        ])->throw()->json();
        if (($refund['status'] ?? null) !== 'succeeded' || empty($refund['id'])) {
            throw ValidationException::withMessages(['refund' => 'Stripe has not confirmed the refund.']);
        }

        return $refund['id'];
    }

    private function refundLocal(): string
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['refund' => 'Local refunds are disabled outside development.']);
        }

        return 'local_refund_'.Str::random(20);
    }

    private function stripe(): PendingRequest
    {
        if (! config('payments.stripe.secret')) {
            throw ValidationException::withMessages(['refund' => 'Stripe is not configured.']);
        }

        return Http::baseUrl(config('payments.stripe.api_url'))->withToken(config('payments.stripe.secret'))->acceptJson()->timeout(15);
    }
}
