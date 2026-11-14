<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Host;
use App\Models\Invite;
use App\Support\InviteCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HostSetupController extends Controller
{
    /**
     * POST /onboarding/host-setup
     *
     * Creates the host with an empty library named after them, a first
     * invite code, and signs them in. The library is filled afterwards
     * from the Library source screen (a Google Sheet or pasted titles).
     */
    public function submit(Request $request)
    {
        $name = trim((string) $request->input('display_name', ''));

        $validator = Validator::make(['display_name' => $name], [
            'display_name' => ['required', 'string', 'max:40'],
        ], [
            'display_name.required' => 'Enter your name so friends know whose library this is.',
            'display_name.max' => 'Names can be at most 40 characters.',
        ]);

        if ($validator->fails()) {
            return response()
                ->view('ham.onboarding.host_setup', [
                    'error' => $validator->errors()->first('display_name'),
                    'display_name' => $name,
                ])
                ->header('Content-Type', 'text/html');
        }

        [$client, $invite] = DB::transaction(function () use ($name) {
            $host = Host::create(['name' => $name."'s library"]);

            $client = Client::create([
                'host_id' => $host->id,
                'role' => 'host',
                'display_name' => $name,
                'device_token' => request()->header('X-Device-Id', Str::random(40)),
                'last_seen_at' => now(),
            ]);

            $invite = Invite::create([
                'host_id' => $host->id,
                'code' => InviteCode::generate(),
                'grants_host' => false,
                'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
            ]);

            return [$client->fresh('host'), $invite];
        });

        $request->session()->put('client_id', $client->id);

        return response()
            ->view('ham.onboarding._host_code', [
                'invite' => $invite,
                'current_client' => $client,
            ])
            ->header('Content-Type', 'text/html');
    }
}
