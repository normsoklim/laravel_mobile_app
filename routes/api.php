<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Auth\PhonePasswordResetController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\KhqrPaymentController;
use App\Http\Controllers\Api\NavigationItemController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingSlideController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SpecialOfferController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification-code', [
    AuthController::class,
    'resendVerificationCode',
]);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [
    AuthController::class,
    'google',
]);
Route::post('/auth/facebook', [
    AuthController::class,
    'facebook',
]);
Route::post('/auth/social', [
    SocialAuthController::class,
    'socialLogin',
]);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::post('/verify-password-reset-otp', [
    AuthController::class,
    'verifyPasswordResetOtp',
]);

Route::post('/reset-password', [
    AuthController::class,
    'resetPassword',
]);

// Password recovery with Firebase Phone Authentication (SMS OTP).
//
// The Flutter app sends the Firebase ID token it obtained after the OTP
// was verified in the `Authorization: Bearer <firebase-id-token>` header.
// The phone number is read from that verified token, never from the
// request body, so the client cannot reset somebody else's account.
//
// Full URL: POST /api/v1/auth/password/phone/reset
Route::post('/v1/auth/password/phone/reset', [
    PhonePasswordResetController::class,
    'reset',
])->middleware('throttle:5,1');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ProductController::class, 'reviews']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// Countries (public read — powers the phone-number country picker)
Route::get('/countries', [CountryController::class, 'index']);
Route::get('/countries/{id}', [CountryController::class, 'show']);

// Special offers (public read — active offers power the Special Offers screen)
Route::get('/special-offers', [SpecialOfferController::class, 'index']);
Route::get('/special-offers/{id}', [SpecialOfferController::class, 'show']);

// Bottom navigation items (public read — powers the Flutter nav bar)
Route::get('/navigation-items', [NavigationItemController::class, 'index']);
Route::get('/navigation-items/{id}', [NavigationItemController::class, 'show']);

