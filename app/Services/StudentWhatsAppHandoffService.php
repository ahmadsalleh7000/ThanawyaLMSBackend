<?php

namespace App\Services;

use App\Models\User;

class StudentWhatsAppHandoffService
{
    public function __construct(private readonly ManualWhatsAppDeliveryService $deliveries) {}

    public function prepare(User $student): string
    {
        $recipient = preg_replace('/\D+/', '', (string) $student->phone_e164);
        abort_unless($recipient !== '', 422, 'لم يتم تسجيل رقم واتساب صالح للطالب.');

        $localPhone = preg_replace('/^\+20/', '0', (string) $student->phone_e164);
        $loginUrl = rtrim((string) config('app.frontend_url'), '/').'/login';
        $message = trans('whatsapp.student_approved', [
            'platform' => config('app.name'),
            'username' => $localPhone,
            'login_url' => $loginUrl,
        ], 'ar');

        $this->deliveries->recordOpened(
            $student,
            'student_registration_approved',
            $student->phone_e164,
            'تم قبول تسجيل الطالب',
            'تم فتح رسالة اعتماد التسجيل في WhatsApp Web. لا تحتوي الرسالة على كلمة مرور.',
            ['manual_handoff' => true],
        );

        return 'https://web.whatsapp.com/send?phone='.$recipient.'&text='.rawurlencode($message);
    }
}
