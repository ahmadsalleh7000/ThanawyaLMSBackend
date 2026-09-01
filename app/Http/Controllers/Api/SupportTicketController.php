<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()->withCount('messages')->latest('last_message_at')->paginate(15);

        return response()->json(['data' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => $this->ticket($ticket)), 'meta' => ['currentPage' => $tickets->currentPage(), 'lastPage' => $tickets->lastPage(), 'total' => $tickets->total()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateMessage($request, true);
        $ticket = SupportTicket::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'subject' => trim($data['subject']), 'category' => $data['category'],
            'priority' => $data['priority'], 'status' => 'open', 'last_message_at' => now(),
        ]);
        $message = $this->createMessage($ticket, $request, $data['message'], $data['attachment'] ?? null);

        User::where('role', User::ROLE_ADMIN)->where(function ($query) {
            $query->whereNull('admin_role')->orWhere('admin_role', 'support_agent');
        })->each(fn (User $admin) => $admin->userNotifications()->create([
            'type' => 'support_ticket', 'title' => 'New learner support request', 'title_ar' => 'طلب دعم جديد من متعلم',
            'message' => $ticket->subject, 'message_ar' => $ticket->subject,
            'action_url' => '/admin/support-tickets/'.$ticket->public_id,
        ]));

        return response()->json(['data' => $this->ticket($ticket->loadCount('messages')), 'message' => $this->message($message)], 201);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $ticket = $request->user()->supportTickets()->where('public_id', $publicId)->with(['messages.user:id,name,role'])->withCount('messages')->firstOrFail();

        return response()->json(['data' => $this->ticket($ticket), 'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => $this->message($message))]);
    }

    public function reply(Request $request, string $publicId): JsonResponse
    {
        $ticket = $request->user()->supportTickets()->where('public_id', $publicId)->firstOrFail();
        abort_if($ticket->status === 'closed', 409, 'Closed requests cannot receive new replies.');
        $data = $this->validateMessage($request);
        $message = $this->createMessage($ticket, $request, $data['message'], $data['attachment'] ?? null);
        $ticket->update(['status' => 'open', 'last_message_at' => now(), 'resolved_at' => null]);

        return response()->json(['data' => $this->ticket($ticket->loadCount('messages')), 'message' => $this->message($message)], 201);
    }

    public function attachment(Request $request, SupportTicketMessage $message): StreamedResponse
    {
        abort_unless($message->ticket()->where('user_id', $request->user()->id)->exists(), 404);
        abort_unless($message->attachment_path && Storage::disk($message->attachment_disk)->exists($message->attachment_path), 404);

        return Storage::disk($message->attachment_disk)->response($message->attachment_path, $message->attachment_name, ['Content-Type' => $message->attachment_mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function validateMessage(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'subject' => [$creating ? 'required' : 'sometimes', 'string', 'min:5', 'max:160'],
            'category' => [$creating ? 'required' : 'sometimes', Rule::in(['technical', 'payment', 'course', 'account', 'other'])],
            'priority' => [$creating ? 'required' : 'sometimes', Rule::in(['normal', 'urgent'])],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ]);
    }

    private function createMessage(SupportTicket $ticket, Request $request, string $body, mixed $attachment): SupportTicketMessage
    {
        $file = $attachment;
        $details = [];
        if ($file) {
            $details = ['attachment_disk' => 'local', 'attachment_path' => $file->store('support-attachments/'.now()->format('Y/m'), 'local'), 'attachment_name' => $file->getClientOriginalName(), 'attachment_mime' => $file->getMimeType(), 'attachment_size' => $file->getSize()];
        }

        return $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => trim($body)] + $details);
    }

    private function ticket(SupportTicket $ticket): array
    {
        return ['id' => $ticket->public_id, 'subject' => $ticket->subject, 'category' => $ticket->category, 'priority' => $ticket->priority, 'status' => $ticket->status, 'messageCount' => $ticket->messages_count, 'lastMessageAt' => $ticket->last_message_at->toISOString(), 'createdAt' => $ticket->created_at->toISOString(), 'resolvedAt' => $ticket->resolved_at?->toISOString()];
    }

    private function message(SupportTicketMessage $message): array
    {
        return ['id' => $message->id, 'body' => $message->body, 'author' => $message->user?->name ?? auth()->user()->name, 'authorRole' => $message->user?->role ?? auth()->user()->role, 'attachment' => $message->attachment_path ? ['name' => $message->attachment_name, 'url' => '/api/support/messages/'.$message->id.'/attachment'] : null, 'createdAt' => $message->created_at->toISOString()];
    }
}
