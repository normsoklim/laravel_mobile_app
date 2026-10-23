<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'order_item_id',
        'user_id',
        'product_id',
        'rating',
        'comment',
        'image_paths',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'image_paths' => 'array',
        ];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
