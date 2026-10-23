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
 * Broadcast when a delivery person sends a live GPS update.
 *
 * Channel: private-delivery.{orderId}
 * Authorized for: the order's customer, the assigned driver, admin/staff.
 */
class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Delivery $delivery;

    public float $latitude;

    public float $longitude;

    public ?\DateTimeInterface $updatedAt;


    public function __construct(
        Delivery $delivery,
        float $latitude,
        float $longitude,
        ?\DateTimeInterface $updatedAt = null,
    ) {
        $this->delivery = $delivery;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->updatedAt = $updatedAt;
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
            'type' => 'location_updated',
            'delivery_id' => $this->delivery->id,
            'order_id' => $this->delivery->order_id,
            'delivery_user_id' => $this->delivery->delivery_user_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => $this->updatedAt?->format('c'),
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.location';
    }
}
