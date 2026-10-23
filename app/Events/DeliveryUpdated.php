<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a delivery's status changes.
 *
 * Channel: private-delivery.{orderId}
 * Authorized for: the order's customer, the assigned driver, admin/staff.
 */
class DeliveryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Delivery $delivery;

    public string $eventType;

    /**
     * @param  Delivery  $delivery  The delivery that changed.
     * @param  string  $eventType  e.g. "status_changed", "location_updated".
     */
    public function __construct(Delivery $delivery, string $eventType = 'status_changed')
    {
        $this->delivery = $delivery;
        $this->eventType = $eventType;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("delivery.{$this->delivery->order_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->eventType,
            'delivery_id' => $this->delivery->id,
            'order_id' => $this->delivery->order_id,
            'status' => $this->delivery->status,
            'delivery_user_id' => $this->delivery->delivery_user_id,
            'current_latitude' => $this->delivery->current_latitude,
            'current_longitude' => $this->delivery->current_longitude,
            'location_updated_at' => $this->delivery->location_updated_at?->toISOString(),
            'assigned_at' => $this->delivery->assigned_at?->toISOString(),
            'accepted_at' => $this->delivery->accepted_at?->toISOString(),
            'picked_up_at' => $this->delivery->picked_up_at?->toISOString(),
            'delivered_at' => $this->delivery->delivered_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.updated';
    }
}
