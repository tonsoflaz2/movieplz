<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current session's client regardless of role (host or client).
 *
 * Same as RequireClient but doesn't enforce role — any valid, non-revoked
 * client passes. Used for routes that both hosts and clients may access
 * (e.g. the shared library).
 */
class RequireAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->session()->get('client_id');

        if (! $clientId) {
            return $this->sessionExpired();
        }

        $client = Client::query()->with('host')->find($clientId);

        if (! $client || $client->isRevoked()) {
            $request->session()->forget('client_id');
            $request->session()->regenerate();
            return $this->sessionExpired();
        }

        $client->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('current_client', $client);

        return $next($request);
    }

    private function sessionExpired(): Response
    {
        return response()
            ->view('ham.partials.error', [
                'message' => 'Your session has ended.',
                'restart' => true,
            ])
            ->header('Content-Type', 'text/html');
    }
}
