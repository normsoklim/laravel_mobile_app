<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies the Firebase ID token that the Flutter app obtains after a
 * Firebase Phone Authentication (SMS OTP) round-trip.
 *
 * The verification is fully server-side:
 *
 *  - the RSA signature is checked against the public keys Google
 *    publishes for Firebase ID tokens (fetched over HTTPS and cached),
 *  - the issuer must be our own Firebase project
 *    (https://securetoken.google.com/<project_id>),
 *  - the audience (the Firebase app id) must be an allowed one,
 *  - `exp` / `nbf` / `iat` are checked by the JWT library,
 *  - the token must have been issued by the *phone* provider and must
 *    carry a phone number.
 *
 * The phone number is therefore never taken on trust from the client:
 * it always comes from a token that Firebase signed after verifying
 * the SMS code.
 */
class FirebasePhoneTokenVerifier
{
    /**
     * Verify the Firebase ID token and require a recent phone-provider
     * authentication.
     *
     * @return array{
     *     uid: string,
     *     phone_number: string,
     *     auth_time: int,
     *     sign_in_provider: string
     * }
     *
     * @throws AuthenticationException
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);

        if ($idToken === '') {
            throw new AuthenticationException('Invalid or expired verification.');
        }

        try {
            $keys = $this->publicKeys();

            if ($keys === []) {
                throw new AuthenticationException(
                    'Invalid or expired verification.'
                );
            }

            $previousLeeway = JWT::$leeway;
            JWT::$leeway = (int) config('firebase.id_token.leeway', 60);

            try {
                $claims = JWT::decode($idToken, $keys);
            } finally {
                JWT::$leeway = $previousLeeway;
            }

            $firebaseClaims = $claims->firebase ?? null;
            $firebaseClaims = is_object($firebaseClaims)
                ? (array) $firebaseClaims
                : (is_array($firebaseClaims) ? $firebaseClaims : []);

            $uid = $claims->sub ?? null;
            $phone = $claims->phone_number
                ?? ($firebaseClaims['phone_number'] ?? null);
            $authTime = $claims->auth_time ?? null;
            $provider = $firebaseClaims['sign_in_provider'] ?? null;

            $this->assertValidIssuer($claims->iss ?? null);
            $this->assertValidAudience($claims->aud ?? null);

            if (
                ! is_string($uid) || $uid === '' ||
                ! is_string($phone) || $phone === '' ||
                ! is_numeric($authTime) ||
                ! is_string($provider) ||
                $provider !== 'phone'
            ) {
                throw new AuthenticationException(
                    'Phone verification is required.'
                );
            }

            return [
                'uid' => $uid,
                'phone_number' => $phone,
                'auth_time' => (int) $authTime,
                'sign_in_provider' => $provider,
            ];
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new AuthenticationException(
                'Invalid or expired verification.'
            );
        }
    }

    /**
     * The token must have been issued by our Firebase project.
     */
    private function assertValidIssuer(mixed $issuer): void
    {
        $projectId = (string) config('firebase.project_id');

        if ($projectId === '') {
            throw new AuthenticationException(
                'Invalid or expired verification.'
            );
        }

        if ($issuer !== 'https://securetoken.google.com/'.$projectId) {
            throw new AuthenticationException(
                'Invalid or expired verification.'
            );
        }
    }

    /**
     * The token must target one of the Firebase apps we ship.
     *
     * When no audience is configured every audience is accepted, but the
     * issuer check above still guarantees the token belongs to our
     * project.
     */
    private function assertValidAudience(mixed $audience): void
    {
        $allowed = (array) config('firebase.allowed_audiences', []);

        if ($allowed === []) {
            if (! is_string($audience) || $audience === '') {
                throw new AuthenticationException(
                    'Invalid or expired verification.'
                );
            }

            return;
        }

        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $value) {
            if (is_string($value) && in_array($value, $allowed, true)) {
                return;
            }
        }

        throw new AuthenticationException(
            'Invalid or expired verification.'
        );
    }

    /**
     * Google's public keys used to sign Firebase ID tokens.
     *
     * They are fetched over HTTPS and cached (the shared cache store
     * keeps them available to every server in a multi-node setup).
     *
     * @return array<string, Key>
     */
    private function publicKeys(): array
    {
        $cacheKey = (string) config('firebase.id_token.cache_key');
        $ttl = (int) config('firebase.id_token.cache_ttl', 3600);

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            /** @var array<string, Key> $cached */
            return $cached;
        }

        $keys = $this->fetchPublicKeys();

        if ($keys !== []) {
            Cache::put($cacheKey, $keys, $ttl);
        }

        return $keys;
    }

    /**
     * Download the public keys, preferring the JWKS endpoint and falling
     * back to the x509 certificate endpoint.
     *
     * @return array<string, Key>
     */
    private function fetchPublicKeys(): array
    {
        $jwksUrl = (string) config('firebase.id_token.jwks_url');
        $certsUrl = (string) config('firebase.id_token.certs_url');

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get($jwksUrl);

            if ($response->successful()) {
                $jwks = $response->json();

                if (is_array($jwks) && ! empty($jwks['keys'])) {
                    /** @var array<string, Key> $keys */
                    $keys = JWK::parseKeySet($jwks);

                    if ($keys !== []) {
                        return $keys;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Unable to fetch the Firebase JWKS: '.$e->getMessage());
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get($certsUrl);

            if ($response->successful()) {
                $certs = $response->json();

                if (is_array($certs) && $certs !== []) {
                    $keys = [];

                    foreach ($certs as $kid => $certificate) {
                        if (! is_string($certificate) || $certificate === '') {
                            continue;
                        }

                        $keys[(string) $kid] = new Key($certificate, 'RS256');
                    }

                    return $keys;
                }
            }
        } catch (Throwable $e) {
            Log::warning(
                'Unable to fetch the Firebase certificates: '.$e->getMessage()
            );
        }

        Log::error('Unable to retrieve the Firebase public keys.');

        return [];
    }
}
