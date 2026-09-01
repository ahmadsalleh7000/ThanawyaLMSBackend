<?php

namespace App\Services;

use App\Enums\GuardianInvitationStatus;
use App\Models\GuardianInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardianWhatsAppHandoffService
{
    public function __construct(private readonly ManualWhatsAppDeliveryService $deliveries) {}

    public function prepare(GuardianInvitation $invitation): string
    {
        if (! in_array($invitation->status, [GuardianInvitationStatus::Pending, GuardianInvitationStatus::Verified], true)) {
            throw ValidationException::withMessages(['invitation' => 'دعوة ولي الأمر لم تعد متاحة للإرسال.']);
        }
        $parent = $invitation->parent;
        abort_unless($parent, 422, 'لم يتم تجهيز حساب ولي الأمر بعد.');
        $temporaryPassword = null;
        if ($invitation->guardian_account_was_created && $parent->must_change_password) {
            $temporaryPassword = Str::password(14, symbols: false);
            $parent->update(['password' => $temporaryPassword, 'temporary_password_expires_at' => now()->addHours((int) config('guardian.temporary_password_expires_hours', 48))]);
        }
        $invitation->update(['last_sent_at' => now()]);

        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/login';
        $studentName = $invitation->student?->name ?? 'الطالب';
        $localPhone = preg_replace('/^\+20/', '0', $invitation->parent_phone_e164);
        $message = $temporaryPassword
            ? trans('whatsapp.guardian_new', ['student' => $studentName, 'username' => $localPhone, 'password' => $temporaryPassword, 'login_url' => $loginUrl], 'ar')
            : trans('whatsapp.guardian_existing', ['platform' => config('app.name'), 'student' => $studentName, 'login_url' => $loginUrl], 'ar');

        $this->deliveries->recordOpened(
            $parent,
            $temporaryPassword ? 'guardian_temporary_credentials' : 'guardian_student_linked',
            $invitation->parent_phone_e164,
            'بيانات دخول حساب ولي الأمر',
            'تم فتح رسالة WhatsApp يدوياً. لا تُحفظ بيانات الدخول المؤقتة.',
            ['invitation_id' => $invitation->id, 'manual_handoff' => true, 'temporary_credential_reissued' => (bool) $temporaryPassword],
            $invitation,
        );

        $recipient = preg_replace('/\D+/', '', $invitation->parent_phone_e164);

        return 'https://web.whatsapp.com/send?phone='.$recipient.'&text='.rawurlencode($message);
    }
}
