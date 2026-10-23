<?php

require __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => 'test',
        'api_key' => 'test',
        'api_secret' => 'test',
    ],
]);

echo 'Cloudinary SDK loaded successfully.' . PHP_EOL;
echo 'Version: ' . Cloudinary::VERSION . PHP_EOL;

// Test that the upload API is accessible
$uploadApi = $cloudinary->uploadApi();
echo 'UploadApi instance: ' . get_class($uploadApi) . PHP_EOL;

// Test that the image asset URL builder works
$image = $cloudinary->image('test_public_id');
echo 'Image asset class: ' . get_class($image) . PHP_EOL;
echo 'Image URL: ' . $image->toUrl() . PHP_EOL;

echo 'All Cloudinary SDK checks passed!' . PHP_EOL;
