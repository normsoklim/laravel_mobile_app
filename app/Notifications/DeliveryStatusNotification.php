<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered whenever a delivery changes state (assigned, accepted,
 * picked up, on the way, delivered, cancelled, failed).
 *
 * Channels:
 *  - database : persisted so the user can read history later.
 *  - broadcast: real-time event over Laravel Reverb while the app is open.
 *  - fcm      : push alert via Firebase when the app is backgrounded/closed.
 */
class DeliveryStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public ?int $orderId = null,
        public ?int $deliveryId = null,
        public ?string $status = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'fcm'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->orderId,
            'delivery_id' => $this->deliveryId,
            'status' => $this->status,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'data' => array_filter([
                'type' => $this->type,
                'order_id' => $this->orderId !== null ? (string) $this->orderId : null,
                'delivery_id' => $this->deliveryId !== null ? (string) $this->deliveryId : null,
                'status' => $this->status,
            ], fn ($value) => $value !== null),
        ];
    }
}
