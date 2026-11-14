<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

class UnreadController extends Controller
{
    /**
     * GET /host/unread.ham — tiny polling partial for the pending
     * requests badge on the host home screen. Returns just the count
     * text, swapped every 30s into its containing box.
     */
    public function show(Request $request)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        $count = MediaRequest::query()
            ->where('host_id', $host->host_id)
            ->awaitingHost()
            ->count();

        return response()
            ->view('ham.host.partials._unread_count', [
                'count' => $count,
            ])
            ->header('Content-Type', 'text/html');
    }
}
