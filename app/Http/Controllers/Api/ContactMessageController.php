<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = ContactMessage::create($request->safe()->only(['name', 'email', 'subject', 'message', 'locale']) + [
            'user_id' => $request->user('sanctum')?->id,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), config('app.key')) : null,
        ]);

        User::where('role', User::ROLE_ADMIN)->get()
            ->filter(fn (User $admin) => $admin->hasAdminPermission('users.manage'))
            ->each(fn (User $admin) => $admin->userNotifications()->create([
                'type' => 'contact_message',
                'title' => 'New contact message',
                'title_ar' => 'رسالة تواصل جديدة',
                'message' => $message->name.' sent: '.$message->subject,
                'message_ar' => 'أرسل '.$message->name.' رسالة بعنوان: '.$message->subject,
                'action_url' => '/admin/contact-messages/'.$message->id,
                'data' => ['contact_message_id' => $message->id, 'reference' => $message->public_reference],
            ]));

        return response()->json([
            'message' => 'Your message has been received.',
            'reference' => $message->public_reference,
        ], 201);
    }
}
