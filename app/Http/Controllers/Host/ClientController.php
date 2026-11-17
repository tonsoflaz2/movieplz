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
        $client = $this->friend($request, $id);
        $client->forceFill(['revoked_at' => now()])->save();

        return $this->row($client);
    }

    /** GET /host/clients/{id}/rename.ham — the name field, filled in. */
    public function renameForm(Request $request, int $id)
    {
        $client = $this->friend($request, $id);

        return $this->renameView($client, $client->nameForHost());
    }

    /**
     * POST /host/clients/{id}/rename — the host's nickname for a friend,
     * which only the host sees. Blank (or the friend's own name) clears it.
     */
    public function rename(Request $request, int $id)
    {
        $client = $this->friend($request, $id);
        $name = trim(preg_replace('/\s+/u', ' ', (string) $request->input('nickname', '')));

        if (mb_strlen($name) > Client::NAME_MAX) {
            return $this->renameView($client, $name, 'Names can be at most '.Client::NAME_MAX.' characters.');
        }

        $client->forceFill(['host_nickname' => $name === '' || $name === $client->display_name ? null : $name])->save();

        return $this->row($client);
    }

    /** GET /host/clients/{id}/row.ham — the row again (Cancel). */
    public function rowView(Request $request, int $id)
    {
        return $this->row($this->friend($request, $id));
    }

    /** One of this host's friends (never the host's own row). */
    private function friend(Request $request, int $id): Client
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        return Client::query()
            ->where('host_id', $host->host_id)
            ->where('role', 'client')
            ->findOrFail($id);
    }

    private function row(Client $client)
    {
        return response()
            ->view('ham.host.partials._client_row', ['client' => $client])
            ->header('Content-Type', 'text/html');
    }

    private function renameView(Client $client, string $value, ?string $error = null)
    {
        return response()
            ->view('ham.host.partials._client_rename', ['client' => $client, 'value' => $value, 'error' => $error])
            ->header('Content-Type', 'text/html');
    }
}
