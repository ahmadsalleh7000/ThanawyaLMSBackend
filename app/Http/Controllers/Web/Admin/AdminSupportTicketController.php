<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $tickets = SupportTicket::with(['user:id,name,email', 'assignee:id,name'])->withCount('messages')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where(fn ($inner) => $inner->where('subject', 'like', "%{$search}%")->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")->latest('last_message_at')->paginate(20)->withQueryString();

        return view('admin.support-tickets.index', ['tickets' => $tickets, 'counts' => SupportTicket::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support-tickets.show', ['ticket' => $ticket->load(['user:id,name,email', 'assignee:id,name', 'messages.user:id,name,role'])]);
    }

    public function reply(Request $request, SupportTicket $ticket, AuditService $audit): RedirectResponse
    {
        abort_if($ticket->status === 'closed', 409);
        $data = $request->validate(['message' => ['required', 'string', 'min:5', 'max:5000'], 'status' => ['required', Rule::in(['in_progress', 'waiting_student', 'resolved'])]]);
        $message = $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => trim($data['message'])]);
        $ticket->update(['assigned_to' => $request->user()->id, 'status' => $data['status'], 'last_message_at' => now(), 'resolved_at' => $data['status'] === 'resolved' ? now() : null]);
        $ticket->user->userNotifications()->create(['type' => 'support_ticket', 'title' => 'Support replied to your request', 'title_ar' => 'رد فريق الدعم على طلبك', 'message' => $ticket->subject, 'message_ar' => $ticket->subject, 'action_url' => '/support/'.$ticket->public_id]);
        $audit->record($request, 'support_ticket.replied', $message);

        return back()->with('success', 'تم إرسال الرد وتحديث حالة الطلب.');
    }

    public function update(Request $request, SupportTicket $ticket, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'waiting_student', 'resolved', 'closed'])]]);
        $before = $ticket->attributesToArray();
        $ticket->update(['status' => $data['status'], 'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null]);
        $audit->record($request, 'support_ticket.updated', $ticket, $before);

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function attachment(SupportTicketMessage $message): StreamedResponse
    {
        abort_unless($message->attachment_path && Storage::disk($message->attachment_disk)->exists($message->attachment_path), 404);

        return Storage::disk($message->attachment_disk)->response($message->attachment_path, $message->attachment_name, ['Content-Type' => $message->attachment_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
