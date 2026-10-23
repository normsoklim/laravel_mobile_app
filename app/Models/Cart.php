<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    /**
     * Appended attributes included in JSON responses so the payload
     * matches the Flutter CartProvider getters (itemCount, totalPrice).
     */
    protected $appends = ['item_count', 'total_price'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Total number of physical units across all lines
     * (matches the Flutter CartProvider.itemCount getter).
     */
    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Sum of every line's total price
     * (matches the Flutter CartProvider.totalPrice getter).
     */
    public function getTotalPriceAttribute(): float
    {
        return round($this->items->sum(
            fn ($item) => $item->product->price * $item->quantity
        ), 2);
    }
}