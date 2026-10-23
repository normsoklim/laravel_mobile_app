<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    /**
     * Line items share the parent order's timestamps.
     */
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'image',
        'size',
        'color',
        'quantity',
        'price',
    ];

    /**
     * Appended attribute included in JSON responses so the payload
     * matches the Flutter `OrderItem.totalPrice` getter.
     */
    protected $appends = ['total_price'];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'quantity' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Customer reviews for this order line (one per user).
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Line total: unit price × quantity (matches the Flutter
     * `OrderItem.totalPrice` getter).
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->price * $this->quantity, 2);
    }
}