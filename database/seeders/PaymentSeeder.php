<?php

namespace Database\Seeders;

use App\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Payment methods matching the Flutter PaymentMethod model
     * (frontend/lib/models/payment_method.dart).
     *
     * The labels, descriptions and Material icon names are identical so the
     * client can use them as-is, while `icon_url` points at the Cloudinary
     * asset that the app renders in real time (the Material icon is only used
     * as a fallback when the URL is missing or fails to load).
     *
     * Uses updateOrCreate so the seeder can be re-run safely.
     */
    public function run(): void
    {
        $payments = [
            [
                'type' => 'ewallet',
                'label' => 'E-Wallet',
                'description' => 'Pay with your e-wallet',
                'icon' => 'account_balance_wallet_outlined',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792574880/tinh_shop/payments/payment_ewallet.svg',
            ],
            [
                'type' => 'card',
                'label' => 'Card',
                'description' => 'Visa, Mastercard',
                'icon' => 'credit_card_outlined',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792574884/tinh_shop/payments/payment_card.png',
            ],
            [
                'type' => 'khqr',
                'label' => 'KHQR / Bakong',
                'description' => 'Scan to pay',
                'icon' => 'qr_code_2_outlined',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792574886/tinh_shop/payments/payment_khqr.png',
            ],
        ];

        foreach ($payments as $payment) {
            Payment::updateOrCreate(
                ['type' => $payment['type']],
                $payment
            );
        }
    }
}
