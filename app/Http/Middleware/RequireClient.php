<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current session's client and attaches it to the request.
 *
 * If the session has no client, or the client has been revoked, returns
 * the HAM error partial at HTTP 200 telling the user their session has
 * expired. The mobile shell's hypermedia layer can't follow redirects,
 * so the partial carries a "Start over" button (app:/app.ham) that
 * reboots the app into /app.ham and routes them through onboarding.
 */
class RequireClient
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
