<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic notification for any backend message that is not tied to an
 * order: promotions, announcements, wallet top-ups, security alerts…
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
class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $type  Machine key, e.g. "promotion" | "announcement" | "general".
     * @param  string  $title  Short headline shown in the list + push.
     * @param  string  $message  Human-readable body.
     * @param  array<string, scalar>  $data  Extra key/value payload merged into every channel.
     */
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public array $data = [],
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
        return array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
        ], $this->data);
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
            'data' => array_merge(
                ['type' => $this->type],
                array_map('strval', $this->data),
            ),
        ];
    }
}
