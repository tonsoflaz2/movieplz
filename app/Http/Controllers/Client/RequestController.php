<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MediaRequest;
use App\Services\PushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    /**
     * GET /requests/new.ham — the request form, partial.
     * Optional ?title= prefill when coming from the library screen.
     */
    public function create(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        return response()
            ->view('ham.client.request_form', [
                'current_client' => $client,
                'title' => (string) $request->query('title', ''),
                'year' => (string) $request->query('year', ''),
                'type' => (string) $request->query('type', ''),
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * POST /requests — create a request, return confirmation partial.
     */
    public function store(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        $data = [
            'title' => trim((string) $request->input('title', '')),
            'year' => $request->input('year'),
            'type' => $request->input('type'),
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'type' => ['nullable', 'in:film,series'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Tell us what you want to watch.',
        ]);

        if ($validator->fails()) {
            return response()
                ->view('ham.client.request_form', [
                    'current_client' => $client,
                    'error' => $validator->errors()->first(),
                    'title' => $data['title'],
                    'year' => (string) $data['year'],
                    'type' => (string) $data['type'],
                    'notes' => $data['notes'],
                ])
                ->header('Content-Type', 'text/html');
        }

        $mediaRequest = MediaRequest::create([
            'host_id' => $client->host_id,
            'client_id' => $client->id,
            'title' => $data['title'],
            'year' => $data['year'] !== null && $data['year'] !== '' ? (int) $data['year'] : null,
            'type' => in_array($data['type'], ['film', 'series'], true) ? $data['type'] : null,
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'status' => MediaRequest::STATUS_PENDING,
        ]);

        $hostClient = Client::query()
            ->where('host_id', $client->host_id)
            ->where('role', 'host')
            ->first();
        PushService::notify(
            $hostClient,
            'new-suggestion',
            'New suggestion',
            "{$client->display_name} suggested {$mediaRequest->title}",
            ['route' => '/host/requests.ham'],
        );

        return response()
            ->view('ham.client._request_confirmation', [
                'current_client' => $client,
                'media_request' => $mediaRequest,
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * GET /requests.ham — client's history grouped by status.
     */
    public function index(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        // Mark fulfilled requests as seen so the unread banner clears.
        MediaRequest::query()
            ->where('client_id', $client->id)
            ->where('status', MediaRequest::STATUS_FULFILLED)
            ->whereNull('seen_by_client_at')
            ->update(['seen_by_client_at' => now()]);

        $requests = MediaRequest::query()
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('status');

        return response()
            ->view('ham.client.requests', [
                'current_client' => $client,
                'up_next' => $requests->get(MediaRequest::STATUS_PENDING, collect())->whereNotNull('up_next_at'),
                'pending' => $requests->get(MediaRequest::STATUS_PENDING, collect())->whereNull('up_next_at'),
                'fulfilled' => $requests->get(MediaRequest::STATUS_FULFILLED, collect()),
                'declined' => $requests->get(MediaRequest::STATUS_DECLINED, collect()),
            ])
            ->header('Content-Type', 'text/html');
    }
}
