<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Order;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function __construct(private readonly EnrollmentLifecycleService $enrollments, private readonly InvoiceService $invoices, private readonly EmailDeliveryService $emails, private readonly GuardianAccountProvisioningService $guardians, private readonly StudentSubscriptionService $subscriptions) {}

    public function complete(Order $order, ?string $providerPaymentId, array $payload): Order
    {
        return DB::transaction(function () use ($order, $providerPaymentId, $payload) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'completed') {
                $locked->update(['status' => 'completed', 'paid_at' => now(), 'provider_payment_id' => $providerPaymentId, 'provider_payload' => $payload]);
                $locked->loadMissing(['beneficiary', 'course', 'bundle.courses', 'subscriptionPlan']);
                $beneficiary = $locked->beneficiary ?? $locked->user;
                if ($locked->product_type === 'subscription') {
                    $beneficiary->update([
                        'is_active' => true,
                        'registration_status' => RegistrationStatus::Approved->value,
                        'registration_reviewed_at' => now(),
                        'registration_reviewed_by' => $payload['verified_by'] ?? null,
                        'registration_rejection_reason' => null,
                    ]);
                    event(new Registered($beneficiary));
                    $this->guardians->provision($beneficiary);
                    $this->subscriptions->activateFor($locked);
                }
                foreach ($locked->purchasedCourses() as $course) {
                    $this->enrollments->enroll($beneficiary, $course, 'payment');
                }
                $locked->update(['assignment_status' => 'assigned', 'assigned_at' => now()]);
                $locked->redemption?->update(['status' => 'redeemed', 'redeemed_at' => now()]);
                $this->invoices->issue($locked);
                if ($locked->user->notificationPreference?->payment_updates ?? true) {
                    $locked->user->userNotifications()->create([
                        'type' => 'payment', 'title' => 'Enrollment confirmed', 'title_ar' => 'تم تأكيد التسجيل',
                        'message' => $locked->product_type === 'subscription' ? 'Your registration and payment were approved. You can now sign in.' : 'Your payment was verified and the course is ready.',
                        'message_ar' => $locked->product_type === 'subscription' ? 'تم اعتماد التسجيل والدفع. يمكنك تسجيل الدخول الآن.' : 'تم التحقق من الدفع وأصبحت الدورة جاهزة.',
                        'action_url' => $locked->product_type === 'subscription' ? '/login' : ($locked->product_type === 'course' ? '/course-player/'.$locked->course_id : '/my-learning'),
                    ]);
                    $this->emails->queue($locked->user, 'payment_approved', ['order' => $locked->public_id]);
                }
                if ($beneficiary->isNot($locked->user)) {
                    $beneficiary->userNotifications()->create([
                        'type' => 'payment', 'title' => 'A course was assigned to you', 'title_ar' => 'تم إسناد دورة إليك',
                        'message' => 'Your parent purchased a course for you. It is ready to start.', 'message_ar' => 'اشترى ولي أمرك دورة لك وأصبحت جاهزة للبدء.',
                        'action_url' => $locked->product_type === 'course' ? '/course-player/'.$locked->course_id : '/my-learning',
                    ]);
                }
            }

            return $locked->refresh();
        });
    }
}
