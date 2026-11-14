<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

class UnreadController extends Controller
{
    /**
     * GET /home/unread.ham — tiny polling partial for the fulfilled-
     * but-unseen banner on the client home. Returns the count text,
     * swapped every 30s into its containing box.
     */
    public function show(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        $count = MediaRequest::query()
            ->where('client_id', $client->id)
            ->where('status', MediaRequest::STATUS_FULFILLED)
            ->whereNull('seen_by_client_at')
            ->count();

        return response()
            ->view('ham.client.partials._unread_count', [
                'count' => $count,
            ])
            ->header('Content-Type', 'text/html');
    }
}
