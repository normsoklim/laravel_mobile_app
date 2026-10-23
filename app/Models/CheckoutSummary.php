<?php

namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Computed checkout totals (value object — no database table).
 *
 * Maps to the Flutter CheckoutSummary model
 * (frontend/lib/models/checkout_summary.dart):
 * - subtotal     -> subtotal
 * - delivery_fee -> deliveryFee
 * - discount     -> discount
 * - total        -> total (computed: subtotal + delivery_fee - discount)
 *
 * The same fields are persisted on orders (Order model) at checkout.
 *
 * @implements Arrayable<string, float>
 */
class CheckoutSummary implements Arrayable, JsonSerializable
{
    /**
     * @param  float  $subtotal  Sum of the cart line prices.
     * @param  float  $deliveryFee  Fee of the selected delivery method.
     * @param  float  $discount  Applied discount amount.
     */
    public function __construct(
        public readonly float $subtotal,
        public readonly float $deliveryFee = 0.0,
        public readonly float $discount = 0.0,
    ) {}

    /**
     * Build the summary from the user's cart (subtotal is computed
     * server-side from the cart items so it can't be tampered with).
     */
    public static function fromCart(Cart $cart, float $deliveryFee = 0.0, float $discount = 0.0): self
    {
        return new self(
            subtotal: round((float) $cart->total_price, 2),
            deliveryFee: round($deliveryFee, 2),
            discount: round($discount, 2),
        );
    }

    /**
     * Final amount the customer pays
     * (matches the Flutter CheckoutSummary.total getter).
     */
    public function total(): float
    {
        return round($this->subtotal + $this->deliveryFee - $this->discount, 2);
    }

    /**
     * @return array{subtotal: float, delivery_fee: float, discount: float, total: float}
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->deliveryFee,
            'discount' => $this->discount,
            'total' => $this->total(),
        ];
    }

    /**
     * @return array{subtotal: float, delivery_fee: float, discount: float, total: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
