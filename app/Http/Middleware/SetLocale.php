<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        // For authenticated users, prefer the saved language from the database.
        $user = $request->user();

        if ($user && $user->preferred_language) {
            $language = $user->preferred_language;
        } else {
            $header = $request->header('Accept-Language', 'en');

            // Accept values such as "km", "km-KH", or "en-US".
            $language = strtolower(
                substr(trim($header), 0, 2)
            );
        }

        // Only allow languages supported by the application.
        if (! in_array($language, ['en', 'km'], true)) {
            $language = 'en';
        }

        App::setLocale($language);

        return $next($request);
    }
}
