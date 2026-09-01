<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'in_progress', 'resolved'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = ContactMessage::query()->with(['user:id,name,email', 'handler:id,name']);
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));
        $query->when($filters['q'] ?? null, function ($builder, string $search): void {
            $builder->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('public_reference', 'like', "%{$search}%"));
        });

        return view('admin.contact-messages.index', [
            'messages' => $query->latest()->paginate(15)->withQueryString(),
            'counts' => [
                'new' => ContactMessage::where('status', 'new')->count(),
                'in_progress' => ContactMessage::where('status', 'in_progress')->count(),
                'resolved' => ContactMessage::where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        return view('admin.contact-messages.show', [
            'message' => $contactMessage->load(['user:id,name,email', 'handler:id,name', 'replier:id,name']),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $contactMessage->attributesToArray();
        $contactMessage->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'handled_by' => $data['status'] !== 'new' || filled($data['admin_note'] ?? null) ? $request->user()->id : null,
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);
        $audit->record($request, 'contact_message.status_updated', $contactMessage, $before);

        return back()->with('success', 'تم تحديث حالة الرسالة.');
    }

    public function reply(Request $request, ContactMessage $contactMessage, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'reply_body' => ['required', 'string', 'min:5', 'max:5000'],
            'status' => ['required', Rule::in(['in_progress', 'resolved'])],
        ]);
        $before = $contactMessage->attributesToArray();
        $subject = str_starts_with($contactMessage->subject, 'Re: ')
            ? $contactMessage->subject
            : 'Re: '.$contactMessage->subject;

        Mail::raw(trim($data['reply_body']), function ($message) use ($contactMessage, $subject): void {
            $message->to($contactMessage->email, $contactMessage->name)->subject($subject);
        });

        $contactMessage->update([
            'status' => $data['status'],
            'last_reply_body' => trim($data['reply_body']),
            'handled_by' => $request->user()->id,
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);
        $audit->record($request, 'contact_message.replied', $contactMessage, $before);

        return back()->with('success', 'تم إرسال الرد وتحديث سجل الرسالة.');
    }
}
