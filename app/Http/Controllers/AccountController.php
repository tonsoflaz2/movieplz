<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Disconnecting a phone, and deleting your account from inside the app
 * (App Store guideline 5.1.1(v)).
 *
 * Disconnecting keeps everything: a host's library, friends and
 * suggestions, or a friend's suggestions. The person comes back with
 * their reconnect code (welcome screen → Reconnect), on this phone or
 * another. Deleting is for good: a friend leaves a library; a host
 * deletes theirs.
 *
 * All of them end the session and restart the app on the welcome screen,
 * which says what happened.
 */
class AccountController extends Controller
{
    /**
     * GET /account/disconnect.ham — the second step of "Disconnect this
     * phone": the person's reconnect code (made now if they've never asked),
     * to copy before they confirm.
     */
    public function disconnectPanel(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');

        return response()
            ->view('ham.partials._disconnect_confirm', [
                'code' => $client->reconnectCode(),
                'current_client' => $client,
            ])
            ->header('Content-Type', 'text/html');
    }

    /**
     * POST /account/disconnect — this phone stops being signed in. Nothing
     * is deleted; no pushes go to the person until they reconnect.
     */
    public function disconnect(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');
        $code = $client->reconnectCode();
        $client->forceFill(['disconnected_at' => now()])->save();

        $kept = $client->isHost()
            ? 'Your library, friends and suggestions are kept.'
            : 'Your suggestions are kept.';

        return $this->goodbye(
            $request,
            "This phone is disconnected. {$kept} To come back, tap Reconnect and enter {$code}.",
            'disconnect-actions',
        );
    }

    /**
     * POST /account/leave — a friend leaves the library. Their requests
     * go with them.
     */
    public function leave(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');
        $library = $client->host?->name ?? 'the library';

        $client->delete();

        return $this->goodbye($request, "You've left {$library}. Your name and suggestions are deleted.");
    }

    /**
     * POST /host/delete-library — a host deletes their library: its
     * titles, friends, requests and invite codes.
     */
    public function deleteLibrary(Request $request)
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_client');
        $host = $client->host;

        DB::transaction(function () use ($host) {
            Invite::where('host_id', $host->id)->delete();
            $host->delete(); // cascades to clients, requests and library items
        });

        return $this->goodbye($request, 'Your library is deleted, with its titles, friends and suggestions.');
    }

    private function goodbye(Request $request, string $message, string $target = 'account-actions')
    {
        $request->session()->forget('client_id');
        $request->session()->regenerate();
        // Shown once, by the welcome screen the app restarts on.
        $request->session()->flash('goodbye', $message);

        return response()
            ->view('ham.partials.goodbye', ['message' => $message, 'target' => $target])
            ->header('Content-Type', 'text/html');
    }
}
