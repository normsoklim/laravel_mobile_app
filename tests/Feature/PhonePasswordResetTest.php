<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\FirebasePhoneTokenVerifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The tests run against the dedicated "tinh_shop_test" PostgreSQL
 * database (see sync_test_database.php). Every test is wrapped in a
 * transaction that is rolled back afterwards, so the database is never
 * polluted.
 */
class PhonePasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = '/api/v1/auth/password/phone/reset';

    private function identity(array $overrides = []): array
    {
        return array_merge([
            'uid' => 'firebase-test-uid',
            'phone_number' => '+85512345678',
            'auth_time' => now()->timestamp - 30,
            'sign_in_provider' => 'phone',
        ], $overrides);
    }

    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Sok Lim',
            'email' => 'sok.lim@example.com',
            'email_verified_at' => now(),
            'phone' => '+85512345678',
            'password' => 'OldPassword123',
            'is_active' => true,
        ], $attributes));
    }

    private function mockVerifier(array $identity, int $times = 1): void
    {
        $this->mock(
            FirebasePhoneTokenVerifier::class,
            function (MockInterface $mock) use ($identity, $times) {
                $mock->shouldReceive('verify')
                    ->times($times)
                    ->andReturn($identity);
            }
        );
    }

    public function test_verified_phone_can_reset_password(): void
    {
        $user = $this->createUser();

        $this->mockVerifier($this->identity());

        $response = $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $response->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertFalse(Hash::check('OldPassword123', $user->password));

        $this->assertDatabaseHas(
            'consumed_phone_reset_authentications',
            ['firebase_uid' => 'firebase-test-uid']
        );
    }

    public function test_existing_sessions_are_revoked_after_the_reset(): void
    {
        $user = $this->createUser();
        $user->createToken('auth_token', ['*']);

        $this->assertCount(1, $user->tokens);

        $this->mockVerifier($this->identity());

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertOk();

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_missing_bearer_token_is_rejected(): void
    {
        $this->createUser();

        $this
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->createUser();

        $this->mock(
            FirebasePhoneTokenVerifier::class,
            function (MockInterface $mock) {
                $mock->shouldReceive('verify')
                    ->once()
                    ->andThrow(new AuthenticationException('Invalid or expired verification.'));
            }
        );

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertUnauthorized();
    }

    public function test_old_phone_authentication_is_rejected(): void
    {
        $this->createUser();

        $this->mockVerifier($this->identity([
            'auth_time' => now()->timestamp - 601,
        ]));

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertUnauthorized();
    }

    public function test_google_provider_is_not_accepted(): void
    {
        $this->createUser();

        $this->mockVerifier($this->identity([
            'sign_in_provider' => 'google.com',
        ]));

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertUnauthorized();
    }

    public function test_unknown_phone_cannot_reset_a_password(): void
    {
        $this->createUser(['phone' => '+85599999999']);

        $this->mockVerifier($this->identity());

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing(
            'consumed_phone_reset_authentications',
            ['firebase_uid' => 'firebase-test-uid']
        );
    }

    public function test_phone_stored_in_the_local_format_is_matched(): void
    {
        $user = $this->createUser(['phone' => '012345678']);

        $this->mockVerifier($this->identity());

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        // The number is stored in the canonical E.164 format after a reset.
        $this->assertSame('+85512345678', $user->phone);
    }

    public function test_same_phone_authentication_cannot_be_reused(): void
    {
        $this->createUser();

        $this->mockVerifier($this->identity(), 2);

        $payload = [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ];

        $first = $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, $payload);

        $first->assertOk();

        $second = $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, $payload);

        $second->assertStatus(409);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->createUser();

        // The payload is validated before the token is verified, so the
        // Firebase verifier is never called for an invalid password.
        $this->mock(
            FirebasePhoneTokenVerifier::class,
            function (MockInterface $mock) {
                $mock->shouldNotReceive('verify');
            }
        );

        $this
            ->withHeader('Authorization', 'Bearer test-firebase-token')
            ->postJson(self::ENDPOINT, [
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422);
    }
}
