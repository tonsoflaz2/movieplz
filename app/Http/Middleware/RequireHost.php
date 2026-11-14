<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Like RequireClient, but also enforces role='host'. A non-host client
 * hitting a /host/* route gets a 200 HAM error ("Not authorized").
 */
class RequireHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->session()->get('client_id');

        if (! $clientId) {
            return $this->unauthorized('Your session has ended.', restart: true);
        }

        $client = Client::query()->with('host')->find($clientId);

        if (! $client || $client->isRevoked()) {
            $request->session()->forget('client_id');
            $request->session()->regenerate();
            return $this->unauthorized('Your session has ended.', restart: true);
        }

        if (! $client->isHost()) {
            return $this->unauthorized('Not authorized.');
        }

        $client->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('current_client', $client);
        $request->attributes->set('current_host', $client);

        return $next($request);
    }

    private function unauthorized(string $message, bool $restart = false): Response
    {
        return response()
            ->view('ham.partials.error', ['message' => $message, 'restart' => $restart])
            ->header('Content-Type', 'text/html');
    }
}
