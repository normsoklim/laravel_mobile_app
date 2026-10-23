<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FirebasePhoneTokenVerifier;
use App\Services\Auth\PhoneNumberNormalizer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PhonePasswordResetController extends Controller
{
    /**
     * Maximum number of reset attempts per verified phone number and hour.
     */
    private const PHONE_ATTEMPT_LIMIT = 5;

    /**
     * Seconds a consumed phone-authentication session is kept before it
     * is cleaned up. The unique constraint is the real control; this only
     * keeps the table small.
     */
    private const CONSUMED_RETENTION_DAYS = 1;

    public function reset(
        Request $request,
        FirebasePhoneTokenVerifier $tokenVerifier,
        PhoneNumberNormalizer $phoneNormalizer
    ): JsonResponse {
        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        $idToken = $request->bearerToken();

        if (! $idToken) {
            return response()->json([
                'message' => 'Invalid or expired phone verification.',
            ], 401);
        }

        try {
            $identity = $tokenVerifier->verify($idToken);
        } catch (AuthenticationException $e) {
            return response()->json([
                'message' => 'Invalid or expired phone verification.',
            ], 401);
        }

        // Defence in depth: only a phone-provider authentication may be
        // used to reset a password. A recent Google/Facebook session that
        // merely carries a linked phone number is not enough.
        if (($identity['sign_in_provider'] ?? null) !== 'phone') {
            return response()->json([
                'message' => 'Invalid or expired phone verification.',
            ], 401);
        }

        $now = now()->timestamp;
        $authTime = $identity['auth_time'];
        $maxAge = (int) config('firebase.phone_auth_max_age', 600);

        // Require a recent phone sign-in and reject future timestamps.
        if ($authTime > $now + 60 || $authTime < $now - $maxAge) {
            return response()->json([
                'message' => 'Phone verification expired. Please verify again.',
            ], 401);
        }

        $phone = $phoneNormalizer->normalize($identity['phone_number']);

        // The verified number must be a valid E.164 number.
        if ($phone === null) {
            return response()->json([
                'message' => 'Unable to reset this account.',
            ], 422);
        }

        // Additional per-phone protection, alongside the route/IP throttling.
        $limitKey = 'password-reset-phone:'.hash('sha256', $phone);

        if (RateLimiter::tooManyAttempts($limitKey, self::PHONE_ATTEMPT_LIMIT)) {
            return response()->json([
                'message' => 'Too many reset attempts. Try again later.',
            ], 429);
        }

        RateLimiter::hit($limitKey, 3600);

        try {
            $reset = DB::transaction(function () use (
                $identity,
                $phone,
                $phoneNormalizer,
                $validated
            ) {
                // Housekeeping: drop the phone-authentication sessions that
                // were consumed a while ago.
                DB::table('consumed_phone_reset_authentications')
                    ->where('created_at', '<', now()->subDays(self::CONSUMED_RETENTION_DAYS))
                    ->delete();

                $user = $this->findUserByPhone($phone, $phoneNormalizer);

                if (! $user) {
                    return false;
                }

                // The unique constraint prevents replay of the same
                // phone-authentication session (and concurrent reuse).
                DB::table('consumed_phone_reset_authentications')
                    ->insert([
                        'firebase_uid' => $identity['uid'],
                        'auth_time' => $identity['auth_time'],
                        'created_at' => now(),
                    ]);

                $user->password = Hash::make($validated['password']);

                // Invalidates the Laravel "remember me" cookies.
                $user->setRememberToken(Str::random(60));

                $user->save();

                // Revoke every Laravel session (Sanctum personal access
                // tokens) so the old password cannot be used any more.
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                // Store the phone number in the canonical E.164 format when
                // it was still stored in a local format.
                $this->canonicalisePhoneNumber($user, $phone);

                return true;
            });
        } catch (QueryException $e) {
            // PostgreSQL unique_violation = 23505.
            // MySQL duplicate-key error = 23000.
            $sqlState = $e->errorInfo[0] ?? null;

            if (in_array($sqlState, ['23505', '23000'], true)) {
                return response()->json([
                    'message' => 'This phone verification has already been used.',
                ], 409);
            }

            report($e);

            return response()->json([
                'message' => 'Unable to reset password right now.',
            ], 500);
        }

        if (! $reset) {
            return response()->json([
                'message' => 'Unable to reset this account.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully. Please sign in again.',
        ]);
    }

    /**
     * Find the customer account that owns the verified phone number.
     *
     * The number stored on the account may still use a local format
     * (`012345678`), so every representation of the verified E.164
     * number is looked up and the result is double-checked.
     */
    private function findUserByPhone(
        string $phone,
        PhoneNumberNormalizer $normalizer
    ): ?User {
        $candidates = $this->phoneCandidates($phone, $normalizer);

        if ($candidates === []) {
            return null;
        }

        $users = User::whereIn('phone', $candidates)
            ->lockForUpdate()
            ->get();

        $matches = $users->filter(
            fn (User $user) => $normalizer->matches($user->phone, $phone)
        )->values();

        // No account, or an ambiguous match — never reset the password.
        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    /**
     * Every way the verified number may have been stored on the account.
     *
     * @return array<int, string>
     */
    private function phoneCandidates(
        string $phone,
        PhoneNumberNormalizer $normalizer
    ): array {
        $digits = ltrim($phone, '+');
        $countryCode = preg_replace(
            '/\D+/',
            '',
            (string) config('firebase.default_country_code', '855')
        ) ?? '855';

        $national = str_starts_with($digits, $countryCode)
            ? substr($digits, strlen($countryCode))
            : $digits;

        $candidates = [
            $phone,
            $digits,
            '0'.$national,
            $national,
        ];

        return array_values(array_unique(array_filter(
            $candidates,
            fn (string $candidate) => $normalizer->normalize($candidate) !== null
        )));
    }

    /**
     * Store the phone number in E.164 format when the account still uses
     * a local format. A unique conflict is ignored on purpose: the
     * password reset must not fail because of a legacy duplicate.
     */
    private function canonicalisePhoneNumber(User $user, string $phone): void
    {
        if ($user->phone === $phone) {
            return;
        }

        $alreadyUsed = User::where('phone', $phone)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return;
        }

        try {
            $user->phone = $phone;
            $user->save();
        } catch (QueryException $e) {
            report($e);
        }
    }
}
