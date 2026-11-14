<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NameController extends Controller
{
    /**
     * POST /onboarding/name
     *
     * Expects the client to have a `pending_invite_id` in session
     * (set by InviteController::redeem). Creates the Client row,
     * burns the invite, stores the new client_id in session, and
     * returns the appropriate home body. Laravel's session cookie
     * ride-along is what persists the auth on the mobile shell.
     */
    public function submit(Request $request)
    {
        $pendingInviteId = $request->session()->get('pending_invite_id');
        if (! $pendingInviteId) {
            return response()
                ->view('ham.onboarding.join', [
                    'error' => 'Your invite session expired. Enter your code again.',
                    'code' => '',
                ])
                ->header('Content-Type', 'text/html');
        }

        $rawName = (string) $request->input('display_name', '');
        $name = trim($rawName);

        $validator = Validator::make(['display_name' => $name], [
            'display_name' => ['required', 'string', 'min:1', 'max:40'],
        ], [
            'display_name.required' => 'Enter your name so the host knows who you are.',
            'display_name.max' => 'Names can be at most 40 characters.',
        ]);

        if ($validator->fails()) {
            $invite = Invite::find($pendingInviteId);
            return response()
                ->view('ham.onboarding.name', [
                    'invite' => $invite,
                    'error' => $validator->errors()->first('display_name'),
                    'display_name' => $rawName,
                ])
                ->header('Content-Type', 'text/html');
        }

        /** @var Client $client */
        $client = DB::transaction(function () use ($pendingInviteId, $name) {
            $invite = Invite::query()->lockForUpdate()->find($pendingInviteId);
            if (! $invite || ! $invite->isUsable()) {
                throw new \RuntimeException('Invite no longer usable.');
            }

            $host = null;

            if ($invite->grants_host) {
                $existingHost = Client::query()->where('role', 'host')->exists();
                if ($existingHost) {
                    throw new \RuntimeException('A host has already been claimed.');
                }

                $host = Host::create([
                    'name' => $name."'s library",
                ]);

                $invite->host_id = $host->id;
                $invite->save();
            } else {
                $host = $invite->host;
                if (! $host) {
                    throw new \RuntimeException('Invite is not tied to a host.');
                }
            }

            $client = Client::create([
                'host_id' => $host->id,
                'invite_id' => $invite->id,
                'role' => $invite->grants_host ? 'host' : 'client',
                'display_name' => $name,
                'device_token' => request()->header('X-Device-Id', Str::random(40)),
                'last_seen_at' => now(),
            ]);

            $invite->forceFill([
                'redeemed_at' => now(),
                'redeemed_by_client_id' => $client->id,
            ])->save();

            return $client->fresh('host');
        });

        $request->session()->forget('pending_invite_id');
        $request->session()->put('client_id', $client->id);

        return response()
            ->view('ham.onboarding._join_success', ['current_client' => $client])
            ->header('Content-Type', 'text/html');
    }
}
