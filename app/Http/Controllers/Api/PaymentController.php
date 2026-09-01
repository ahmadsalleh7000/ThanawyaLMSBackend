<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercePricingService;
use App\Services\ManualPaymentSettingsService;
use App\Services\OrderPaymentService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function checkout(Request $request, CommercePricingService $pricing, ManualPaymentSettingsService $manualSettings): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [User::ROLE_STUDENT, User::ROLE_PARENT], true), 403, 'Only student and parent accounts can purchase courses.');
        $validated = $request->validate([
            'product_type' => ['sometimes', Rule::in(['course', 'bundle'])],
            'course_id' => ['nullable', 'required_unless:product_type,bundle', 'string', Rule::exists('courses', 'id')->where('is_published', true)],
            'bundle_id' => ['nullable', 'required_if:product_type,bundle', 'integer', 'exists:course_bundles,id'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string'],
            'student_id' => ['nullable', 'integer'],
        ]);
        $buyer = $request->user();
        $beneficiary = $buyer;
        if ($buyer->role === User::ROLE_PARENT) {
            $beneficiary = User::students()->find($validated['student_id'] ?? 0);
            if (! $beneficiary) {
                abort(422, 'Select an approved linked student for this purchase.');
            }
            Gate::forUser($buyer)->authorize('purchaseFor', $beneficiary);
        } elseif (isset($validated['student_id']) && (int) $validated['student_id'] !== $buyer->id) {
            abort(422, 'Student accounts can only purchase for themselves.');
        }
        $productType = $validated['product_type'] ?? 'course';
        $course = $productType === 'course' ? Course::whereNull('archived_at')->findOrFail($validated['course_id']) : null;
        $bundle = $productType === 'bundle'
            ? CourseBundle::where('is_active', true)->with(['courses' => fn ($query) => $query->where('is_published', true)->whereNull('archived_at')])->findOrFail($validated['bundle_id'])
            : null;
        abort_if($bundle && $bundle->courses->isEmpty(), 422, 'This bundle has no available courses.');
        abort_if($bundle && $bundle->courses->count() !== $bundle->courses()->count(), 422, 'This learning path is temporarily unavailable.');
        $productCourses = $bundle?->courses ?? collect([$course]);
        abort_if($beneficiary->enrollments()->active()->whereIn('course_id', $productCourses->pluck('id'))->exists(), 409, 'The selected student already has access to one or more courses in this product.');
        $quote = $pricing->quote($buyer, $course, $bundle, $validated['coupon_code'] ?? null);

        $provider = config('payments.provider');
        abort_if($provider === 'local' && ! app()->environment(['local', 'testing']), 503, 'Local payments are disabled outside development.');
        abort_unless(in_array($provider, ['local', 'stripe', 'manual_transfer'], true), 503, 'The payment provider is not configured.');
        $manualChannels = $manualSettings->channels();
        if ($provider === 'manual_transfer') {
            abort_unless(isset($manualChannels[$validated['payment_method']]) && filled($manualChannels[$validated['payment_method']]['destination']), 422, 'This payment channel is unavailable.');
        } else {
            abort_unless($validated['payment_method'] === 'card', 422, 'Only card payments are available through this provider.');
        }

        [$token, $order] = DB::transaction(function () use ($buyer, $beneficiary, $course, $productType, $bundle, $quote, $provider, $validated, $pricing): array {
            $token = Str::random(64);
            $order = $buyer->orders()->create([
                'public_id' => (string) Str::uuid(),
                'beneficiary_user_id' => $beneficiary->id,
                'course_id' => $course?->id,
                'product_type' => $productType,
                'course_bundle_id' => $bundle?->id,
                'coupon_id' => $quote['coupon']?->id,
                'original_amount' => $quote['subtotal'],
                'discount_amount' => $quote['discount'],
                'amount' => $quote['total'],
                'currency' => config('payments.currency'),
                'status' => 'pending',
                'assignment_status' => 'pending',
                'payment_provider' => $provider,
                'payment_method' => $validated['payment_method'],
                'session_token_hash' => hash('sha256', $token),
            ]);
            if ($quote['coupon']) {
                $pricing->reserve($quote['coupon'], $buyer, $order, $quote['discount']);
            }

            return [$token, $order];
        }, 3);

        if ($provider === 'stripe') {
            $session = $this->stripe()->asForm()->post('/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => config('payments.frontend_url').'/order-confirmation?session_id='.$token,
                'cancel_url' => config('payments.frontend_url').'/courses',
                'client_reference_id' => $order->public_id,
                'metadata[order_id]' => $order->public_id,
                'metadata[user_id]' => (string) $request->user()->id,
                'metadata[beneficiary_user_id]' => (string) $beneficiary->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($order->currency),
                'line_items[0][price_data][unit_amount]' => (int) round((float) $order->amount * 100),
                'line_items[0][price_data][product_data][name]' => $bundle?->name_en ?: ($course->title_en ?: $course->title),
            ])->throw()->json();
            $order->update(['provider_session_id' => $session['id']]);

            return response()->json(['session_id' => $token, 'redirect_url' => $session['url']], 201);
        }

        if ($provider === 'manual_transfer') {
            $channel = $manualChannels[$validated['payment_method']];

            return response()->json([
                'session_id' => $token,
                'order_id' => $order->public_id,
                'status' => 'pending',
                'amount' => (float) $order->amount,
                'currency' => $order->currency,
                'payment' => ['channel' => $validated['payment_method'], 'label' => $channel['label'], 'destination' => $channel['destination']],
                'expires_at' => $order->created_at->addMinutes($manualSettings->expiresMinutes())->toISOString(),
                'beneficiary' => $beneficiary->only(['id', 'name']),
            ], 201);
        }

        return response()->json(['session_id' => $token, 'redirect_url' => config('payments.frontend_url').'/order-confirmation?session_id='.$token], 201);
    }

    public function confirm(Request $request, OrderPaymentService $payments, ManualPaymentSettingsService $manualSettings): JsonResponse
    {
        $validated = $request->validate(['session_id' => ['required', 'string', 'size:64']]);
        $order = $request->user()->orders()->where('session_token_hash', hash('sha256', $validated['session_id']))->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $order);

        if ($order->status !== 'completed') {
            if ($order->payment_provider === 'stripe') {
                $session = $this->stripe()->get('/checkout/sessions/'.$order->provider_session_id)->throw()->json();
                abort_unless(
                    ($session['payment_status'] ?? null) === 'paid'
                    && ($session['metadata']['order_id'] ?? null) === $order->public_id
                    && ($session['metadata']['user_id'] ?? null) === (string) $request->user()->id
                    && (! isset($session['metadata']['beneficiary_user_id']) || $session['metadata']['beneficiary_user_id'] === (string) $order->beneficiary_user_id)
                    && ($session['amount_total'] ?? -1) === (int) round((float) $order->amount * 100)
                    && strtoupper($session['currency'] ?? '') === $order->currency,
                    409,
                    'Payment has not been verified.',
                );
                $providerPaymentId = $session['payment_intent'] ?? null;
                $payload = ['payment_status' => $session['payment_status'], 'amount_total' => $session['amount_total'], 'currency' => $session['currency']];
            } elseif ($order->payment_provider === 'local') {
                abort_unless(app()->environment(['local', 'testing']), 503, 'Local confirmation is disabled.');
                $providerPaymentId = 'local_'.Str::random(20);
                $payload = ['verified_by' => 'local-test-provider'];
            } else {
                abort(409, 'Manual payments must be approved by an administrator.');
            }

            $order = $payments->complete($order, $providerPaymentId, $payload);
        }

        return response()->json($this->serialize($order, $manualSettings->expiresMinutes()));
    }

    public function orders(Request $request, ManualPaymentSettingsService $manualSettings): JsonResponse
    {
        $expiresMinutes = $manualSettings->expiresMinutes();

        return response()->json(['data' => $request->user()->orders()->latest()->get()->map(fn (Order $order) => $this->serialize($order, $expiresMinutes))]);
    }

    private function serialize(Order $order, int $manualPaymentExpiresMinutes): array
    {
        $order->loadMissing(['invoice', 'beneficiary:id,name,email', 'course:id,title,title_en', 'bundle:id,name,name_en', 'subscriptionPlan:id,name_ar,name_en,billing_period', 'manualPaymentSubmissions' => fn ($query) => $query->latest()]);
        $submission = $order->manualPaymentSubmissions->first();
        $reviewEtaHours = (int) config('payments.manual_transfer.review_eta_hours', 24);

        return [
            'id' => $order->public_id,
            'productType' => $order->product_type,
            'productTitle' => $order->subscriptionPlan?->name_en ?? $order->bundle?->name ?? $order->course?->title,
            'productTitleAr' => $order->subscriptionPlan?->name_ar,
            'billingPeriod' => $order->subscriptionPlan?->billing_period,
            'courseId' => $order->course_id,
            'bundleId' => $order->course_bundle_id,
            'originalAmount' => (float) $order->original_amount,
            'discountAmount' => (float) $order->discount_amount,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'assignmentStatus' => $order->assignment_status,
            'assignedAt' => $order->assigned_at?->toISOString(),
            'beneficiary' => $order->beneficiary?->only(['id', 'name', 'email']),
            'isGiftPurchase' => $order->beneficiary_user_id !== $order->user_id,
            'paymentMethod' => $order->payment_method,
            'invoiceNumber' => $order->invoice?->number,
            'reviewNote' => $submission?->review_note,
            'submissionStatus' => $submission?->status,
            'submittedAt' => $submission?->created_at?->toISOString(),
            'reviewedAt' => $submission?->reviewed_at?->toISOString(),
            'estimatedReviewAt' => $submission && in_array($submission->status, ['submitted', 'under_review'], true) ? $submission->created_at->addHours($reviewEtaHours)->toISOString() : null,
            'resubmissionAllowed' => $order->status === 'needs_information',
            'supportUrl' => '/support?new=1&category=payment&order='.$order->public_id,
            'expiresAt' => $order->payment_provider === 'manual_transfer' ? $order->created_at->addMinutes($manualPaymentExpiresMinutes)->toISOString() : null,
            'date' => ($order->paid_at ?? $order->created_at)->toISOString(),
        ];
    }

    private function stripe(): PendingRequest
    {
        abort_unless(config('payments.stripe.secret'), 503, 'Stripe is not configured.');

        return Http::baseUrl(config('payments.stripe.api_url'))->withToken(config('payments.stripe.secret'))->acceptJson()->timeout(15);
    }
}
