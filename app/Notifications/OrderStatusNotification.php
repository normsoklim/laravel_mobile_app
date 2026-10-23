<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered whenever an order changes state (placed, confirmed, shipped,
 * delivered, cancelled) or a payment succeeds/fails.
 *
 * Channels:
 *  - database : persisted so the user can read history later + drives
 *               the unread badge.
 *  - broadcast: real-time event over Laravel Reverb while the app is open.
 *  - fcm      : push alert via Firebase when the app is backgrounded/closed.
 *
 * It implements ShouldQueue so all three deliveries happen on the queue
 * worker and never block the HTTP request that triggered them.
 */
class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $type  Machine key, e.g. "order_status" | "payment".
     * @param  string  $title  Short headline shown in the list + push.
     * @param  string  $message  Human-readable body.
     * @param  int|null  $orderId  Related order (for deep-linking/taps).
     * @param  string|null  $status  Order/payment status.
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public ?int $orderId = null,
        public ?string $status = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'fcm'];
    }

    /**
     * Shared payload used by the database row, the broadcast event and
     * the FCM data message so every channel stays consistent.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->orderId,
            'status' => $this->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Payload consumed by the custom FcmChannel. Data values must be
     * strings for FCM.
     *
     * @return array{title: string, body: string, data: array<string,string>}
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'data' => array_filter([
                'type' => $this->type,
                'order_id' => $this->orderId !== null ? (string) $this->orderId : null,
                'status' => $this->status,
            ], fn ($value) => $value !== null),
        ];
    }
}