// Onboarding / walkthrough slides (public read — powers the Flutter carousel)
Route::get('/onboarding-slides', [OnboardingSlideController::class, 'index']);
Route::get('/onboarding-slides/{id}', [OnboardingSlideController::class, 'show']);

Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user-profile', [AuthController::class, 'profile']);
    // Used by the Flutter client to validate a restored ("Remember me")
    // session before it lets the customer into the app.
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/delete-account', [AuthController::class, 'deleteAccount']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all-device', [AuthController::class, 'logoutAllDevice']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'updateAvatar']);
    Route::post('/create-pin', [AuthController::class, 'createPin']);
    Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
    Route::post('/change-pin', [AuthController::class, 'changePin']);

    // Language preference (protected)
    Route::post('/language', function (Request $request) {
        $language = $request->input('language', 'en');

        if (! in_array($language, ['en', 'km'], true)) {
            return response()->json([
                'message' => __('messages.validation_failed'),
            ], 422);
        }

        $request->user()->update([
            'preferred_language' => $language,
        ]);

        return response()->json([
            'message' => __('messages.language_changed'),
            'language' => $language,
        ]);
    });

    // Product management (protected)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::patch('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Category management (protected)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::patch('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Special offer management (protected)
    Route::post('/special-offers', [SpecialOfferController::class, 'store']);
    Route::put('/special-offers/{id}', [SpecialOfferController::class, 'update']);
    Route::patch('/special-offers/{id}', [SpecialOfferController::class, 'update']);
    Route::delete('/special-offers/{id}', [SpecialOfferController::class, 'destroy']);

    // Navigation item management (protected — the icons live in Cloudinary)
    Route::post('/navigation-items', [NavigationItemController::class, 'store']);
    Route::put('/navigation-items/{id}', [NavigationItemController::class, 'update']);
    Route::patch('/navigation-items/{id}', [NavigationItemController::class, 'update']);
    Route::delete('/navigation-items/{id}', [NavigationItemController::class, 'destroy']);
    Route::post('/navigation-items/{id}/icon', [NavigationItemController::class, 'uploadIcon']);

    // Onboarding slide management (protected — the images live in Cloudinary)
    Route::post('/onboarding-slides', [OnboardingSlideController::class, 'store']);
    Route::put('/onboarding-slides/{id}', [OnboardingSlideController::class, 'update']);
    Route::patch('/onboarding-slides/{id}', [OnboardingSlideController::class, 'update']);
    Route::delete('/onboarding-slides/{id}', [OnboardingSlideController::class, 'destroy']);
    Route::post('/onboarding-slides/{id}/image', [OnboardingSlideController::class, 'uploadImage']);

    // Orders (protected — tied to the authenticated user)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/tracking', [OrderController::class, 'tracking']);
    Route::post('/orders/{id}/reviews', [OrderController::class, 'reviews']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::patch('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Deliveries (protected)
    Route::get('/deliveries/available', [DeliveryController::class, 'available']);
    // List of users that can be assigned to a delivery (admin/staff).
    Route::get('/deliveries/drivers', [DeliveryController::class, 'drivers']);
    Route::post('/deliveries/assign', [DeliveryController::class, 'assign']);
    Route::get('/deliveries/my', [DeliveryController::class, 'myDeliveries']);
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::get('/deliveries/{id}', [DeliveryController::class, 'show']);
    Route::patch('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('/deliveries/{id}/location', [DeliveryController::class, 'updateLocation']);
    Route::get('/orders/{id}/delivery-tracking', [DeliveryController::class, 'tracking']);

    // Cart (protected — one cart per user)
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{productId}/increase', [CartController::class, 'increaseQuantity']);
    Route::patch('/cart/items/{productId}/decrease', [CartController::class, 'decreaseQuantity']);
    Route::patch('/cart/items/{productId}/quantity', [CartController::class, 'setQuantity']);
    Route::patch('/cart/items/{productId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{productId}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Favorites (protected — one favorite entry per user per product)
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'add']);
    Route::patch('/favorites/{productId}/increase', [FavoriteController::class, 'increase']);
    Route::patch('/favorites/{productId}/decrease', [FavoriteController::class, 'decrease']);
    Route::patch('/favorites/{productId}/quantity', [FavoriteController::class, 'setQuantity']);
    Route::delete('/favorites/{productId}', [FavoriteController::class, 'remove']);
    Route::delete('/favorites', [FavoriteController::class, 'clear']);

    // Payment method management (protected)
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::put('/payments/{id}', [PaymentController::class, 'update']);
    Route::patch('/payments/{id}', [PaymentController::class, 'update']);
    Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
    // Uploads the payment icon to Cloudinary and stores the URL.
    Route::post('/payments/{id}/icon', [PaymentController::class, 'uploadIcon']);

    // Bakong KHQR payment flow (protected — tied to the authenticated user)
    //   POST /payments/khqr              → generate a QR for an order
    //   GET  /payments/khqr/{id}/status  → poll payment status
    //   POST /payments/khqr/{id}/check   → verify with Bakong & confirm order
    Route::post('/payments/khqr', [KhqrPaymentController::class, 'createKhqr']);
    Route::get('/payments/khqr/{id}/status', [KhqrPaymentController::class, 'status']);
    Route::post('/payments/khqr/{id}/check', [KhqrPaymentController::class, 'check']);

    // Checkout summary (protected — computed from the user's cart)
    Route::get('/checkout-summary', [CheckoutController::class, 'summary']);

    Route::get('/wallet', [WalletController::class, 'balance']);
    Route::get('/wallet/transactions', [WalletController::class, 'index']);
    Route::get('/wallet/transactions/{id}', [WalletController::class, 'show']);
    Route::post('/wallet/top-up', [WalletController::class, 'topUp']);
    Route::post('/wallet/test-payment', [WalletController::class, 'testPayment']);
    Route::post('/wallet/pay', [WalletController::class, 'pay']);

    // Addresses (protected — tied to the authenticated user)
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::get('/addresses/{id}', [AddressController::class, 'show']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::patch('/addresses/{id}', [AddressController::class, 'update']);
    Route::patch('/addresses/{id}/set-default', [AddressController::class, 'setDefault']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    // Notifications (protected — tied to the authenticated user)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // FCM device tokens (protected — push delivery targets)
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy']);
});

Route::get('/language-test', function (Request $request) {
    return response()->json([
        'success' => true,
        'locale' => app()->getLocale(),
        'message' => __('messages.welcome'),
    ]);
});
