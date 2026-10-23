<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only app: there is no named "login" route, so
        // guests must NOT be redirected to one. Returning null makes the
        // Authenticate middleware throw a plain AuthenticationException,
        // which is rendered as 401 JSON for api/* requests (see
        // shouldRenderJsonWhen below) instead of a 500
        // "Route [login] not defined" error.
        $middleware->redirectGuestsTo(fn () => null);

        // Set the application locale based on the Accept-Language header.
        $middleware->appendToGroup('api', [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
