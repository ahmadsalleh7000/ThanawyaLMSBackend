<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\NotificationDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Services\AuditService;
use App\Services\EmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $deliveries = NotificationDelivery::with(['user:id,name', 'template:id,name'])->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where(fn ($inner) => $inner->where('recipient', 'like', '%'.$search.'%')->orWhere('subject', 'like', '%'.$search.'%')))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.system.notifications', [
            'deliveries' => $deliveries, 'templates' => NotificationTemplate::orderBy('name')->get(),
            'summary' => ['queued' => NotificationDelivery::whereIn('status', ['queued', 'sending', 'retrying'])->count(), 'sent' => NotificationDelivery::where('status', 'sent')->where('created_at', '>=', now()->subDay())->count(), 'failed' => NotificationDelivery::where('status', 'failed')->count(), 'rate' => $this->successRate()],
        ]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['subject_ar' => ['required', 'string', 'max:255'], 'subject_en' => ['required', 'string', 'max:255'], 'body_ar' => ['required', 'string', 'max:10000'], 'body_en' => ['required', 'string', 'max:10000'], 'is_active' => ['sometimes', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $before = $template->attributesToArray();
        $template->update($data);
        $audit->record($request, 'notification_template.updated', $template, $before);

        return back()->with('success', 'تم تحديث قالب البريد.');
    }

    public function retry(Request $request, NotificationDelivery $delivery, EmailDeliveryService $emails, AuditService $audit): RedirectResponse
    {
        abort_unless(in_array($delivery->status, [NotificationDeliveryStatus::Failed, NotificationDeliveryStatus::Retrying], true), 409, 'Only failed deliveries can be retried.');
        abort_unless(($delivery->channel ?? 'email') === 'email', 409, 'Guardian invitations must be corrected or resent from the student account.');
        $before = $delivery->attributesToArray();
        $emails->retry($delivery);
        $audit->record($request, 'notification_delivery.retried', $delivery, $before);

        return back()->with('success', 'تمت إعادة الرسالة إلى قائمة الإرسال.');
    }

    private function successRate(): float
    {
        $completed = NotificationDelivery::whereIn('status', ['sent', 'failed'])->count();

        return $completed ? round(NotificationDelivery::where('status', 'sent')->count() / $completed * 100, 1) : 100;
    }
}
