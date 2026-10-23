<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single GPS reading captured during a delivery.
 *
 * The deliveries table keeps the latest position for fast map
 * refreshes; this table retains the full history for troubleshooting
 * and reporting.
 */
class DeliveryLocation extends Model
{
    protected $fillable = [
        'delivery_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /**
     * The delivery this location point belongs to.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
