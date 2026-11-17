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
     * Creates the host with an empty library, a first invite code, and
     * signs them in. The library is called what they typed in "Library
     * name", or "{name}'s library" when they left it blank; they can rename
     * it later from the dashboard. The library is filled afterwards
     * from the Library source screen (a Google Sheet or pasted titles).
     */
    public function submit(Request $request)
    {
        $name = trim((string) $request->input('display_name', ''));
        $libraryName = Host::cleanName($request->input('library_name'));

        $validator = Validator::make(['display_name' => $name, 'library_name' => $libraryName], [
            'display_name' => ['required', 'string', 'max:40'],
            'library_name' => ['nullable', 'string', 'max:'.Host::NAME_MAX],
        ], [
            'display_name.required' => 'Enter your name so friends know whose library this is.',
            'display_name.max' => 'Names can be at most 40 characters.',
            'library_name.max' => 'Library names can be at most '.Host::NAME_MAX.' characters.',
        ]);

        if ($validator->fails()) {
            return response()
                ->view('ham.onboarding.host_setup', [
                    'error' => $validator->errors()->first(),
                    'display_name' => $name,
                    'library_name' => $libraryName,
                ])
                ->header('Content-Type', 'text/html');
        }

        [$client, $invite] = DB::transaction(function () use ($name, $libraryName) {
            $host = Host::create(['name' => $libraryName !== '' ? $libraryName : Host::defaultName($name)]);

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
