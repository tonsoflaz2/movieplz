<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Android notifications through Firebase Cloud Messaging's HTTP v1 API:
 * a service-account JWT (RS256) is exchanged for an OAuth access token,
 * cached for most of its hour, then each message is one POST.
 *
 * Messages are data-only (no "notification" block): the Hype Apps engine
 * draws the banner itself, with the app's icon, on its own channel
 * (hypeapps_push), and a tap opens data.route. A notification message
 * would be drawn by the system with a generic icon.
 */
class FcmSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** FCM's name for the last message it accepted (projects/…/messages/…), for the log. */
    public static ?string $lastMessageId = null;

    /**
     * Sends one notification. Returns 'sent', 'gone' (FCM says the token
     * is no longer valid: forget it) or 'failed'.
     */
    public static function send(string $token, string $title, string $body, array $data = []): string
    {
        self::$lastMessageId = null;
        $account = self::account();
        if (! $account) {
            Log::warning('FCM push skipped: FCM_CREDENTIALS is not set or not a service-account key');

            return 'failed';
        }

        try {
            $response = Http::withToken(self::accessToken($account))
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                    'message' => [
                        'token' => $token,
                        // FCM's data values must be strings.
                        'data' => array_map('strval', array_merge($data, ['title' => $title, 'body' => $body])),
                        // High priority wakes the app to draw the banner.
                        'android' => ['priority' => 'high'],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('FCM push failed', ['error' => $e->getMessage()]);

            return 'failed';
        }

        if ($response->successful()) {
            self::$lastMessageId = $response->json('name');

            return 'sent';
        }

        $status = $response->json('error.status');
        $code = collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
        Log::warning('FCM rejected push', ['status' => $response->status(), 'error' => $code ?? $status, 'token' => substr($token, 0, 8).'...']);

        // The token is no longer valid (app uninstalled, token rotated) or was
        // never an FCM token.
        if ($code === 'UNREGISTERED' || $response->status() === 404
            || ($status === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'registration token'))) {
            return 'gone';
        }

        return 'failed';
    }

    /** The service account from FCM_CREDENTIALS (base64 JSON or a file path), or null. */
    private static function account(): ?array
    {
        $raw = (string) config('push.fcm.credentials');
        if ($raw === '') {
            return null;
        }
        $json = is_file($raw) ? file_get_contents($raw) : base64_decode($raw, true);
        $account = $json ? json_decode($json, true) : null;

        return is_array($account) && isset($account['client_email'], $account['private_key'], $account['project_id'])
            ? $account
            : null;
    }

    private static function accessToken(array $account): string
    {
        return Cache::remember('fcm.access_token.'.md5($account['client_email']), now()->addMinutes(55), function () use ($account) {
            $now = time();
            $unsigned = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                .'.'.self::base64url(json_encode([
                    'iss' => $account['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => self::TOKEN_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));
            if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign with the FCM service-account key');
            }

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.self::base64url($signature),
            ]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new \RuntimeException('Google refused the FCM service-account key ('.$response->status().')');
            }

            return $response->json('access_token');
        });
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
