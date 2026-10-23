<?php

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GoogleAuthService
{
    public function authenticate(string $idToken): User
    {
        $client = new Client([
            'client_id' => config('services.google.client_id'),
        ]);

        try {
            $payload = $client->verifyIdToken($idToken);
        } catch (Throwable $e) {
            // Log the real cause (e.g. the server could not reach Google's
            // public-key endpoint, or the token was malformed) so the failure
            // can actually be diagnosed instead of a generic 422 message.
            Log::error('Google ID token verification threw an exception', [
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'id_token' => 'Invalid or expired Google ID token.',
            ]);
        }

        if (!$payload) {
            Log::warning('Google ID token verification returned no payload', [
                'expected_audience' => config('services.google.client_id'),
            ]);

            throw ValidationException::withMessages([
                'id_token' => 'Invalid Google ID token.',
            ]);
        }

        if (($payload['aud'] ?? null) !== config('services.google.client_id')) {
            throw ValidationException::withMessages([
                'id_token' => 'Google token audience is invalid.',
            ]);
        }

        // Google only issues an ID token after the user has authenticated and
        // granted consent, so the e-mail it carries is already owned by the
        // user. Some Google ID tokens omit the `email_verified` claim entirely,
        // so we only reject when Google explicitly reports the address as NOT
        // verified — otherwise a missing claim blocks every sign-in with a
        // "Google email is not verified" error.
        if (array_key_exists('email_verified', $payload)
            && $payload['email_verified'] === false) {
            throw ValidationException::withMessages([
                'email' => 'Google email is not verified.',
            ]);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'] ?? null;

        if (!$email) {
            throw ValidationException::withMessages([
                'email' => 'Google account does not provide an email address.',
            ]);
        }

        return DB::transaction(function () use ($payload, $googleId, $email) {

            $socialAccount = SocialAccount::where('provider', 'google')
                ->where('provider_id', $googleId)
                ->first();

            if ($socialAccount) {
                return $socialAccount->user;
            }

            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'full_name' => $payload['name'] ?? null,
                    'avatar' => $payload['picture'] ?? null,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    // The User model casts `password` with the `hashed` cast,
                    // so pass a plain random string and let Laravel hash it.
                    'password' => Str::random(32),
                ]);
            } else {
                // Update user info if needed (avatar, name)
                if (($payload['picture'] ?? null) && !$user->avatar) {
                    $user->avatar = $payload['picture'];
                }
                if (($payload['name'] ?? null) && !$user->full_name) {
                    $user->full_name = $payload['name'];
                }
                // Mark email as verified since Google verified it
                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }
                $user->save();
            }

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $googleId,
                'provider_email' => $email,
                'provider_name' => $payload['name'] ?? null,
                'provider_avatar' => $payload['picture'] ?? null,
            ]);

            return $user;
        });
    }
}