<?php

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FacebookAuthService
{
    public function authenticate(string $accessToken): User
    {
        $appId = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');

        $debugResponse = Http::get(
            'https://graph.facebook.com/debug_token',
            [
                'input_token' => $accessToken,
                'access_token' => $appId . '|' . $appSecret,
            ]
        );

        if (!$debugResponse->successful()) {
            throw ValidationException::withMessages([
                'access_token' => 'Failed to validate Facebook access token.',
            ]);
        }

        $debugData = $debugResponse->json();

        // Check if token is valid and belongs to our app
        if (
            empty($debugData['data']['is_valid']) ||
            $debugData['data']['is_valid'] !== true
        ) {
            throw ValidationException::withMessages([
                'access_token' => 'Invalid Facebook access token.',
            ]);
        }

        // Verify the token belongs to our Facebook app
        if (
            ($debugData['data']['app_id'] ?? null) !== $appId
        ) {
            throw ValidationException::withMessages([
                'access_token' => 'Facebook access token does not belong to this application.',
            ]);
        }

        $facebookId = $debugData['data']['user_id'] ?? null;

        if (empty($facebookId)) {
            throw ValidationException::withMessages([
                'access_token' => 'Facebook user ID is missing from token.',
            ]);
        }

        // 2. Get user details from Facebook Graph API
        $response = Http::get(
            'https://graph.facebook.com/me',
            [
                'fields' => 'id,name,email,picture.type(large)',
                'access_token' => $accessToken,
            ]
        );

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'access_token' => 'Failed to fetch Facebook user data.',
            ]);
        }

        $facebookUser = $response->json();

        // 3. Verify the user ID matches
        if (($facebookUser['id'] ?? null) !== $facebookId) {
            throw ValidationException::withMessages([
                'access_token' => 'Facebook user ID mismatch.',
            ]);
        }

        // 4. Get email
        $email = $facebookUser['email'] ?? null;

        if (!$email) {
            throw ValidationException::withMessages([
                'email' => 'Facebook account does not provide an email address.',
            ]);
        }

        $name = $facebookUser['name'] ?? null;

        $avatar = $facebookUser['picture']['data']['url'] ?? null;

        // 5. Find or create user
        return DB::transaction(function () use (
            $facebookId,
            $email,
            $name,
            $avatar
        ) {

            // Check whether this Facebook account
            // is already connected to a user
            $socialAccount = SocialAccount::where('provider', 'facebook')
                ->where('provider_id', $facebookId)
                ->first();

            if ($socialAccount) {
                return $socialAccount->user;
            }

            // Facebook account is new.
            // Check whether the email already exists.
            $user = User::where('email', $email)->first();

            if (!$user) {

                // Create new user
                $user = User::create([
                    'email' => $email,
                    'full_name' => $name,
                    'avatar' => $avatar,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'password' => bcrypt(Str::random(32)),
                ]);

            } else {

                // Existing account
                if ($avatar && !$user->avatar) {
                    $user->avatar = $avatar;
                }

                if ($name && !$user->full_name) {
                    $user->full_name = $name;
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                $user->save();
            }

            // Connect Facebook account to user
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'facebook',
                'provider_id' => $facebookId,
            ]);

            return $user;
        });
    }
}
