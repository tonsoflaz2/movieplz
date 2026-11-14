<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invite;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

/**
 * The entry point every cold launch hits: `GET /main.ham`.
 *
 * Branches on the session:
 *   - no session        → onboarding (invite entry)
 *   - session = client  → full client home screen
 *   - session = host    → full host home screen
 *
 * Unlike sub-routes, this route always returns a full `<screen>` — it's
 * the only route the mobile shell calls outside of a swap context.
 *
 * The included home-body partials expect the same data shape as when
 * they're returned standalone from Client\HomeController::show and
 * Host\HomeController::show, so this controller duplicates their data
 * prep. Extract to a service class if this grows.
 */
class MainController extends Controller
{
    /**
     * Screens `?open=` may start on (a tapped notification's data.route),
     * per role; anything else opens home as usual.
     */
    private const OPENABLE = [
        'host' => ['/host/home.ham', '/host/requests.ham', '/library.ham', '/host/clients.ham'],
        'client' => ['/home.ham', '/requests.ham', '/library.ham'],
    ];

    public function index(Request $request)
    {
        $client = $this->resolveSessionClient($request);

        $screen = match (true) {
            $client === null => 'onboarding',
            $client->isHost() => 'host',
            default => 'client',
        };

        $open = (string) $request->query('open', '');
        $data = [
            'screen' => $screen,
            'current_client' => $client,
            // Set by AccountController after leaving or deleting a library.
            'goodbye' => $request->session()->get('goodbye'),
            'open' => in_array($open, self::OPENABLE[$screen] ?? [], true) ? $open : null,
        ];

        if ($screen === 'client') {
            $data += $this->clientHomeData($client);
        } elseif ($screen === 'host') {
            $data += $this->hostHomeData($client);
        }

        return response()
            ->view('ham.app', $data)
            ->header('Content-Type', 'text/html');
    }

    private function resolveSessionClient(Request $request): ?Client
    {
        $clientId = $request->session()->get('client_id');
        if (! $clientId) {
            return null;
        }

        $client = Client::query()->with('host')->find($clientId);
        if (! $client || $client->isRevoked()) {
            $request->session()->forget('client_id');
            return null;
        }

        // Another phone signed in as this person disconnected; this one
        // still is, so they're connected (and pushed) again.
        $client->forceFill(['last_seen_at' => now(), 'disconnected_at' => null])->save();
        return $client;
    }

    private function clientHomeData(Client $client): array
    {
        return [
            'recent_requests' => MediaRequest::query()
                ->where('client_id', $client->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'unread_fulfilled' => MediaRequest::query()
                ->where('client_id', $client->id)
                ->where('status', MediaRequest::STATUS_FULFILLED)
                ->whereNull('seen_by_client_at')
                ->count(),
        ];
    }

    private function hostHomeData(Client $host): array
    {
        return [
            'pending_count' => MediaRequest::query()
                ->where('host_id', $host->host_id)
                ->awaitingHost()
                ->count(),
            'latest_pending' => MediaRequest::query()
                ->where('host_id', $host->host_id)
                ->awaitingHost()
                ->with('client')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
            'library_total' => LibraryItem::query()->where('host_id', $host->host_id)->count(),
            'film_count' => LibraryItem::query()->where('host_id', $host->host_id)->where('type', 'film')->count(),
            'series_count' => LibraryItem::query()->where('host_id', $host->host_id)->where('type', 'series')->count(),
            'active_client_count' => Client::query()
                ->where('host_id', $host->host_id)
                ->where('role', 'client')
                ->whereNull('revoked_at')
                ->whereNull('disconnected_at')
                ->count(),
            'tbw' => \App\Support\Tbw::counts($host->host_id),
            'active_invite' => Invite::query()
                ->where('host_id', $host->host_id)
                ->whereNull('redeemed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first(),
        ];
    }
}
