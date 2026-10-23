<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    /**
     * Display types consumed by the Flutter wallet screen
     * ('Orders' or 'Top Up' — the UI checks type == 'top up').
     */
    public const TYPES = ['Orders', 'Top Up'];

    /**
     * Ledger statuses. A Bakong KHQR top-up starts as
     * 'pending' and only becomes 'completed' once Laravel
     * verifies the payment.
     */
    public const STATUSES = ['pending', 'completed', 'failed', 'cancelled'];

    protected $fillable = [
        'user_id',
        'wallet_id',
        'order_id',
        'title',
        'type',
        'amount',
        'image_url',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'status',
        'metadata',
    ];

    protected $appends = ['date', 'imageUrl'];

    // Raw column exposed via the camelCase imageUrl append above.
    protected $hidden = ['image_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'balance_before' => 'float',
            'balance_after' => 'float',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Formatted transaction date shown on the wallet tile.
     */
    public function getDateAttribute(): string
    {
        return $this->created_at?->format('M j, Y') ?? '';
    }

    public function getImageUrlAttribute(): string
    {
        return (string) ($this->attributes['image_url'] ?? '');
    }

    /**
     * Credits (top up / refund) increase the balance.
     */
    public function getIsCreditAttribute(): bool
    {
        $type = strtolower((string) $this->type);

        return $type === 'top up' || $type === 'top_up' || $type === 'refund';
    }
}
