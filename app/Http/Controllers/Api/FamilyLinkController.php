<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\DecideExistingGuardianInvitationAction;
use App\Enums\GuardianInvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use App\Models\StudentLinkCode;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FamilyLinkController extends Controller
{
    public function createCode(Request $request): JsonResponse
    {
        StudentLinkCode::where('student_id', $request->user()->id)->whereNull('used_at')->update(['expires_at' => now()]);
        do {
            $code = collect(range(1, 8))->map(fn () => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)])->join('');
            $hash = hash('sha256', $code);
        } while (StudentLinkCode::where('code_hash', $hash)->exists());
        $invitation = StudentLinkCode::create(['student_id' => $request->user()->id, 'code_hash' => $hash, 'expires_at' => now()->addMinutes(15)]);

        return response()->json(['data' => ['code' => substr($code, 0, 4).'-'.substr($code, 4), 'expiresAt' => $invitation->expires_at->toISOString()]], 201);
    }

    public function studentRequests(Request $request): JsonResponse
    {
        return response()->json(['data' => ParentStudentLink::where('student_id', $request->user()->id)->with('parent:id,name,email')->latest()->get()->map(fn ($link) => $this->linkData($link, 'parent'))]);
    }

    public function parentRequests(Request $request): JsonResponse
    {
        return response()->json(['data' => ParentStudentLink::where('parent_id', $request->user()->id)->with('student:id,name,email')->latest()->get()->map(fn ($link) => $this->linkData($link, 'student'))]);
    }

    public function guardianInvitations(Request $request): JsonResponse
    {
        $forParent = fn ($query) => $query
            ->where('parent_user_id', $request->user()->id)
            ->orWhere('parent_phone_e164', $request->user()->phone_e164);
        GuardianInvitation::query()
            ->where($forParent)
            ->where('status', GuardianInvitationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => GuardianInvitationStatus::Expired]);

        $invitations = GuardianInvitation::query()
            ->where($forParent)
            ->whereIn('status', [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Expired])
            ->with('student:id,name')
            ->latest()
            ->get()
            ->map(fn (GuardianInvitation $invitation): array => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
                'relationshipType' => $invitation->relationship_type->value,
                'expiresAt' => $invitation->expires_at->toISOString(),
                'student' => $invitation->student?->only(['id', 'name']),
            ]);

        return response()->json(['data' => $invitations]);
    }

    public function decideGuardianInvitation(
        Request $request,
        GuardianInvitation $invitation,
        string $decision,
        DecideExistingGuardianInvitationAction $decide,
    ): JsonResponse {
        abort_unless(in_array($decision, ['accept', 'reject'], true), 404);
        Gate::forUser($request->user())->authorize('respond', $invitation);
        $updated = $decide->execute($request->user(), $invitation, $decision);

        return response()->json(['data' => [
            'id' => $updated->id,
            'status' => $updated->status->value,
        ]]);
    }

    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $data['code']));
        if (strlen($normalized) !== 8) {
            throw ValidationException::withMessages(['code' => ['The linking code is invalid or expired.']]);
        }
        $link = DB::transaction(function () use ($request, $normalized) {
            $invitation = StudentLinkCode::where('code_hash', hash('sha256', $normalized))->lockForUpdate()->first();
            if (! $invitation || $invitation->used_at || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => ['The linking code is invalid or expired.']]);
            }
            $student = User::whereKey($invitation->student_id)->where('role', User::ROLE_STUDENT)->where('is_active', true)->firstOrFail();
            $existing = ParentStudentLink::where(['parent_id' => $request->user()->id, 'student_id' => $student->id])->first();
            abort_if($existing?->status === 'accepted', 409, 'This student is already linked.');
            $link = ParentStudentLink::updateOrCreate(
                ['parent_id' => $request->user()->id, 'student_id' => $student->id],
                ['status' => 'pending', 'linked_at' => now(), 'accepted_at' => null, 'rejected_at' => null],
            );
            $invitation->update(['used_at' => now()]);
            UserNotification::create(['user_id' => $student->id, 'type' => 'family_link', 'title' => 'Parent link request', 'title_ar' => 'طلب ربط ولي أمر', 'message' => $request->user()->name.' requested access to your learning progress.', 'message_ar' => 'طلب '.$request->user()->name.' متابعة تقدمك التعليمي.', 'action_url' => '/dashboard']);

            return $link->load('student:id,name,email');
        });

        return response()->json(['message' => 'Link request submitted for student approval.', 'data' => $this->linkData($link, 'student')], 201);
    }

    public function decide(Request $request, ParentStudentLink $link, string $decision): JsonResponse
    {
        abort_unless(in_array($decision, ['accept', 'reject'], true), 404);
        Gate::forUser($request->user())->authorize('respond', $link);
        $accepted = $decision === 'accept';
        $link->update(['status' => $accepted ? 'accepted' : 'rejected', 'accepted_at' => $accepted ? now() : null, 'verified_at' => $accepted ? now() : null, 'rejected_at' => $accepted ? null : now()]);
        UserNotification::create(['user_id' => $link->parent_id, 'type' => 'family_link', 'title' => $accepted ? 'Student linked' : 'Link request declined', 'title_ar' => $accepted ? 'تم ربط الطالب' : 'تم رفض طلب الربط', 'message' => $accepted ? 'You can now follow the student learning report.' : 'The student declined the linking request.', 'message_ar' => $accepted ? 'يمكنك الآن متابعة تقرير تعلم الطالب.' : 'رفض الطالب طلب الربط.', 'action_url' => '/parent-dashboard']);

        return response()->json(['data' => $this->linkData($link->load('parent:id,name,email'), 'parent')]);
    }

    public function unlink(Request $request, ParentStudentLink $link): JsonResponse
    {
        Gate::forUser($request->user())->authorize('revoke', $link);
        $link->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
        GuardianInvitation::query()
            ->where('parent_user_id', $link->parent_id)
            ->where('student_id', $link->student_id)
            ->where('status', GuardianInvitationStatus::Verified)
            ->update(['status' => GuardianInvitationStatus::Revoked]);

        return response()->json(['message' => 'Family link revoked.']);
    }

    private function linkData(ParentStudentLink $link, string $person): array
    {
        return ['id' => $link->id, 'status' => $link->status, 'createdAt' => $link->created_at?->toISOString(), $person => $link->{$person}?->only(['id', 'name', 'email'])];
    }
}
