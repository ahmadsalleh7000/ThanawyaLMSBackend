<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ManualPaymentSubmission;
use App\Models\NotificationDelivery;
use App\Services\AuditService;
use App\Services\GuardianWhatsAppHandoffService;
use App\Services\ManualPaymentReviewService;
use App\Services\ManualWhatsAppDeliveryService;
use App\Services\StudentWhatsAppHandoffService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminManualPaymentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ManualPaymentSubmission::class);
        $submissions = $this->filtered($request)->with(['order.course', 'order.bundle', 'order.subscriptionPlan', 'order.beneficiary:id,name,email', 'user:id,name,email', 'reviewer:id,name'])
            ->withCount('matchingReceipts')->latest()->paginate(20)->withQueryString();
        $submissions->getCollection()->each(function (ManualPaymentSubmission $submission): void {
            if ($submission->order->product_type === 'subscription' && $submission->order->subscriptionPlan) {
                $submission->order->setRelation('course', new Course(['title' => $submission->order->subscriptionPlan->name_ar]));
            }
        });

        return view('admin.payment-reviews.index', [
            'submissions' => $submissions,
            'counts' => ManualPaymentSubmission::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(ManualPaymentSubmission $submission): View
    {
        Gate::authorize('view', $submission);
        $submission->load(['order.course', 'order.bundle.courses', 'order.subscriptionPlan', 'order.beneficiary', 'user', 'reviewer', 'matchingReceipts.user'])->loadCount('matchingReceipts');
        $studentWhatsApp = $submission->user->notificationDeliveries()->with('sentBy:id,name')->where('template_key', 'student_registration_approved')->latest()->first();
        $invitation = $submission->user->guardianInvitations()->with(['parent:id,name,phone_e164,must_change_password'])->latest()->first();
        $guardianWhatsApp = $invitation?->deliveries()->with('sentBy:id,name')->whereIn('template_key', ['guardian_temporary_credentials', 'guardian_student_linked'])->latest()->first();

        return view('admin.payment-reviews.show', compact('submission', 'invitation', 'studentWhatsApp', 'guardianWhatsApp'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasAdminPermission('finance.export'), 403);

        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Submitted at', 'Student', 'Email', 'Order', 'Channel', 'Transaction ID', 'Sender', 'Expected amount', 'Submitted amount', 'Status', 'Reviewer', 'Reviewed at']);
            $this->filtered($request)->with(['order', 'user:id,name,email', 'reviewer:id,name'])->latest()->chunk(500, function ($items) use ($output): void {
                foreach ($items as $item) {
                    fputcsv($output, [$item->created_at->toISOString(), $item->user->name, $item->user->email, $item->order->public_id, $item->channel, $item->transaction_id, $item->sender_identifier, $item->order->amount, $item->submitted_amount, $item->status, $item->reviewer?->name, $item->reviewed_at?->toISOString()]);
                }
            });
            fclose($output);
        }, 'payment-reviews-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function receipt(ManualPaymentSubmission $submission): StreamedResponse
    {
        Gate::authorize('view', $submission);
        abort_if($submission->receipt_deleted_at, 410, 'This receipt was deleted under the retention policy.');
        abort_unless(Storage::disk($submission->receipt_disk)->exists($submission->receipt_path), 404);

        return Storage::disk($submission->receipt_disk)->response($submission->receipt_path, $submission->receipt_original_name, [
            'Content-Type' => $submission->receipt_mime,
            'Content-Disposition' => 'inline; filename="receipt"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(Request $request, ManualPaymentSubmission $submission, ManualPaymentReviewService $reviews, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'needs_information'])],
            'review_note' => [Rule::requiredIf($request->input('decision') !== 'approved'), 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
        Gate::authorize($data['decision'] === 'approved' ? 'approve' : 'review', $submission);
        if ($data['decision'] === 'approved' && $submission->matchingReceipts()->whereKeyNot($submission->id)->exists()) {
            Gate::authorize('overrideDuplicate', $submission);
            validator($data, ['review_note' => ['required', 'string', 'min:10', 'max:2000']], [
                'review_note.required' => 'يجب توثيق سبب اعتماد إيصال مكرر.',
            ])->validate();
        }
        $before = $submission->attributesToArray();
        $result = $data['decision'] === 'approved'
            ? $reviews->approve($submission, $request->user(), $data['review_note'] ?? null)
            : $reviews->decide($submission, $request->user(), $data['decision'], $data['review_note']);
        $audit->record($request, 'manual_payment.'.$data['decision'], $result, $before);

        $message = match ($data['decision']) {
            'approved' => 'تم اعتماد الدفع وتفعيل حساب الطالب وتجهيز حساب ولي الأمر. يمكنك فتح رسائل WhatsApp يدوياً من قسم التواصل.',
            'rejected' => 'تم رفض التسجيل وحفظ السبب.',
            default => 'تم إرسال طلب استكمال المعلومات إلى الطالب.',
        };

        return redirect()->route('admin.payment-reviews.show', $submission)->with('success', $message);
    }

    public function guardianWhatsApp(Request $request, ManualPaymentSubmission $submission, GuardianWhatsAppHandoffService $handoff, AuditService $audit): RedirectResponse|JsonResponse
    {
        Gate::authorize('view', $submission);
        abort_unless($submission->status === 'approved' && $submission->order->product_type === 'subscription', 422);
        $invitation = $submission->user->guardianInvitations()->with('student:id,name')->latest()->firstOrFail();
        $url = $handoff->prepare($invitation);
        $audit->record($request, 'guardian_invitation.whatsapp_opened', $invitation);

        return $request->expectsJson() ? response()->json(['url' => $url, 'status' => 'pending']) : redirect()->away($url);
    }

    public function studentWhatsApp(Request $request, ManualPaymentSubmission $submission, StudentWhatsAppHandoffService $handoff, AuditService $audit): RedirectResponse|JsonResponse
    {
        Gate::authorize('view', $submission);
        abort_unless($submission->status === 'approved' && $submission->order->product_type === 'subscription', 422);
        $url = $handoff->prepare($submission->user);
        $audit->record($request, 'student_registration.whatsapp_opened', $submission->user);

        return $request->expectsJson() ? response()->json(['url' => $url, 'status' => 'pending']) : redirect()->away($url);
    }

    public function markWhatsAppSent(Request $request, ManualPaymentSubmission $submission, string $audience, ManualWhatsAppDeliveryService $deliveries, AuditService $audit): RedirectResponse
    {
        Gate::authorize('view', $submission);
        abort_unless($submission->status === 'approved' && $submission->order->product_type === 'subscription', 422);
        abort_unless(in_array($audience, ['student', 'guardian'], true), 404);

        $query = NotificationDelivery::query()->where('channel', 'whatsapp_web');
        if ($audience === 'student') {
            $query->where('user_id', $submission->user_id)->where('template_key', 'student_registration_approved');
        } else {
            $invitation = $submission->user->guardianInvitations()->latest()->firstOrFail();
            $query->where('guardian_invitation_id', $invitation->id)
                ->whereIn('template_key', ['guardian_temporary_credentials', 'guardian_student_linked']);
        }

        $delivery = $query->latest('id')->firstOrFail();
        $before = $delivery->attributesToArray();
        $delivery = $deliveries->markSent($delivery, $request->user());
        $audit->record($request, "{$audience}_whatsapp.marked_sent", $delivery, $before);

        return redirect()->route('admin.payment-reviews.show', $submission)
            ->with('success', $audience === 'student' ? 'تم تأكيد إرسال رسالة الطالب.' : 'تم تأكيد إرسال رسالة ولي الأمر.');
    }

    private function filtered(Request $request): Builder
    {
        $query = ManualPaymentSubmission::query();
        if ($status = $request->string('status')->toString()) {
            validator(['status' => $status], ['status' => [Rule::in(['submitted', 'under_review', 'approved', 'rejected', 'needs_information'])]])->validate();
            $query->where('status', $status);
        }
        if ($channel = $request->string('channel')->toString()) {
            validator(['channel' => $channel], ['channel' => [Rule::in(array_keys(config('payments.manual_transfer.channels')))]])->validate();
            $query->where('channel', $channel);
        }
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('transaction_id', 'like', '%'.$search.'%')->orWhere('sender_identifier', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('order.beneficiary', fn ($student) => $student->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('order', fn ($order) => $order->where('public_id', 'like', '%'.$search.'%'));
            });
        }
        $query->when($request->date('from'), fn ($builder, $date) => $builder->where('created_at', '>=', $date->startOfDay()))
            ->when($request->date('to'), fn ($builder, $date) => $builder->where('created_at', '<=', $date->endOfDay()))
            ->when($request->boolean('duplicates'), fn ($builder) => $builder->whereIn('receipt_sha256', ManualPaymentSubmission::select('receipt_sha256')->groupBy('receipt_sha256')->havingRaw('count(*) > 1')));

        return $query;
    }
}
