<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invite;
use App\Support\InviteCode;
use Illuminate\Http\Request;

class InviteCodeController extends Controller
{
    public function generate(Request $request)
    {
        /** @var Client $host */
        $host = $request->attributes->get('current_host');

        Invite::query()
            ->where('host_id', $host->host_id)
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $invite = Invite::create([
            'host_id' => $host->host_id,
            'code' => InviteCode::generate(),
            'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
            'grants_host' => false,
        ]);

        return response()
            ->view('ham.host.partials._invite_code', ['invite' => $invite])
            ->header('Content-Type', 'text/html');
    }
}
