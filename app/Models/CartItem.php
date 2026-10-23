<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'size',
        'color',
    ];

    /**
     * Appended attribute included in JSON responses so the payload
     * matches the Flutter CartItem.totalPrice getter.
     */
    protected $appends = ['total_price'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Line total: unit price × quantity (matches the Flutter
     * CartItem.totalPrice getter).
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->product->price * $this->quantity, 2);
    }
}