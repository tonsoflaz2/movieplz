<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;

class PushService
{
    /**
     * Tell one person about [event] ('up-for-it', 'saw-it', 'added', 'declined', 'new-suggestion'):
     * pushed when they can be (a token, connected, not revoked). Either way
     * it's one INFO line in the log, so "why didn't I get it?" has an
     * answer: skipped and why, or what Apple or Firebase said.
     */
    public static function notify(?Client $client, string $event, string $title, string $body, array $data = []): void
    {
        if (! $client?->canBePushed()) {
            Log::info('Push skipped', [
                'client' => $client?->id,
                'event' => $event,
                'reason' => match (true) {
                    $client === null => 'no such client',
                    ! $client->push_token => 'no token',
                    $client->isRevoked() => 'revoked',
                    default => 'disconnected',
                },
            ]);

            return;
        }

        self::send($client->push_token, $client->push_platform ?? 'ios', $title, $body, $data,
            ['client' => $client->id, 'event' => $event]);
    }

    /**
     * One notification to one device: Apple's push service for iOS, Firebase
     * for Android. A token the service says is gone is cleared from its
     * client, so it isn't tried again. Logged at INFO with [context] (client,
     * event), the outcome and Apple's apns-id or Firebase's message name;
     * never the token.
     */
    public static function send(string $token, string $platform, string $title, string $body, array $data = [], array $context = []): void
    {
        if ($platform === 'android') {
            $result = FcmSender::send($token, $title, $body, $data);
            Log::info('Push', $context + ['platform' => 'android', 'result' => $result, 'message_id' => FcmSender::$lastMessageId]);
            if ($result === 'gone') {
                self::forget($token);
            }

            return;
        }

        $outcome = self::sendApns($token, $title, $body, $data);
        Log::info('Push', $context + ['platform' => 'ios'] + $outcome);
    }

    /** The device is gone (uninstalled, or its token replaced): stop pushing to it. */
    private static function forget(string $token): void
    {
        Client::query()->where('push_token', $token)->update(['push_token' => null]);
    }

    /**
     * Stands in for the HTTP/2 call to Apple in tests: given the token,
     * payload and environment ('production' or 'development'), returns
     * [status, body] or [status, body, apns-id]. Null: the real call.
     */
    public static ?\Closure $apnsTransport = null;

    /**
     * An App Store or TestFlight build's token works only on Apple's
     * production gateway, a development build's only on the sandbox. Each
     * token's environment is stored with its client: sent by the device
     * when it registers, or learnt here. Unknown, production is tried first
     * (that's what users have); "BadDeviceToken" there means the token is
     * the other kind, so the other gateway is tried and remembered.
     */
    /** @return array{result: string, environment: ?string, status: ?int, apns_id: ?string} */
    private static function sendApns(string $token, string $title, string $body, array $data): array
    {
        $payload = json_encode([
            'aps' => [
                // A title-only notification leaves the body out.
                'alert' => ['title' => $title] + ($body === '' ? [] : ['body' => $body]),
                'sound' => 'default',
            ],
            'data' => $data,
        ]);

        $known = Client::query()->where('push_token', $token)->value('push_environment');
        $first = $known === 'development' ? 'development' : 'production';
        $tries = [$first, $first === 'production' ? 'development' : 'production'];

        $outcome = ['result' => 'failed', 'environment' => null, 'status' => null, 'apns_id' => null];
        foreach ($tries as $environment) {
            [$result, $status, $apnsId] = self::postApns($token, $payload, $environment);
            $outcome = ['result' => $result, 'environment' => $environment, 'status' => $status, 'apns_id' => $apnsId];
            if ($result === 'sent') {
                if ($environment !== $known) {
                    Client::query()->where('push_token', $token)->update(['push_environment' => $environment]);
                }

                return $outcome;
            }
            if ($result !== 'wrong-environment') {
                return $outcome;
            }
        }

        // Neither gateway knows the token.
        Log::warning('APNs: token unknown in production and development', ['token' => substr($token, 0, 8).'...']);
        self::forget($token);

        return ['result' => 'gone'] + $outcome;
    }

    /**
     * One push to one gateway: ['sent' | 'wrong-environment' | 'gone' | 'failed',
     * the HTTP status, Apple's apns-id].
     *
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private static function postApns(string $token, string $payload, string $environment): array
    {
        $httpCode = null;
        $apnsId = null;
        try {
            $error = '';
            if (self::$apnsTransport) {
                $answer = (self::$apnsTransport)($token, $payload, $environment);
                [$httpCode, $response] = $answer;
                $apnsId = $answer[2] ?? null;
            } else {
                $jwt = self::mintJwt();
                $bundleId = config('apns.bundle_id');
                $host = $environment === 'production'
                    ? 'api.push.apple.com'
                    : 'api.sandbox.push.apple.com';

                $ch = curl_init("https://{$host}/3/device/{$token}");
                curl_setopt_array($ch, [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        "authorization: bearer {$jwt}",
                        "apns-topic: {$bundleId}",
                        "apns-push-type: alert",
                        'content-type: application/json',
                    ],
                    // Apple's id for the notification, in the log.
                    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$apnsId) {
                        if (stripos($line, 'apns-id:') === 0) {
                            $apnsId = trim(substr($line, 8));
                        }

                        return strlen($line);
                    },
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
            }

            if ($error) {
                Log::warning('APNs curl error', ['error' => $error, 'token' => substr($token, 0, 8).'...']);

                return ['failed', null, null];
            }
            if ($httpCode === 200) {
                return ['sent', 200, $apnsId];
            }
            $reason = json_decode((string) $response, true)['reason'] ?? '';
            // A token from the other gateway's kind of build.
            if ($httpCode === 400 && $reason === 'BadDeviceToken') {
                return ['wrong-environment', 400, $apnsId];
            }
            Log::warning('APNs rejected push', ['status' => $httpCode, 'response' => $response, 'environment' => $environment, 'token' => substr($token, 0, 8).'...', 'payload' => $payload]);
            // 410: the token is no longer active for the topic.
            if ($httpCode === 410) {
                self::forget($token);

                return ['gone', 410, $apnsId];
            }

            return ['failed', $httpCode, $apnsId];
        } catch (\Throwable $e) {
            Log::warning('APNs push failed', ['error' => $e->getMessage()]);

            return ['failed', $httpCode, $apnsId];
        }
    }

    private static function mintJwt(): string
    {
        $keyId = config('apns.key_id');
        $teamId = config('apns.team_id');
        $keyPath = base_path(config('apns.key_path'));

        $header = self::base64url(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = self::base64url(json_encode(['iss' => $teamId, 'iat' => time()]));
        $unsigned = "{$header}.{$claims}";

        $key = openssl_pkey_get_private('file://'.$keyPath);
        if (! $key) {
            throw new \RuntimeException('Failed to load APNs .p8 key from '.$keyPath);
        }

        openssl_sign($unsigned, $der, $key, OPENSSL_ALGO_SHA256);

        // Convert DER-encoded signature to raw r||s (64 bytes) for ES256
        $sig = self::derToRaw($der);

        return $unsigned.'.'.self::base64url($sig);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert a DER-encoded ECDSA signature to the raw 64-byte r||s
     * format that JWS ES256 requires.
     */
    private static function derToRaw(string $der): string
    {
        $offset = 2;

        // r
        $rLen = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;

        // s
        $sLen = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLen);

        // Pad/trim to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }
}
