<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'subtotal',
        'delivery_fee',
        'discount',
        'total',
        'address',
        'latitude',
        'longitude',
        'payment_method',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Floats so JSON emits numbers (Flutter expects double).
            'subtotal' => 'float',
            'delivery_fee' => 'float',
            'discount' => 'float',
            'total' => 'float',
            // The delivery destination picked on the map — null when the
            // customer used a manually typed address.
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Status history timeline shown in the Flutter tracking screen.
     */
    public function trackings()
    {
        return $this->hasMany(OrderTracking::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * The active delivery assignment for this order.
     *
     * Cancelled / failed deliveries are ignored so that an order can be
     * re-assigned to another delivery person.
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class)
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->latest('id');
    }

    /**
     * Every delivery ever created for this order (history).
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Whether the order has a map destination (a pin the customer picked).
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
