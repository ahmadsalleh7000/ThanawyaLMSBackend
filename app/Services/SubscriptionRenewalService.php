<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionRenewalService
{
    public function checkout(User $student, SubscriptionPlan $plan, AcademicTerm $term, string $channel): array
    {
        $settings = app(ManualPaymentSettingsService::class);
        $method = $settings->channels()[$channel] ?? null;
        abort_unless($student->role === User::ROLE_STUDENT && $student->isEligibleForAccess(), 403);
        abort_unless(config('payments.provider') === 'manual_transfer', 422, 'Subscription renewal currently uses verified manual transfers.');
        abort_unless($method && filled($method['destination']), 422, __('This payment channel is unavailable.'));
        abort_unless($plan->is_active && $term->is_active && $term->academicYear->is_active, 422, __('This study period is not available.'));
        abort_if($term->ends_on?->endOfDay()->isPast(), 422, __('This study period has ended. Choose a current or upcoming term.'));
        abort_unless($plan->academicTerms()->whereKey($term->id)->exists(), 422, __('This plan does not cover the selected term.'));
        $terms = $plan->academicTerms()->where('academic_year_id', $term->academic_year_id)
            ->where('academic_grade_id', $term->academic_grade_id)->where('is_active', true)
            ->when($plan->billing_period !== 'year', fn ($query) => $query->whereKey($term->id))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->get();
        $token = Str::random(64);
        $order = DB::transaction(function () use ($student, $plan, $terms, $channel, $token, $settings) {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_if($student->studentSubscriptions()->whereIn('academic_term_id', $terms->pluck('id'))->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists(), 409, __('You already have access to this study period. No additional payment is needed.'));
            abort_if($student->orders()->where('product_type', 'subscription')->whereIn('status', ['submitted', 'needs_information'])->exists(), 409, __('Review your existing subscription payment in payment history before starting another.'));
            $pending = $student->orders()->where('product_type', 'subscription')->where('status', 'pending')->latest()->first();
            $attributes = [
                'subscription_plan_id' => $plan->id, 'beneficiary_user_id' => $student->id,
                'product_type' => 'subscription', 'original_amount' => $plan->price, 'discount_amount' => 0,
                'amount' => $plan->price, 'currency' => $plan->currency, 'status' => 'pending',
                'assignment_status' => 'pending', 'payment_provider' => 'manual_transfer', 'payment_method' => $channel,
                'session_token_hash' => hash('sha256', $token),
                'provider_payload' => ['academic_term_ids' => $terms->pluck('id')->all(), 'source' => 'subscription_renewal'],
            ];
            if ($pending && $pending->created_at->addMinutes($settings->expiresMinutes())->isFuture()) {
                // A refresh resumes this order rather than creating another transfer request.
                abort_unless($pending->subscription_plan_id === $plan->id && ($pending->provider_payload['academic_term_ids'] ?? []) === $terms->pluck('id')->all(), 409, 'Finish the existing payment order or contact support before choosing another plan.');
                $pending->update(['session_token_hash' => hash('sha256', $token), 'payment_method' => $channel]);

                return $pending;
            }

            return $student->orders()->create(['public_id' => (string) Str::uuid()] + $attributes);
        });
        if ((float) $order->amount <= 0) {
            app(OrderPaymentService::class)->complete($order, 'free-subscription-plan', ['source' => 'subscription_renewal']);
        }

        return ['session_id' => $token, 'order_id' => $order->public_id, 'status' => $order->fresh()->status,
            'amount' => (float) $order->amount, 'currency' => $order->currency,
            'payment' => ['channel' => $channel, 'label' => $method['label'], 'destination' => $method['destination']],
            'coverage' => $terms->map(fn ($item) => ['id' => $item->id, 'nameAr' => $item->name_ar, 'nameEn' => $item->name_en, 'startsAt' => $item->starts_on?->toDateString(), 'endsAt' => $item->ends_on?->toDateString()])->values(),
            'expiresAt' => $order->created_at->addMinutes($settings->expiresMinutes())->toISOString()];
    }
}
