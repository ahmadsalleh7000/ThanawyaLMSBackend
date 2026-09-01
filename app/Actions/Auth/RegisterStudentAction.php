<?php

namespace App\Actions\Auth;

use App\Data\StudentRegistrationResult;
use App\Enums\GuardianInvitationStatus;
use App\Enums\RegistrationStatus;
use App\Models\GuardianInvitation;
use App\Models\ManualPaymentSubmission;
use App\Models\Order;
use App\Models\StudentOnboardingProfile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CommercePricingService;
use App\Services\OrderPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterStudentAction
{
    public function __construct(private readonly CommercePricingService $pricing, private readonly OrderPaymentService $payments) {}

    public function execute(array $data): StudentRegistrationResult
    {
        $receipt = $data['receipt'] ?? null;
        $receiptPath = null;
        if ($receipt) {
            $receiptPath = $receipt->store('payment-receipts/'.now()->format('Y/m'), 'local');
        }

        try {
            $result = DB::transaction(function () use ($data, $receipt, $receiptPath): StudentRegistrationResult {
                $plan = SubscriptionPlan::whereKey($data['subscription_plan_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
                $quote = $this->pricing->quoteSubscriptionPlan($plan, $data['promo_code'] ?? null);
                $requiresPayment = (float) $quote['total'] > 0.001;
                $phoneOwner = User::where('phone_e164', $data['parent_phone'])->lockForUpdate()->first();
                if ($phoneOwner && $phoneOwner->role !== User::ROLE_PARENT) {
                    throw ValidationException::withMessages([
                        'parent_phone' => ['This number cannot be used as a parent or guardian number.'],
                    ]);
                }

                $student = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone_e164' => $data['student_phone'],
                    'password' => $data['password'],
                    'role' => User::ROLE_STUDENT,
                    'is_active' => false,
                    'registration_status' => RegistrationStatus::PendingPaymentReview->value,
                ]);
                StudentOnboardingProfile::create([
                    'student_id' => $student->id,
                    'grade' => $data['grade'],
                    'semester' => $data['semester'],
                ]);

                $plainTextToken = Str::random(64);
                $invitation = GuardianInvitation::create([
                    'student_id' => $student->id,
                    'parent_user_id' => $phoneOwner?->id,
                    'parent_phone_e164' => $data['parent_phone'],
                    'relationship_type' => $data['relationship_type'],
                    'relationship_details' => $data['relationship_details'] ?? null,
                    'token_hash' => hash('sha256', $plainTextToken),
                    'status' => GuardianInvitationStatus::Pending,
                    'expires_at' => now()->addHours((int) config('guardian.invitation_expires_hours', 48)),
                ]);

                $order = Order::create([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $student->id,
                    'beneficiary_user_id' => $student->id,
                    'course_id' => null,
                    'product_type' => 'subscription',
                    'subscription_plan_id' => $plan->id,
                    'coupon_id' => $quote['coupon']?->id,
                    'original_amount' => $quote['subtotal'],
                    'discount_amount' => $quote['discount'],
                    'amount' => $quote['total'],
                    'currency' => $plan->currency,
                    'status' => 'submitted',
                    'assignment_status' => 'pending',
                    'payment_provider' => $requiresPayment ? 'manual_transfer' : 'promo_code',
                    'payment_method' => $requiresPayment ? $data['payment_channel'] : 'promo_code',
                    'session_token_hash' => hash('sha256', Str::random(64)),
                ]);

                $submission = null;
                if ($requiresPayment) {
                    $submission = ManualPaymentSubmission::create([
                        'order_id' => $order->id,
                        'user_id' => $student->id,
                        'channel' => $data['payment_channel'],
                        'transaction_id' => trim($data['transaction_id']),
                        'sender_identifier' => trim($data['sender_identifier']),
                        'transferred_at' => $data['transferred_at'],
                        'submitted_amount' => $quote['total'],
                        'receipt_disk' => 'local',
                        'receipt_path' => $receiptPath,
                        'receipt_original_name' => $receipt->getClientOriginalName(),
                        'receipt_mime' => (string) $receipt->getMimeType(),
                        'receipt_size' => $receipt->getSize(),
                        'receipt_sha256' => hash_file('sha256', Storage::disk('local')->path($receiptPath)),
                        'status' => 'submitted',
                    ]);
                }
                if ($quote['coupon']) {
                    $this->pricing->reserve($quote['coupon'], $student, $order, $quote['discount']);
                }

                if ($submission) {
                    User::where('role', User::ROLE_ADMIN)->where(function ($query): void {
                        $query->whereNull('admin_role')->orWhere('admin_role', 'finance_manager');
                    })->each(fn (User $admin) => $admin->userNotifications()->create([
                        'type' => 'student_registration',
                        'title' => 'New student registration awaiting review',
                        'title_ar' => 'تسجيل طالب جديد بانتظار المراجعة',
                        'message' => $student->name.' submitted registration and payment evidence.',
                        'message_ar' => 'أرسل '.$student->name.' طلب التسجيل وإثبات الدفع.',
                        'action_url' => '/admin/payment-reviews/'.$submission->id,
                    ]));
                }

                return new StudentRegistrationResult($student, $invitation, $plainTextToken, $submission, $order);
            }, 3);

            if (! $result->paymentSubmission) {
                $completedOrder = $this->payments->complete($result->order, 'full-discount-promo', ['source' => 'registration_promo_code']);
                User::where('role', User::ROLE_ADMIN)->where(function ($query): void {
                    $query->whereNull('admin_role')->orWhere('admin_role', 'finance_manager');
                })->each(fn (User $admin) => $admin->userNotifications()->create([
                    'type' => 'student_registration',
                    'title' => 'Student auto-approved by promo code',
                    'title_ar' => 'تم تفعيل طالب تلقائياً بكود خصم',
                    'message' => $result->student->name.' was activated with a full-discount promo code.',
                    'message_ar' => 'تم تفعيل '.$result->student->name.' تلقائياً باستخدام كود خصم كامل.',
                    'action_url' => '/admin/users/'.$result->student->id,
                    'data' => [
                        'order_id' => $completedOrder->public_id,
                        'student_id' => $result->student->id,
                        'coupon_id' => $completedOrder->coupon_id,
                        'discount_amount' => (float) $completedOrder->discount_amount,
                    ],
                ]));
            }
        } catch (\Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
            throw $exception;
        }

        return $result;
    }
}
