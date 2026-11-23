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
            'plans' => SubscriptionPlan::where('is_active', true)->with(['academicTerms.grade', 'academicTerms.academicYear'])->orderBy('display_order')->get()
                ->map(fn (SubscriptionPlan $plan) => [
                    'id' => $plan->id, 'code' => $plan->code, 'nameAr' => $plan->name_ar,
                    'nameEn' => $plan->name_en, 'period' => $plan->billing_period,
                    'price' => (float) $plan->price, 'currency' => $plan->currency,
                    'terms' => $plan->academicTerms->filter(fn ($term) => $term->is_active && $term->academicYear->is_active && (! $term->ends_on || $term->ends_on->endOfDay()->isFuture()))->map(fn ($term) => ['id' => $term->id, 'nameAr' => $term->grade->name_ar.' — '.$term->name_ar.' — '.$term->academicYear->name_ar, 'nameEn' => $term->grade->name_en.' — '.$term->name_en.' — '.$term->academicYear->name_en, 'startsAt' => $term->starts_on?->toDateString(), 'endsAt' => $term->ends_on?->toDateString()])->values(),
                ]),
            'paymentMethods' => $channels,
            'reviewEtaHours' => (int) config('payments.manual_transfer.review_eta_hours', 24),
        ]]);
    }
}
