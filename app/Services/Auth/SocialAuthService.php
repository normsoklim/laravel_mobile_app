<?php

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialAuthService
{
    public function authenticate(string $idToken, string $provider): User
    {
        // Verify Firebase ID token using Google Client
        $client = new Client([
            'client_id' => config('services.firebase.client_id'),
        ]);

        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            throw ValidationException::withMessages([
                'id_token' => 'Invalid Firebase ID token.',
            ]);
        }

        // Verify the token audience (Firebase project's client ID)
        if (($payload['aud'] ?? null) !== config('services.firebase.client_id')) {
            throw ValidationException::withMessages([
                'id_token' => 'Firebase token audience is invalid.',
            ]);
        }

        // Verify the issuer
        $expectedIssuer = 'https://securetoken.google.com/' . config('services.firebase.client_id');
        if (($payload['iss'] ?? null) !== $expectedIssuer) {
            throw ValidationException::withMessages([
                'id_token' => 'Firebase token issuer is invalid.',
            ]);
        }

        $firebaseUid = $payload['sub'];
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? null;
        $picture = $payload['picture'] ?? null;

        if (!$email) {
            throw ValidationException::withMessages([
                'email' => 'Social account does not provide an email address.',
            ]);
        }

        return DB::transaction(function () use (
            $provider,
            $firebaseUid,
            $email,
            $name,
            $picture
        ) {
            // Check if this social account is already connected
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $firebaseUid)
                ->first();

            if ($socialAccount) {
                return $socialAccount->user;
            }

            // Social account is new. Check whether the email already exists.
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'email' => $email,
                    'full_name' => $name,
                    'avatar' => $picture,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'password' => bcrypt(Str::random(32)),
                ]);
            } else {
                // Update user info if needed
                if ($picture && !$user->avatar) {
                    $user->avatar = $picture;
                }

                if ($name && !$user->full_name) {
                    $user->full_name = $name;
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            }

            // Connect social account to user
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $firebaseUid,
                'provider_email' => $email,
                'provider_name' => $name,
                'provider_avatar' => $picture,
            ]);

            return $user;
        });
    }
}
