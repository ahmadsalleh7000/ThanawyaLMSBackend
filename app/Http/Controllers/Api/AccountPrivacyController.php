<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountPrivacyController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403, 'Administrator accounts are managed through platform governance.');
        $payload = [
            'exported_at' => now()->toISOString(),
            'profile' => $user->only(['id', 'name', 'email', 'phone_e164', 'role', 'email_verified_at', 'phone_verified_at', 'created_at']),
            'guardian_invitations' => DB::table('guardian_invitations')
                ->where('student_id', $user->id)
                ->orWhere('parent_user_id', $user->id)
                ->get(['student_id', 'parent_user_id', 'parent_phone_e164', 'relationship_type', 'status', 'expires_at', 'accepted_at', 'rejected_at', 'created_at']),
            'guardian_relationships' => DB::table('parent_student_links')
                ->where('student_id', $user->id)
                ->orWhere('parent_id', $user->id)
                ->get(['parent_id', 'student_id', 'relationship_type', 'status', 'invited_at', 'verified_at', 'rejected_at', 'revoked_at', 'created_at']),
            'enrollments' => DB::table('enrollments')->where('user_id', $user->id)->get(['course_id', 'enrolled_at', 'created_at']),
            'lesson_completions' => DB::table('lesson_completions')->where('user_id', $user->id)->get(['lesson_id', 'completed_at']),
            'assessment_results' => DB::table('assessment_attempts')->where('user_id', $user->id)->get(['assessment_id', 'score', 'passed', 'submitted_at']),
            'certificates' => DB::table('certificates')->where('user_id', $user->id)->get(['public_id', 'course_id', 'issued_at', 'revoked_at']),
            'orders' => DB::table('orders')->where('user_id', $user->id)->get(['public_id', 'course_id', 'amount', 'currency', 'status', 'payment_method', 'paid_at', 'created_at']),
            'reviews' => DB::table('course_reviews')->where('user_id', $user->id)->get(['course_id', 'rating', 'comment', 'status', 'created_at']),
            'lesson_comments' => DB::table('lesson_comments')->where('user_id', $user->id)->whereNull('deleted_at')->get(['lesson_id', 'parent_id', 'content', 'edited_at', 'created_at']),
            'onboarding' => $user->onboardingProfile?->only(['grade', 'semester', 'learning_goal', 'experience_level', 'weekly_goal_minutes', 'leaderboard_opt_in', 'placement_answers', 'placement_score', 'recommended_level', 'recommended_course_id', 'completed_at', 'skipped_at']),
            'notification_preferences' => $user->notificationPreference?->only(['payment_updates', 'course_updates', 'learning_reminders', 'parent_progress_summaries', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end', 'reminder_time', 'weekly_summary_day', 'timezone']),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response()->streamDownload(fn () => print $json, 'ahmed-lms-data-'.$user->id.'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function deletionStatus(Request $request): JsonResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Administrator accounts are managed through platform governance.');

        $deletion = AccountDeletionRequest::where('user_id', $request->user()->id)->latest()->first();

        return response()->json(['data' => $deletion ? $this->serializeDeletion($deletion) : null, 'retention' => $this->retention()]);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Administrator accounts are managed through platform governance.');
        $data = $request->validate(['current_password' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:1000']]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }
        $existing = AccountDeletionRequest::where('user_id', $request->user()->id)->whereIn('status', ['pending', 'approved'])->first();
        if ($existing) {
            return response()->json(['message' => 'A deletion request is already pending.', 'data' => $existing], 409);
        }
        $deletion = AccountDeletionRequest::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'scheduled_for' => now()->addDays((int) config('privacy.deletion_cooling_off_days')),
            'policy_version' => config('privacy.policy_version'),
        ]);

        return response()->json(['message' => 'Deletion request received.', 'data' => $this->serializeDeletion($deletion), 'retention' => $this->retention()], 201);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Administrator accounts are managed through platform governance.');
        $deletion = AccountDeletionRequest::where('user_id', $request->user()->id)->whereIn('status', ['pending', 'approved'])->where('scheduled_for', '>', now())->latest()->firstOrFail();
        $deletion->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['message' => 'Deletion request cancelled.', 'data' => $this->serializeDeletion($deletion)]);
    }

    private function serializeDeletion(AccountDeletionRequest $deletion): array
    {
        return ['id' => $deletion->id, 'status' => $deletion->status, 'requestedAt' => $deletion->created_at->toISOString(), 'scheduledFor' => $deletion->scheduled_for->toISOString(), 'reviewedAt' => $deletion->reviewed_at?->toISOString(), 'reviewNote' => $deletion->review_note, 'processedAt' => $deletion->processed_at?->toISOString(), 'legalRecordsUntil' => $deletion->legal_records_until?->toISOString(), 'legalRecordsPrunedAt' => $deletion->legal_records_pruned_at?->toISOString(), 'canCancel' => in_array($deletion->status, ['pending', 'approved'], true) && $deletion->scheduled_for->isFuture(), 'retention' => $this->retention()];
    }

    private function retention(): array
    {
        return ['coolingOffDays' => (int) config('privacy.deletion_cooling_off_days'), 'financialRecordYears' => (int) config('privacy.financial_record_retention_years'), 'explanation' => 'Learning, profile, communication, and authentication data are erased or anonymized. Minimal invoices, orders, transaction references, and audit evidence remain access-controlled until their retention period ends.'];
    }
}
