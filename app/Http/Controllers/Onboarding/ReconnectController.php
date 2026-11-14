<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\ReconnectCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ReconnectController extends Controller
{
    /**
     * POST /onboarding/reconnect
     *
     * Signs this phone back in as the person a reconnect code belongs to:
     * a host to their library, a friend to theirs with their suggestions.
     * The same code works on a new phone. A revoked friend's code doesn't
     * work, and a deleted library's codes are gone with it.
     *
     * Always HTTP 200 (the shell drops non-2xx bodies): the form again
     * with an error, or the welcome-back partial. Five tries a minute per
     * address, said on the form (the throttle middleware's error would
     * replace the form with a generic message).
     */
    public function submit(Request $request)
    {
        $raw = (string) $request->input('code', '');

        $key = 'reconnect:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->formWithError('Too many tries. Wait a minute, then try again.', $raw);
        }
        RateLimiter::hit($key, 60);

        if (trim($raw) === '') {
            return $this->formWithError('Enter your reconnect code.', $raw);
        }

        $client = Client::findByReconnectCode($raw);
        if (! $client || ! $client->host) {
            return $this->formWithError('That code isn\'t recognized. Check it and try again: it looks like '
                .'ABCD-EFGH-JKMN.', $raw);
        }

        $client->forceFill([
            'disconnected_at' => null,
            'last_seen_at' => now(),
        ])->save();

        $request->session()->regenerate();
        $request->session()->put('client_id', $client->id);

        return response()
            ->view('ham.onboarding._reconnect_success', ['current_client' => $client])
            ->header('Content-Type', 'text/html');
    }

    private function formWithError(string $message, string $previous)
    {
        return response()
            ->view('ham.onboarding.reconnect', [
                'error' => $message,
                'code' => ReconnectCode::normalize($previous) !== '' ? ReconnectCode::format($previous) : '',
            ])
            ->header('Content-Type', 'text/html');
    }
}
