<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invite;
use App\Models\LibraryItem;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * GET /host/home.ham — host dashboard body, partial into #main.
     */
    public function show(Request $request)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        $pendingCount = MediaRequest::query()
            ->where('host_id', $host->host_id)
            ->awaitingHost()
            ->count();

        $latestPending = MediaRequest::query()
            ->where('host_id', $host->host_id)
            ->awaitingHost()
            ->with('client')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $libraryTotal = LibraryItem::query()->where('host_id', $host->host_id)->count();
        $filmCount = LibraryItem::query()->where('host_id', $host->host_id)->where('type', 'film')->count();
        $seriesCount = LibraryItem::query()->where('host_id', $host->host_id)->where('type', 'series')->count();

        $activeClientCount = Client::query()
            ->where('host_id', $host->host_id)
            ->where('role', 'client')
            ->whereNull('revoked_at')
            ->whereNull('disconnected_at')
            ->count();

        $activeInvite = Invite::query()
            ->where('host_id', $host->host_id)
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return response()
            ->view('ham.host.home_body', [
                'current_client' => $host,
                'pending_count' => $pendingCount,
                'latest_pending' => $latestPending,
                'library_total' => $libraryTotal,
                'film_count' => $filmCount,
                'series_count' => $seriesCount,
                'active_client_count' => $activeClientCount,
                'active_invite' => $activeInvite,
                'tbw' => \App\Support\Tbw::counts($host->host_id),
            ])
            ->header('Content-Type', 'text/html');
    }
}
