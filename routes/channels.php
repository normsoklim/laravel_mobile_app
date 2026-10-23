<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Laravel's database/broadcast notifications are delivered on a private
| channel named after the notifiable model and its id, e.g.
| "App.Models.User.5". Here we authorise that channel so a user may only
| subscribe to THEIR OWN notification stream — changing the id in the
| channel name must never let someone receive another user's events.
|
*/

Broadcast::channel('App.Models.User.{notifiableId}', function ($user, int $notifiableId) {
    return (int) $user->getAuthIdentifier() === (int) $notifiableId;
});

/**
 * Private channel for live delivery tracking.
 *
 * Channel name: private-delivery.{orderId}
 *
 * Authorized subscribers:
 *   - The order's customer
 *   - The assigned delivery person
 *   - Admin / staff
 */
Broadcast::channel('delivery.{orderId}', function ($user, int $orderId) {
    $order = \App\Models\Order::find($orderId);

    if (!$order) {
        return false;
    }

    $userId = (int) $user->getAuthIdentifier();

    // The customer who placed the order.
    if ((int) $order->user_id === $userId) {
        return true;
    }

    // The assigned delivery person.
    $delivery = \App\Models\Delivery::where('order_id', $orderId)->first();
    if ($delivery && (int) $delivery->delivery_user_id === $userId) {
        return true;
    }

    // Admin / staff can monitor any delivery.
    if (in_array($user->role, ['admin', 'staff'], true)) {
        return true;
    }

    return false;
});
