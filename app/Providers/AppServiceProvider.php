<?php

namespace App\Providers;

use App\Notifications\Channels\FcmChannel;
use App\Services\CloudinaryService;
use App\Services\Notifications\FcmService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CloudinaryService::class, function ($app) {
            return new CloudinaryService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the custom "fcm" notification channel so notifications
        // that list 'fcm' in via() can be delivered. Without this the
        // ChannelManager throws "Driver [fcm] not supported" and every
        // queued notification job fails.
        Notification::extend('fcm', function ($app) {
            return new FcmChannel($app->make(FcmService::class));
        });

        // Deliver queued jobs (notifications!) right after the response is
        // sent, so the app never needs a separately running queue worker.
        // The response is already flushed at this point, so the client is
        // never blocked by the delivery work.
        app()->terminating(function () {
            try {
                Artisan::call('queue:work', [
                    '--stop-when-empty' => true,
                    '--max-time' => 25,
                ]);
            } catch (Throwable $e) {
                // Queue draining must never break the response.
            }
        });
    }
}
