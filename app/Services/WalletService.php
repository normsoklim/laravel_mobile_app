<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;


class WalletService
{
    /**
     * Every user gets a wallet on first access.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'balance' => 0,
                'currency' => 'USD',
            ]
        );
    }


    public function credit(
        User $user,
        float $amount,
        string $type,
        string $description,
        ?string $reference = null,
        ?int $orderId = null,
        ?array $metadata = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than 0.');
        }

        return DB::transaction(function () use (
            $user,
            $amount,
            $type,
            $description,
            $reference,
            $orderId,
            $metadata
        ) {
            $wallet = $this->getOrCreateWallet($user);

            $wallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $before = (float) $wallet->balance;
            $after = $before + $amount;

            $wallet->update([
                'balance' => $after,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'order_id' => $orderId,
                'type' => $type,
                'title' => $description,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference ?? $this->generateReference(),
                'description' => $description,
                'status' => 'completed',
                'metadata' => $metadata,
            ]);
        });
    }

    public function debit(
        User $user,
        float $amount,
        string $type,
        string $description,
        ?string $reference = null,
        ?int $orderId = null,
        ?array $metadata = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than 0.');
        }

        return DB::transaction(function () use (
            $user,
            $amount,
            $type,
            $description,
            $reference,
            $orderId,
            $metadata
        ) {
            $wallet = $this->getOrCreateWallet($user);

            $wallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            $before = (float) $wallet->balance;

            if ($before < $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $after = $before - $amount;

            $wallet->update([
                'balance' => $after,
            ]);

            // Fetch product image for order transactions
            $imageUrl = null;
            if ($type === 'Orders' && $orderId) {
                $imageUrl = $this->getOrderImageUrl($orderId);
            }

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'order_id' => $orderId,
                'type' => $type,
                'title' => $description,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference ?? $this->generateReference(),
                'description' => $description,
                'status' => 'completed',
                'metadata' => $metadata,
                'image_url' => $imageUrl,
            ]);
        });
    }

    /**
     * Get the first order item's image for a given order.
     */
    private function getOrderImageUrl(int $orderId): ?string
    {
        $orderItem = OrderItem::where('order_id', $orderId)
            ->first();

        return $orderItem?->image;
    }

    private function generateReference(): string
    {
        return 'WAL-' . strtoupper(Str::random(12));
    }
}
