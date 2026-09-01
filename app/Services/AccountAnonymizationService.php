<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\ManualPaymentSubmission;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAnonymizationService
{
    public function process(AccountDeletionRequest $deletion): AccountDeletionRequest
    {
        $attachments = collect();
        $receipts = collect();
        $processed = DB::transaction(function () use ($deletion, &$attachments, &$receipts): AccountDeletionRequest {
            $request = AccountDeletionRequest::lockForUpdate()->with('user')->findOrFail($deletion->id);
            if ($request->status !== 'approved' || $request->scheduled_for->isFuture()) {
                throw ValidationException::withMessages(['deletion' => 'Only approved deletion requests that reached their scheduled date can be processed.']);
            }
            $user = $request->user;
            abort_if($user->isAdmin(), 403, 'Administrator accounts cannot be anonymized through self-service deletion.');
            $reference = $request->anonymization_reference ?: (string) Str::uuid();
            $phone = $user->phone_e164;

            $attachments = SupportTicketMessage::whereHas('ticket', fn ($query) => $query->where('user_id', $user->id))->whereNotNull('attachment_path')->get(['attachment_disk', 'attachment_path']);
            $receipts = ManualPaymentSubmission::where('user_id', $user->id)->whereNull('receipt_deleted_at')->get(['id', 'receipt_disk', 'receipt_path']);

            DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('parent_student_links')->where('parent_id', $user->id)->orWhere('student_id', $user->id)->delete();
            DB::table('student_link_codes')->where('student_id', $user->id)->delete();
            DB::table('guardian_invitations')
                ->where('student_id', $user->id)
                ->orWhere('parent_user_id', $user->id)
                ->when($phone, fn ($query) => $query->orWhere('parent_phone_e164', $phone))
                ->delete();
            DB::table('notification_deliveries')->where('user_id', $user->id)->delete();
            DB::table('contact_messages')->where('user_id', $user->id)->delete();
            $user->supportTickets()->delete();
            $user->courseReviews()->delete();
            $user->userNotifications()->delete();
            $user->notificationPreference()->delete();
            $user->wishlistItems()->delete();
            $user->assessmentDrafts()->delete();
            $user->certificates()->delete();
            $user->assessmentAttempts()->delete();
            $user->lessonWorkspaceStates()->delete();
            $user->lessonCompletions()->delete();
            $user->enrollments()->delete();
            $user->onboardingProfile()->delete();
            $user->supportCase()->delete();

            ManualPaymentSubmission::where('user_id', $user->id)->update(['sender_identifier' => '[redacted]', 'student_note' => null, 'receipt_original_name' => '[deleted]', 'receipt_path' => 'privacy-deleted/'.$reference, 'receipt_deleted_at' => now()]);
            $user->orders()->get(['id', 'public_id'])->each(fn ($order) => $order->update(['provider_payload' => null, 'session_token_hash' => hash('sha256', $order->public_id.$reference)]));
            $user->forceFill([
                'name' => 'Deleted user '.substr($reference, 0, 8),
                'email' => 'deleted+'.str_replace('-', '', $reference).'@privacy.invalid',
                'password' => Hash::make(Str::random(60)),
                'remember_token' => null,
                'email_verified_at' => null,
                'phone_e164' => null,
                'phone_verified_at' => null,
                'is_active' => false,
                'anonymized_at' => now(),
            ])->save();
            $request->update([
                'status' => 'completed',
                'processed_at' => now(),
                'legal_records_until' => now()->addYears((int) config('privacy.financial_record_retention_years')),
                'anonymization_reference' => $reference,
                'processing_error' => null,
                'policy_version' => config('privacy.policy_version'),
                'reason' => null,
            ]);

            return $request->refresh();
        });

        $attachments->each(fn ($file) => Storage::disk($file->attachment_disk)->delete($file->attachment_path));
        $receipts->each(fn ($file) => Storage::disk($file->receipt_disk)->delete($file->receipt_path));

        return $processed;
    }
}
