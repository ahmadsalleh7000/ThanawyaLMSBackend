<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\ManualPaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualPaymentReviewService
{
    public function __construct(private readonly OrderPaymentService $payments, private readonly EmailDeliveryService $emails) {}

    public function approve(ManualPaymentSubmission $submission, User $reviewer, ?string $note): ManualPaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $note) {
            $locked = ManualPaymentSubmission::lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['review' => 'This submission has already been reviewed.']);
            }
            if (! in_array($locked->order->status, ['submitted', 'needs_information'], true)) {
                throw ValidationException::withMessages(['review' => 'The related order is no longer awaiting payment review.']);
            }
            if (abs((float) $locked->submitted_amount - (float) $locked->order->amount) > 0.001) {
                throw ValidationException::withMessages(['review' => 'The submitted amount does not match the order.']);
            }
            if ($locked->order->product_type === 'subscription'
                && ! $locked->user->registrationStatus()->canTransitionTo(RegistrationStatus::Approved)) {
                throw ValidationException::withMessages(['review' => 'This registration can no longer be approved.']);
            }
            $locked->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->payments->complete($locked->order, $locked->transaction_id, ['verified_by' => $reviewer->id, 'submission_id' => $locked->id, 'channel' => $locked->channel]);

            return $locked->refresh();
        });
    }

    public function decide(ManualPaymentSubmission $submission, User $reviewer, string $status, string $note): ManualPaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $status, $note) {
            $locked = ManualPaymentSubmission::lockForUpdate()->findOrFail($submission->id);
            if (! in_array($locked->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['review' => 'This submission has already been reviewed.']);
            }
            $locked->update(['status' => $status, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $locked->order->update([
                'status' => $status === 'needs_information' ? 'needs_information' : 'rejected',
                'assignment_status' => $status === 'needs_information' ? 'pending' : 'not_assigned',
            ]);
            if ($locked->order->product_type === 'subscription' && $status === 'rejected') {
                $user = $locked->user;
                if (! $user->registrationStatus()->canTransitionTo(RegistrationStatus::Rejected)) {
                    throw ValidationException::withMessages(['review' => 'This registration can no longer be rejected.']);
                }
                $user->update([
                    'is_active' => false,
                    'registration_status' => RegistrationStatus::Rejected->value,
                    'registration_reviewed_at' => now(),
                    'registration_reviewed_by' => $reviewer->id,
                    'registration_rejection_reason' => $note,
                ]);
            }
            if ($locked->user->notificationPreference?->payment_updates ?? true) {
                $locked->user->userNotifications()->create([
                    'type' => 'payment',
                    'title' => $status === 'needs_information' ? 'More payment information required' : 'Payment evidence rejected',
                    'title_ar' => $status === 'needs_information' ? 'مطلوب استكمال بيانات الدفع' : 'تم رفض إثبات الدفع',
                    'message' => $note,
                    'message_ar' => $note,
                    'action_url' => '/payment-history',
                ]);
                $this->emails->queue($locked->user, $status === 'needs_information' ? 'payment_needs_information' : 'payment_rejected', ['order' => $locked->order->public_id, 'reason' => $note]);
            }

            return $locked->refresh();
        });
    }
}
