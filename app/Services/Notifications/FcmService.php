<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FcmService
{
    private ?GoogleClient $client = null;

    private ?string $projectId = null;

    /**
     * Send a push notification to every device token registered for a user.
     *
     * @param  array{title: string, body: string}  $notification
     * @param  array<string, string>  $data  Extra key/value payload (order id, type…)
     * @return array{devices_found: int, sent: int, failed: int}
     */
    public function sendToUser(int $userId, array $notification, array $data = []): array
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->all();

        return $this->sendToTokens($tokens, $notification, $data);
    }

    /**
     * Send a push notification to a list of FCM device tokens.
     *
     * Tokens that FCM reports as invalid/unregistered are removed so the
     * table does not accumulate dead entries.
     *
     * @param  array<int, string>  $tokens
     * @param  array{title: string, body: string}  $notification
     * @param  array<string, scalar|null>  $data
     * @return array{devices_found: int, sent: int, failed: int}
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        $result = ['devices_found' => count($tokens), 'sent' => 0, 'failed' => 0];

        if (empty($tokens)) {
            return $result;
        }

        if (! $this->isConfigured()) {
            Log::warning('FCM send skipped: service-account credentials are not configured.');

            $result['failed'] = $result['devices_found'];

            return $result;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            $result['failed'] = $result['devices_found'];

            return $result;
        }

        foreach ($tokens as $token) {
            $res = $this->deliver($accessToken, 'token', $token, $notification, $data);

            if ($res['ok']) {
                $result['sent']++;

                // 404 = the token is no longer registered with FCM; drop it.
                if ($res['status'] === 404) {
                    DeviceToken::where('token', $token)->delete();
                }
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send a push notification to every device subscribed to a Firebase
     * topic (e.g. "promotions", "new_products"). No device tokens are
     * needed — Firebase fans the message out to all subscribers.
     *
     * Topics are public: never send private order/payment data through
     * them. Use sendToUser()/sendToTokens() for user-specific messages.
     *
     * @param  array{title: string, body: string}  $notification
     * @param  array<string, scalar|null>  $data
     * @return string|null The FCM message id on success, null on failure.
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): ?string
    {
        if (! $this->isConfigured()) {
            Log::warning('FCM topic send skipped: service-account credentials are not configured.');

            return null;
        }

        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return null;
        }

        $res = $this->deliver($accessToken, 'topic', $topic, $notification, $data);

        return $res['ok'] ? $res['name'] : null;
    }

    /**
     * Build and POST a single FCM message. The delivery target is either
     * a device "token" or a "topic" — both share the same notification,
     * data, android and apns blocks.
     *
     * @param  array{title: string, body: string}  $notification
     * @param  array<string, scalar|null>  $data
     * @return array{ok: bool, name: string|null, status: int}
     */
    private function deliver(
        string $accessToken,
        string $target,
        string $targetValue,
        array $notification,
        array $data
    ): array {
        $payload = [
            'message' => [
                $target => $targetValue,
                'notification' => [
                    'title' => (string) ($notification['title'] ?? ''),
                    'body' => (string) ($notification['body'] ?? ''),
                ],
                // Data payload is always delivered (foreground + background)
                // and is what the Flutter app reads to route the tap.
                'data' => $this->formatData($data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, $payload);

            if ($response->successful()) {
                return ['ok' => true, 'name' => $response->json('name'), 'status' => $response->status()];
            }

            Log::warning('FCM send failed', [
                'target' => $target,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return ['ok' => false, 'name' => null, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::error('FCM send exception: '.$e->getMessage());

            return ['ok' => false, 'name' => null, 'status' => 0];
        }
    }

    /**
     * FCM requires every data value to be a string. Scalars are cast,
     * null becomes an empty string, and anything else is rejected so a
     * bad payload fails loudly instead of being silently mangled.
     *
     * @param  array<string, scalar|null>  $data
     * @return array<string, string>
     */
    private function formatData(array $data): array
    {
        $formatted = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                $formatted[$key] = '';

                continue;
            }

            if (! is_scalar($value)) {
                throw new \InvalidArgumentException('FCM data values must be scalar.');
            }

            $formatted[$key] = (string) $value;
        }

        return $formatted;
    }

    /**
     * Whether the service-account credentials are present and loadable.
     */
    public function isConfigured(): bool
    {
        return $this->getClient() !== null;
    }

    /**
     * Build (once) the Google client from the service-account file and
     * remember the project id used in the FCM endpoint.
     */
    private function getClient(): ?GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $path = config('firebase.credentials');
        $absolute = $path && file_exists($path)
            ? $path
            : base_path($path ?? '');

        if (! $path || ! file_exists($absolute)) {
            Log::error('Firebase service-account file not found.', ['path' => $absolute]);

            return null;
        }

        try {
            // Google\Client::getConfig('project_id') does not expose the
            // service-account project id, so read it straight from the JSON.
            $credentials = json_decode((string) file_get_contents($absolute), true);
            $projectId = $credentials['project_id'] ?? null;

            if (empty($projectId)) {
                Log::error('Firebase service-account is missing a project_id.', ['path' => $absolute]);

                return null;
            }

            $client = new GoogleClient();
            $client->setAuthConfig($absolute);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $this->client = $client;
            $this->projectId = $projectId;

            return $this->client;
        } catch (\Throwable $e) {
            Log::error('Failed to initialise Firebase client: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Fetch (and cache for its lifetime) an OAuth access token.
     */
    private function getAccessToken(): ?string
    {
        $client = $this->getClient();

        if (! $client) {
            return null;
        }

        try {
            $token = $client->fetchAccessTokenWithAssertion();

            if (isset($token['error'])) {
                Log::error('FCM access token error', ['error' => $token['error']]);

                return null;
            }

            return $token['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('FCM access token exception: '.$e->getMessage());

            return null;
        }
    }
}
