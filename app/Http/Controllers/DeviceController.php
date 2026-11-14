<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * POST /api/devices — upsert push token for the current session client.
     *
     * Best-effort: if the session has a valid, non-revoked client, save
     * the token. If not (no session, revoked, stale ID), silently return
     * 200 so the framework doesn't retry or show errors.
     */
    public function register(Request $request)
    {
        Log::info('Device register hit', [
            'token' => substr((string) $request->input('token', ''), 0, 12).'...',
            'platform' => $request->input('platform'),
            'client_id' => $request->session()->get('client_id'),
        ]);

        $clientId = $request->session()->get('client_id');
        if (! $clientId) {
            return response('', 200);
        }

        $client = Client::query()
            ->whereNull('revoked_at')
            ->find($clientId);

        if (! $client) {
            $request->session()->forget('client_id');
            return response('', 200);
        }

        $token = (string) ($request->input('token') ?? $request->json('token') ?? '');
        $platform = (string) ($request->input('platform') ?? $request->json('platform') ?? 'ios');
        // The engine sends 'ios' or 'android'; anything else is treated as iOS, as before.
        $platform = $platform === 'android' ? 'android' : 'ios';
        // An iOS build's aps-environment, when the engine sends it: which
        // APNs gateway the token belongs to. Without it, the first push
        // finds out (PushService::sendApns).
        $environment = (string) ($request->input('apns_environment') ?? $request->json('apns_environment') ?? '');
        $environment = match ($environment) {
            'production' => 'production',
            'development', 'sandbox' => 'development',
            default => null,
        };

        Log::info('Device register saving', ['token_len' => strlen($token), 'platform' => $platform, 'client' => $client->id]);

        if ($token !== '') {
            // One phone, one person: the token leaves every other row (any
            // library), so this phone is notified only for whoever is
            // signed in on it now, not for an identity it used before.
            $others = Client::query()->where('push_token', $token)->where('id', '!=', $client->id)->pluck('id');
            if ($others->isNotEmpty()) {
                Client::query()->whereIn('id', $others)->update(['push_token' => null, 'push_environment' => null]);
                Log::info('Push token moved', ['from_clients' => $others->all(), 'to_client' => $client->id]);
            }

            $client->forceFill([
                'push_token' => $token,
                'push_platform' => $platform,
                // A new token starts unknown unless the device says.
                'push_environment' => $platform === 'ios'
                    ? ($environment ?? ($client->push_token === $token ? $client->push_environment : null))
                    : null,
            ])->save();
        }

        return response('', 200);
    }
}
