<?php

/**
 * Uploads the bundled payment-method icons to Cloudinary and prints the
 * resulting URLs so they can be stored in the `payments.icon_url` column
 * (see Database\Seeders\PaymentSeeder).
 *
 * Usage: php upload_payment_icons.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var App\Services\CloudinaryService $cloudinary */
$cloudinary = $app->make(App\Services\CloudinaryService::class);

$assets = [
    'ewallet' => __DIR__ . '/../frontend/assets/images/svg/wallet.svg',
    'card' => __DIR__ . '/../frontend/assets/images/credit_card.png',
    'khqr' => __DIR__ . '/../frontend/assets/images/bakong.png',
];

foreach ($assets as $type => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Missing asset for {$type}: {$path}" . PHP_EOL);
        continue;
    }

    $url = $cloudinary->uploadAndGetUrl(
        $path,
        ['public_id' => 'payment_' . $type],
        'tinh_shop/payments'
    );

    echo $type . ' => ' . $url . PHP_EOL;
}
