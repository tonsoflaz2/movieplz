<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invite;
use App\Support\InviteCode;
use Illuminate\Console\Command;

class HostMintCode extends Command
{
    protected $signature = 'host:mint-code';

    protected $description = 'Mint a one-time 5-digit code that can be redeemed on the mobile app to claim the host role. Errors if a host already exists.';

    public function handle(): int
    {
        $existing = Client::query()->where('role', 'host')->exists();
        if ($existing) {
            $this->error('A host has already been claimed. Use the in-app "Generate invite code" button to issue client invites.');
            return self::FAILURE;
        }

        $invite = Invite::create([
            'host_id' => null,
            'code' => InviteCode::generate(),
            'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
            'grants_host' => true,
        ]);

        $this->info('');
        $this->info('  Host code: '.$invite->code);
        $this->info('  Expires:   '.$invite->expires_at->diffForHumans().' ('.InviteCode::LIFETIME_MINUTES.' minutes)');
        $this->info('');
        $this->info('  Open the MoviePlz app on your phone and enter this code to claim host.');
        $this->info('');

        return self::SUCCESS;
    }
}
