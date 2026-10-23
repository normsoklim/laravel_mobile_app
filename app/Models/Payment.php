<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /**
     * Payment type keys (matching the Flutter PaymentType enum values)
     * mapped to the canonical label persisted on orders
     * (Order.payment_method).
     */
    public const TYPES = [
        'ewallet' => 'E-Wallet',
        'card' => 'Card',
        'khqr' => 'KHQR',
    ];

    protected $fillable = [
        'type',
        'label',
        'description',
        'icon',
        // Cloudinary URL (or bundled asset path) of the payment icon.
        'icon_url',
        'is_active',
    ];

    protected $appends = ['type_label'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }


    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->label;
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'payment_method', 'type_label');
    }
}