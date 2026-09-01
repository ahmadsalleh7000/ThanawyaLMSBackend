<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentSubmission;
use App\Models\User;
use App\Services\ManualPaymentSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ManualPaymentController extends Controller
{
    public function submit(Request $request, ManualPaymentSettingsService $settings): JsonResponse
    {
        $channels = array_keys(array_filter($settings->channels(), fn ($channel) => filled($channel['destination'])));
        $data = $request->validate([
            'session_id' => ['nullable', 'required_without:order_id', 'string', 'size:64'],
            'order_id' => ['nullable', 'required_without:session_id', 'uuid'],
            'channel' => ['required', Rule::in($channels)],
            'transaction_id' => ['required', 'string', 'min:4', 'max:150', 'unique:manual_payment_submissions,transaction_id'],
            'sender_identifier' => ['required', 'string', 'min:4', 'max:100'],
            'transferred_at' => ['required', 'date', 'before_or_equal:now', 'after:'.now()->subDays(7)->toDateTimeString()],
            'submitted_amount' => ['required', 'numeric', 'gt:0'],
            'receipt' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:5120'],
            'student_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'transaction_id.unique' => 'This transaction ID was already submitted. Check the reference or contact support.',
            'receipt.required' => 'Please attach the payment receipt before submitting.',
            'receipt.max' => 'The receipt must not exceed 5 MB.',
        ]);
        $order = $request->user()->orders()
            ->when($data['session_id'] ?? null, fn ($query, $token) => $query->where('session_token_hash', hash('sha256', $token)))
            ->when($data['order_id'] ?? null, fn ($query, $publicId) => $query->where('public_id', $publicId))
            ->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $order);
        abort_unless($order->payment_provider === 'manual_transfer', 422, 'This order does not accept manual evidence.');
        abort_unless(in_array($order->status, ['pending', 'needs_information'], true), 409, 'This order cannot accept another submission.');
        abort_if($order->status === 'pending' && $order->created_at->addMinutes($settings->expiresMinutes())->isPast(), 410, 'This payment order has expired.');
        abort_if(abs((float) $data['submitted_amount'] - (float) $order->amount) > 0.001, 422, 'The submitted amount must match the order total.');

        $file = $data['receipt'];
        $mime = (string) $file->getMimeType();
        $path = $file->store('payment-receipts/'.now()->format('Y/m'), 'local');
        $submission = ManualPaymentSubmission::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'channel' => $data['channel'],
            'transaction_id' => trim($data['transaction_id']),
            'sender_identifier' => trim($data['sender_identifier']),
            'transferred_at' => $data['transferred_at'],
            'submitted_amount' => $data['submitted_amount'],
            'receipt_disk' => 'local',
            'receipt_path' => $path,
            'receipt_original_name' => $file->getClientOriginalName(),
            'receipt_mime' => $mime,
            'receipt_size' => $file->getSize(),
            'receipt_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'student_note' => $data['student_note'] ?? null,
            'status' => 'submitted',
        ]);
        $order->update(['status' => 'submitted']);
        User::where('role', User::ROLE_ADMIN)->where(function ($query) {
            $query->whereNull('admin_role')->orWhere('admin_role', 'finance_manager');
        })->each(fn ($admin) => $admin->userNotifications()->create([
            'type' => 'payment_review', 'title' => 'Payment evidence awaiting review', 'title_ar' => 'إثبات دفع بانتظار المراجعة',
            'message' => 'A customer submitted a manual transfer receipt.', 'message_ar' => 'أرسل مستخدم إيصال تحويل يدوي جديد.',
            'action_url' => '/admin/payment-reviews/'.$submission->id,
        ]));

        return response()->json([
            'id' => $submission->id,
            'orderId' => $order->public_id,
            'status' => 'submitted',
            'submittedAt' => $submission->created_at->toISOString(),
            'message' => 'Payment evidence is awaiting admin verification.',
        ], 201);
    }
}
