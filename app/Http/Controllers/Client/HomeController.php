<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MediaRequest;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * GET /home.ham — returns the client home body as a partial, to be
     * swapped into #main either on cold-launch (via ham/main.blade.php
     * including this view) or on bottom-nav tap (via the swap action).
     */
    public function show(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        $recentRequests = MediaRequest::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $unreadFulfilled = MediaRequest::query()
            ->where('client_id', $client->id)
            ->where('status', MediaRequest::STATUS_FULFILLED)
            ->whereNull('seen_by_client_at')
            ->count();

        return response()
            ->view('ham.client.home_body', [
                'current_client' => $client,
                'recent_requests' => $recentRequests,
                'unread_fulfilled' => $unreadFulfilled,
            ])
            ->header('Content-Type', 'text/html');
    }
}
