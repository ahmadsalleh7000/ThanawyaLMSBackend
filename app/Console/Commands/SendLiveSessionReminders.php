<?php

namespace App\Console\Commands;

use App\Models\LiveSession;
use Illuminate\Console\Command;

class SendLiveSessionReminders extends Command
{
    protected $signature = 'sessions:send-reminders';

    protected $description = 'Send one in-app reminder for live sessions starting tomorrow';

    public function handle(): int
    {
        $sessions = LiveSession::query()->where('status', 'published')->whereBetween('starts_at', [now()->addHours(23), now()->addHours(25)])->with('course')->get();
        $sent = 0;
        foreach ($sessions as $session) {
            $session->course->enrollments()->active()->with('user')->get()->each(function ($enrollment) use ($session, &$sent): void {
                $notification = $enrollment->user->userNotifications()->where('type', 'live_session_reminder')->whereJsonContains('data->session_id', $session->id)->exists();
                if ($notification) {
                    return;
                }
                $enrollment->user->userNotifications()->create(['type' => 'live_session_reminder', 'title' => 'Live class tomorrow', 'title_ar' => 'حصة مباشرة غداً', 'message' => $session->title, 'message_ar' => $session->title, 'action_url' => $session->meeting_url, 'data' => ['session_id' => $session->id, 'starts_at' => $session->starts_at->toISOString()]]);
                $sent++;
            });
        }
        $this->info("Sent {$sent} live session reminder(s).");

        return self::SUCCESS;
    }
}
