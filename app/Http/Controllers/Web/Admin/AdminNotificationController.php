<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminNotificationController extends Controller
{
    public function read(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        $target = Str::startsWith((string) $notification->action_url, '/admin/')
            ? $notification->action_url
            : route('admin.dashboard');

        return redirect()->to($target);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم كل الإشعارات كمقروءة.');
    }
}
