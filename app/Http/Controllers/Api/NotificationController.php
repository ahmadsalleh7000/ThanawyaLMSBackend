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
        $filters = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'filter' => ['nullable', 'in:all,read,unread']]);
        $base = $request->user()->userNotifications();
        $total = (clone $base)->count();
        $unread = (clone $base)->whereNull('read_at')->count();
        $query = (clone $base)->when(($filters['filter'] ?? '') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when(($filters['filter'] ?? '') === 'read', fn ($query) => $query->whereNotNull('read_at'));
        $items = $query->latest()->orderByDesc('id')->paginate(20);

        return response()->json(['data' => $items->getCollection()->map(fn (UserNotification $item) => $this->serialize($item)), 'meta' => [
            'currentPage' => $items->currentPage(), 'lastPage' => $items->lastPage(), 'total' => $total,
            'unreadCount' => $unread, 'readCount' => $total - $unread,
        ]])->header('Cache-Control', 'private, no-store');
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
