<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * GET /host/clients.ham — list connected clients. Only role='client',
     * so the host doesn't see themselves.
     */
    public function index(Request $request)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        $clients = Client::query()
            ->where('host_id', $host->host_id)
            ->where('role', 'client')
            ->with('invite')
            ->orderByDesc('created_at')
            ->get();

        return response()
            ->view('ham.host.clients', [
                'current_client' => $host,
                'clients' => $clients,
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * POST /host/clients/{id}/revoke — mark a client as revoked.
     * The target client's next request bounces to the "session expired"
     * HAM partial; their cookie is effectively dead.
     */
    public function revoke(Request $request, int $id)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        $client = Client::query()
            ->where('host_id', $host->host_id)
            ->where('role', 'client')
            ->findOrFail($id);

        $client->forceFill(['revoked_at' => now()])->save();

        return response()
            ->view('ham.host.partials._client_row', [
                'client' => $client,
            ])
            ->header('Content-Type', 'text/html');
    }
}
