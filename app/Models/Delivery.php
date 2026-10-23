<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Represents a delivery assignment linking an order to a delivery person.
 *
 * Status lifecycle:
 *   assigned → accepted → picked_up → on_the_way → delivered
 *   (with cancelled / failed as terminal exceptions)
 */
class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_user_id',
        'status',
        'assigned_at',
        'accepted_at',
        'picked_up_at',
        'delivered_at',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'location_updated_at' => 'datetime',
    ];

    /**
     * The order this delivery belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The delivery person assigned to this delivery.
     */
    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    /**
     * GPS history points for this delivery.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(DeliveryLocation::class, 'delivery_id')
            ->orderBy('recorded_at', 'desc');
    }

    /**
     * The most recent GPS location for live tracking.
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(DeliveryLocation::class, 'delivery_id')
            ->latest('recorded_at');
    }

    /**
     * Scope: only active (non-terminal) deliveries.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['delivered', 'cancelled', 'failed']);
    }

    /**
     * Scope: deliveries assigned to a specific delivery person.
     */
    public function scopeForDriver($query, int $userId)
    {
        return $query->where('delivery_user_id', $userId);
    }

    /**
     * Whether the delivery is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled', 'failed'], true);
    }

    /**
     * Whether the driver may transition to the given status.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = match ($this->status) {
            'assigned' => ['accepted', 'cancelled'],
            'accepted' => ['picked_up', 'cancelled'],
            'picked_up' => ['on_the_way', 'failed'],
            'on_the_way' => ['delivered', 'failed'],
            default => [],
        };

        return in_array($newStatus, $allowed, true);
    }

    /**
     * Apply a status transition, recording the timestamp.
     *
     * @return $this
     */
    public function transitionTo(string $newStatus): self
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status} to {$newStatus}."
            );
        }

        $now = Carbon::now();

        $this->status = $newStatus;

        match ($newStatus) {
            'accepted' => $this->accepted_at = $now,
            'picked_up' => $this->picked_up_at = $now,
            'delivered' => $this->delivered_at = $now,
            default => null,
        };

        $this->save();

        return $this;
    }

    /**
     * Update the driver's live GPS position.
     *
     * @return $this
     */
    public function updateLocation(float $latitude, float $longitude, ?float $accuracy = null): self
    {
        $this->current_latitude = $latitude;
        $this->current_longitude = $longitude;
        $this->location_updated_at = Carbon::now();
        $this->save();

        // Also persist to the history table.
        DeliveryLocation::create([
            'delivery_id' => $this->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'recorded_at' => Carbon::now(),
        ]);

        return $this;
    }
}
