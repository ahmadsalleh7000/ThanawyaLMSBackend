<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\ManualPaymentSettingsService;
use Illuminate\Http\JsonResponse;

class RegistrationOptionsController extends Controller
{
    public function __invoke(ManualPaymentSettingsService $payments): JsonResponse
    {
        $channels = collect($payments->channels())
            ->filter(fn (array $channel) => filled($channel['destination']))
            ->map(fn (array $channel, string $code) => [
                'code' => $code,
                'label' => $channel['label'],
                'destination' => $channel['destination'],
            ])->values();

        return response()->json(['data' => [
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('display_order')->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id' => $plan->id, 'code' => $plan->code, 'nameAr' => $plan->name_ar,
                    'nameEn' => $plan->name_en, 'period' => $plan->billing_period,
                    'price' => (float) $plan->price, 'currency' => $plan->currency,
                ]),
            'paymentMethods' => $channels,
            'reviewEtaHours' => (int) config('payments.manual_transfer.review_eta_hours', 24),
        ]]);
    }
}
