<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->userNotifications()->latest()->limit(50)->get()->map(fn (UserNotification $item) => $this->serialize($item));

        return response()->json(['data' => $items, 'meta' => ['unreadCount' => $items->where('isRead', false)->count()]]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $items = $request->user()->userNotifications()
            ->where('type', 'announcement')
            ->whereNull('read_at')
            ->whereHas('announcement', fn ($query) => $query
                ->where('status', 'published')
                ->where(fn ($active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->with('announcement:id,is_banner,expires_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (UserNotification $item) => $this->serialize($item) + [
                'isBanner' => (bool) $item->announcement?->is_banner,
                'expiresAt' => $item->announcement?->expires_at?->toISOString(),
            ]);

        return response()->json(['data' => $items]);
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $item = $request->user()->userNotifications()->findOrFail($notification);
        if (! $item->read_at) {
            $item->update(['read_at' => now()]);
        }

        return response()->json(['data' => $this->serialize($item->refresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => $updated]]);
    }

    private function serialize(UserNotification $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'titleAr' => $item->title_ar,
            'message' => $item->message,
            'messageAr' => $item->message_ar,
            'actionUrl' => $item->action_url,
            'data' => $item->data,
            'isRead' => (bool) $item->read_at,
            'date' => $item->created_at->toISOString(),
        ];
    }
}
