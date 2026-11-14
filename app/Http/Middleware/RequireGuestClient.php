<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inverse of RequireClient — rejects requests that already have an
 * authenticated session. Used on onboarding routes to prevent an authed
 * user from accidentally re-redeeming an invite.
 *
 * If the session points to a still-valid client, render the main
 * screen body so the user sees their home instead of the invite form.
 */
class RequireGuestClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->session()->get('client_id');

        if ($clientId) {
            $client = Client::query()->find($clientId);
            if ($client && ! $client->isRevoked()) {
                return response()
                    ->view($client->isHost() ? 'ham.host.home_body' : 'ham.client.home_body', [
                        'current_client' => $client,
                    ])
                    ->header('Content-Type', 'text/html');
            }
            // Stale session — clear it and fall through to onboarding.
            $request->session()->forget('client_id');
        }

        return $next($request);
    }
}
