<?php

namespace App\Providers;

use App\Models\Client;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Share `$current_client` and `$current_host` with every HAM
        // view. Controllers that already resolved the client via
        // RequireClient / RequireHost middleware will override via
        // request attributes; other HAM views (e.g. `/main.ham` that
        // runs before any auth middleware) fall back to session lookup.
        View::composer('ham.*', function ($view) {
            $client = request()->attributes->get('current_client');

            if (! $client) {
                $clientId = session('client_id');
                if ($clientId) {
                    $client = Client::query()
                        ->with('host')
                        ->whereNull('revoked_at')
                        ->find($clientId);
                }
            }

            $view->with('current_client', $client);
            $view->with('current_host', $client?->isHost() ? $client : null);
        });
    }
}
