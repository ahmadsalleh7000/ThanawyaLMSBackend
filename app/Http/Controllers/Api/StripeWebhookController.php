<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, OrderPaymentService $payments): JsonResponse
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, $request->header('Stripe-Signature', ''));
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $order = Order::where('provider_session_id', $session['id'] ?? '')->firstOrFail();
            abort_unless(
                ($session['payment_status'] ?? null) === 'paid'
                && ($session['metadata']['order_id'] ?? null) === $order->public_id
                && ($session['metadata']['user_id'] ?? null) === (string) $order->user_id
                && (! isset($session['metadata']['beneficiary_user_id']) || $session['metadata']['beneficiary_user_id'] === (string) $order->beneficiary_user_id)
                && ($session['amount_total'] ?? -1) === (int) round((float) $order->amount * 100)
                && strtoupper($session['currency'] ?? '') === $order->currency,
                409,
                'Payment data does not match the order.',
            );
            $payments->complete($order, $session['payment_intent'] ?? null, ['stripe_event_id' => $event['id'] ?? null]);
        }

        return response()->json(['received' => true]);
    }

    private function verifySignature(string $payload, string $header): void
    {
        $secret = config('payments.stripe.webhook_secret');
        abort_unless($secret, 503, 'Stripe webhook is not configured.');
        parse_str(str_replace(',', '&', $header), $parts);
        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        $signatures = array_filter((array) ($parts['v1'] ?? []));
        abort_if(abs(time() - $timestamp) > 300, 400, 'Expired Stripe signature.');
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        abort_unless(collect($signatures)->contains(fn ($signature) => hash_equals($expected, $signature)), 400, 'Invalid Stripe signature.');
    }
}
