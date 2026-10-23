<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    use HasFactory;

    protected $table = 'order_trackings';

    protected $fillable = [
        'order_id',
        'status',
        'description',
        'location',
        'tracking_date',
        'tracking_time',
        'is_completed',
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'tracking_time' => 'datetime',
        'is_completed' => 'boolean',
    ];

    /**
     * Order associated with this tracking record.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
