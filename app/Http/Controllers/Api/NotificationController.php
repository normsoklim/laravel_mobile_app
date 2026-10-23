<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

/**
 * Notification history endpoints for the authenticated user.
 *
 * Every query is scoped to $request->user()'s own notification
 * relationship, so a user can never read, mark, or delete another
 * user's notifications by guessing an id.
 */
class NotificationController extends Controller
{
    /**
     * POST /api/notifications
     *
     * Post a notification from the backend. Delivery fans out to all
     * three channels (database history + Reverb broadcast + FCM push)
     * and is queued, so the request returns immediately.
     *
     * Targeting:
     *  - `all` = true            → every user (promotions/announcements)
     *  - `user_id` given         → that specific user
     *  - neither                 → the authenticated user
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'all' => ['nullable', 'boolean'],
        ]);

        $notification = new GeneralNotification(
            type: $data['type'] ?? 'general',
            title: $data['title'],
            message: $data['message'],
        );

        if (! empty($data['all'])) {
            // Broadcast to every user. Notification::send queues one
            // delivery job per user, so a large user base never blocks
            // this request.
            $recipients = User::query()->select('id')->get();

            Notification::send($recipients, $notification);

            return response()->json([
                'message' => 'Notification posted to all users.',
                'recipients' => $recipients->count(),
            ], Response::HTTP_CREATED);
        }

        $user = ! empty($data['user_id'])
            ? User::findOrFail($data['user_id'])
            : $request->user();

        $user->notify($notification);

        return response()->json([
            'message' => 'Notification posted.',
            'user_id' => $user->id,
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/notifications
     * Paginated notification history (newest first).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($n) => $this->transform($n)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     * Drives the red badge on the bell icon.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * PATCH /api/notifications/read-all
     */
    public function markAllRead(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, string $id)
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    /**
     * Shape a database notification row into a stable JSON contract
     * for the Flutter client.
     */
    private function transform($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'order_id' => $data['order_id'] ?? null,
            'status' => $data['status'] ?? null,
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
