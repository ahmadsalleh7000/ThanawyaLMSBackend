<?php

namespace App\Services;

use App\Enums\GuardianInvitationStatus;
use App\Enums\GuardianRelationshipStatus;
use App\Models\GuardianInvitation;
use App\Models\ParentStudentLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardianAccountProvisioningService
{
    public function provision(User $student): void
    {
        $invitation = $student->guardianInvitations()->where('status', GuardianInvitationStatus::Pending)->latest()->first();
        if (! $invitation) {
            return;
        }

        DB::transaction(function () use ($student, $invitation): void {
            $locked = GuardianInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== GuardianInvitationStatus::Pending) {
                return;
            }

            $digits = preg_replace('/\D+/', '', $locked->parent_phone_e164);
            $guardianEmail = "guardian-{$digits}@guardian.ahmed-lms.local";
            $matches = User::query()
                ->where('phone_e164', $locked->parent_phone_e164)
                ->orWhere('email', $guardianEmail)
                ->lockForUpdate()
                ->get();

            if ($matches->count() > 1 || ($matches->first() && $matches->first()->role !== User::ROLE_PARENT)) {
                throw ValidationException::withMessages([
                    'review' => 'تعذر تجهيز حساب ولي الأمر بسبب تعارض في بيانات الحساب. راجع رقم ولي الأمر من صفحة المستخدمين.',
                ]);
            }

            $parent = $matches->first();
            if (! $parent) {
                $parent = User::firstOrCreate(['email' => $guardianEmail], [
                    'name' => 'ولي أمر '.$student->name,
                    'phone_e164' => $locked->parent_phone_e164,
                    'phone_verified_at' => now(),
                    // This random value is never disclosed. A temporary credential is
                    // generated only when an admin explicitly opens the WhatsApp handoff.
                    'password' => Str::random(64),
                    'must_change_password' => true,
                    'temporary_password_expires_at' => null,
                    'role' => User::ROLE_PARENT,
                    'is_active' => true,
                ]);
            }

            if ($parent->role !== User::ROLE_PARENT) {
                throw ValidationException::withMessages([
                    'review' => 'تعذر تجهيز حساب ولي الأمر بسبب تعارض في نوع الحساب.',
                ]);
            }

            if ($parent->phone_e164 !== $locked->parent_phone_e164) {
                $phoneIsOwned = User::where('phone_e164', $locked->parent_phone_e164)->whereKeyNot($parent->id)->exists();
                if ($phoneIsOwned) {
                    throw ValidationException::withMessages([
                        'review' => 'رقم ولي الأمر مرتبط بحساب آخر. راجع بيانات الحساب قبل الاعتماد.',
                    ]);
                }
                $parent->update([
                    'phone_e164' => $locked->parent_phone_e164,
                    'phone_verified_at' => $parent->phone_verified_at ?? now(),
                ]);
            }

            $grade = $student->onboardingProfile?->grade;
            ParentStudentLink::updateOrCreate(
                ['parent_id' => $parent->id, 'student_id' => $student->id],
                ['relationship_type' => $locked->relationship_type, 'relationship_details' => $locked->relationship_details,
                    'grade' => $grade, 'status' => GuardianRelationshipStatus::Accepted, 'linked_at' => now(),
                    'invited_at' => $locked->created_at, 'accepted_at' => now(), 'verified_at' => now(),
                    'rejected_at' => null, 'revoked_at' => null],
            );
            $locked->update([
                'parent_user_id' => $parent->id,
                'status' => GuardianInvitationStatus::Verified,
                'accepted_at' => now(),
                'guardian_account_was_created' => $parent->wasRecentlyCreated
                    || ($parent->must_change_password && str_ends_with($parent->email, '@guardian.ahmed-lms.local')),
            ]);
        }, 3);
    }
}
