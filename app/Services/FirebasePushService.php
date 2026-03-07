<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    private const FCM_SEND_URL_TEMPLATE = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CACHE_KEY_ACCESS_TOKEN = 'firebase_fcm_access_token';

    private const TOKEN_TTL_SECONDS = 3500;

    /**
     * Send push notification payload to multiple FCM tokens via HTTP v1 API.
     * Each token is sent one request; failures are logged and do not stop the batch.
     *
     * @param  array<int, string>  $tokens  FCM registration tokens
     * @param  array{title: string, body: string, sound: string, url: string}  $payload
     */
    public function sendToTokens(array $tokens, array $payload): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if ($tokens === []) {
            return;
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            Log::warning('Firebase push skipped: no access token');

            return;
        }

        $projectId = config('firebase.project_id');
        if (! is_string($projectId) || $projectId === '') {
            Log::warning('Firebase push skipped: FIREBASE_PROJECT_ID not set');

            return;
        }

        $url = sprintf(self::FCM_SEND_URL_TEMPLATE, $projectId);
        $headers = [
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/json',
        ];

        foreach ($tokens as $token) {
            $body = [
                'message' => [
                    'token' => $token,
                    'data' => [
                        'title' => $payload['title'] ?? '',
                        'body' => $payload['body'] ?? '',
                        'sound' => $payload['sound'] ?? 'default',
                        'url' => $payload['url'] ?? '/',
                    ],
                ],
            ];

            $response = Http::withHeaders($headers)->post($url, $body);

            if (! $response->successful()) {
                Log::warning('FCM send failed', [
                    'token_preview' => substr($token, 0, 20).'...',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }
    }

    /**
     * Obtain OAuth2 access token for FCM (cached).
     */
    private function getAccessToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY_ACCESS_TOKEN);
        if (is_string($cached)) {
            return $cached;
        }

        $jwt = $this->createJwt();
        if ($jwt === null) {
            return null;
        }

        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::warning('Firebase OAuth token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken)) {
            return null;
        }

        Cache::put(self::CACHE_KEY_ACCESS_TOKEN, $accessToken, self::TOKEN_TTL_SECONDS);

        return $accessToken;
    }

    private function createJwt(): ?string
    {
        $clientEmail = config('firebase.client_email');
        $privateKey = config('firebase.private_key');

        if (! is_string($clientEmail) || $clientEmail === '' || ! is_string($privateKey) || $privateKey === '') {
            return null;
        }

        $privateKey = str_replace(['\\n', "\n"], "\n", $privateKey);
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ];

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signatureInput = $headerB64.'.'.$payloadB64;

        $signature = '';
        $signed = openssl_sign(
            $signatureInput,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            Log::warning('Firebase JWT signing failed');

            return null;
        }

        $signatureB64 = $this->base64UrlEncode($signature);

        return $signatureInput.'.'.$signatureB64;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
