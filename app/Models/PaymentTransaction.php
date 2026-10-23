<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class PaymentTransaction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'currency',
        'transaction_id',
        'md5',
        'qr',
        'expires_at',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            // Float so JSON emits a number (Flutter expects double).
            'amount' => 'float',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}