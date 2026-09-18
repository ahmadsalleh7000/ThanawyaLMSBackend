<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StudentSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->role === User::ROLE_STUDENT, 403, 'Only student accounts have subscriptions.');

        $subscriptions = $student->studentSubscriptions()
            ->with(['plan:id,code,name_ar,name_en,billing_period,price,currency', 'term.academicYear:id,code,name_ar,name_en', 'term.grade:id,code,name_ar,name_en'])
            ->latest('starts_at')->get();
        $active = $subscriptions->filter(fn (StudentSubscription $subscription) => $subscription->status === 'active'
            && (! $subscription->ends_at || $subscription->ends_at->isFuture()));
        $subscriptionOrders = $student->orders()->where('product_type', 'subscription')
            ->with(['subscriptionPlan:id,code,name_ar,name_en,billing_period,price,currency', 'manualPaymentSubmissions' => fn ($query) => $query->latest()])
            ->latest()->get();
        $actionableOrder = $subscriptionOrders->first(fn (Order $order) => in_array($order->status, ['pending', 'submitted', 'needs_information'], true));

        $state = match (true) {
            $active->isNotEmpty() => 'active',
            $actionableOrder?->status === 'submitted' => 'pending_review',
            $actionableOrder?->status === 'needs_information' => 'rejected',
            $actionableOrder?->status === 'pending' => 'pending_payment',
            $subscriptions->isNotEmpty() => 'expired',
            default => 'none',
        };

        return response()->json(['data' => [
            'state' => $state,
            'current' => $active->isEmpty() ? null : $this->serializeCurrent($active),
            'pendingOrder' => $actionableOrder ? $this->serializeOrder($actionableOrder) : null,
            'history' => $subscriptionOrders->map(fn (Order $order) => $this->serializeOrder($order))->values(),
            'availableActions' => [
                'canViewCourses' => $active->isNotEmpty(),
                'canViewPaymentHistory' => $subscriptionOrders->isNotEmpty(),
                'canResubmitPayment' => $actionableOrder?->status === 'needs_information',
                'canContactSupport' => true,
                'canUpgrade' => false,
                'canRenew' => false,
                'canCancel' => false,
            ],
        ]]);
    }

    private function serializeCurrent($subscriptions): array
    {
        $first = $subscriptions->first();

        return [
            'id' => $first->id,
            'status' => 'active',
            'plan' => [
                'id' => $first->plan->id,
                'code' => $first->plan->code,
                'nameAr' => $first->plan->name_ar,
                'nameEn' => $first->plan->name_en,
                'period' => $first->plan->billing_period,
                'price' => (float) $first->plan->price,
                'currency' => $first->plan->currency,
            ],
            'grade' => ['code' => $first->term->grade->code, 'nameAr' => $first->term->grade->name_ar, 'nameEn' => $first->term->grade->name_en],
            'academicYear' => ['code' => $first->term->academicYear->code, 'nameAr' => $first->term->academicYear->name_ar, 'nameEn' => $first->term->academicYear->name_en],
            'semesters' => $subscriptions->map(fn (StudentSubscription $subscription) => [
                'code' => $subscription->term->code,
                'nameAr' => $subscription->term->name_ar,
                'nameEn' => $subscription->term->name_en,
            ])->unique('code')->values(),
            'startsAt' => $subscriptions->min('starts_at')?->toISOString(),
            'endsAt' => $subscriptions->filter(fn ($subscription) => $subscription->ends_at)->max('ends_at')?->toISOString(),
        ];
    }

    private function serializeOrder(Order $order): array
    {
        $submission = $order->manualPaymentSubmissions->first();

        return [
            'id' => $order->public_id,
            'status' => $order->status,
            'submissionStatus' => $submission?->status,
            'reviewNote' => $submission?->review_note,
            'plan' => $order->subscriptionPlan ? [
                'id' => $order->subscriptionPlan->id,
                'code' => $order->subscriptionPlan->code,
                'nameAr' => $order->subscriptionPlan->name_ar,
                'nameEn' => $order->subscriptionPlan->name_en,
                'period' => $order->subscriptionPlan->billing_period,
            ] : null,
            'originalAmount' => (float) $order->original_amount,
            'discountAmount' => (float) $order->discount_amount,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'paymentMethod' => $order->payment_method,
            'createdAt' => $order->created_at->toISOString(),
            'submittedAt' => $submission?->created_at?->toISOString(),
        ];
    }
}
