<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\FcmService;
use Illuminate\Notifications\Notification;

/**
 * Custom Laravel notification channel that delivers a push message
 * through Firebase Cloud Messaging.
 *
 * A notification opts into push by implementing a `toFcm()` method that
 * returns the title/body/data payload (or null to skip). This keeps the
 * FCM wiring out of the notification's `via()` list logic while still
 * using Laravel's queueable notification pipeline.
 */
class FcmChannel
{
    public function __construct(private FcmService $fcm)
    {
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        if (empty($payload)) {
            return;
        }

        $this->fcm->sendToUser(
            $notifiable->getKey(),
            [
                'title' => $payload['title'] ?? '',
                'body' => $payload['body'] ?? '',
            ],
            $payload['data'] ?? [],
        );
    }
}
