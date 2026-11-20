<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Client\HomeController as ClientHomeController;
use App\Http\Controllers\Client\LibraryController as ClientLibraryController;
use App\Http\Controllers\Client\RequestController as ClientRequestController;
use App\Http\Controllers\Client\UnreadController as ClientUnreadController;
use App\Http\Controllers\Host\UnreadController as HostUnreadController;
use App\Http\Controllers\Host\ClientController as HostClientController;
use App\Http\Controllers\Host\HomeController as HostHomeController;
use App\Http\Controllers\Host\InviteCodeController as HostInviteCodeController;
use App\Http\Controllers\Host\LibrarySourceController as HostLibrarySourceController;
use App\Http\Controllers\Host\RequestController as HostRequestController;
use App\Http\Controllers\Host\TbwController as HostTbwController;
use App\Http\Controllers\Host\TitleEditController as HostTitleEditController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\Onboarding\HostSetupController as OnboardingHostSetupController;
use App\Http\Controllers\Onboarding\InviteController as OnboardingInviteController;
use App\Http\Controllers\Onboarding\NameController as OnboardingNameController;
use App\Http\Controllers\Onboarding\ReconnectController as OnboardingReconnectController;
use Illuminate\Support\Facades\Route;

// Plain web pages the store listing links to.
Route::view('/privacy', 'site.privacy');
Route::view('/support', 'site.support');

// Root and cold-launch entry both branch on session inside MainController.
// Hitting http://movieplz.test/ in any HAM client (or browser) returns the
// same `<app>` shell as /app.ham.
Route::get('/', [MainController::class, 'index']);

/*
|--------------------------------------------------------------------------
| HAM routes (mobile shell)
|--------------------------------------------------------------------------
| See /Users/fluency/Code/movieplz/CLAUDE.md for Blade-vs-HAM rules and
| /Users/fluency/Code/movieplz/docs/spec.md for the product spec.
|
| URL conventions:
|   /app.ham                cold-launch entry, always full screen
|   /home.ham               authenticated client home (partial)
|   /host/home.ham          authenticated host home (partial)
|   /onboarding/*           unauthenticated flow
|   POST /onboarding/invite, /onboarding/name — redemption steps
|
| All HAM controllers return Content-Type: text/html so the framework's
| NetworkManager treats the response as markup. HAM routes NEVER return
| a non-2xx status — validation failures re-render the form at 200.
*/

// Cold launch entry — branches on session inside MainController.
Route::get('/app.ham', [MainController::class, 'index']);

// Onboarding — unauthenticated.
// Rate-limit POST /onboarding/invite to 5 attempts per minute per IP
// to defend 5-digit codes against brute force.
Route::get('/onboarding/host-setup.ham', fn () => response()
    ->view('ham.onboarding.host_setup')
    ->header('Content-Type', 'text/html'))
    ->middleware('guest.client');
Route::get('/onboarding/join.ham', fn () => response()
    ->view('ham.onboarding.join')
    ->header('Content-Type', 'text/html'))
    ->middleware('guest.client');
Route::post('/onboarding/host-setup', [OnboardingHostSetupController::class, 'submit'])
    ->middleware('guest.client');
Route::post('/onboarding/invite', [OnboardingInviteController::class, 'redeem'])
    ->middleware(['throttle:5,1', 'guest.client']);
Route::post('/onboarding/name', [OnboardingNameController::class, 'submit'])
    ->middleware('guest.client');
// Reconnect: back to your library with your reconnect code, after
// disconnecting a phone or on a new one.
Route::get('/onboarding/reconnect.ham', fn () => response()
    ->view('ham.onboarding.reconnect')
    ->header('Content-Type', 'text/html'))
    ->middleware('guest.client');
// Rate-limited inside (five tries a minute), so the form can say so.
Route::post('/onboarding/reconnect', [OnboardingReconnectController::class, 'submit'])
    ->middleware('guest.client');

// Push token registration — called by <notification register="/api/devices"/>.
// No auth middleware — controller resolves session best-effort.
Route::post('/api/devices', [\App\Http\Controllers\DeviceController::class, 'register']);

// Shared library — accessible to both clients and hosts.
Route::middleware('auth.session')->group(function () {
    Route::get('/library.ham', [ClientLibraryController::class, 'index']);
    Route::get('/library/search.ham', [ClientLibraryController::class, 'search']);
    Route::get('/library/{id}.ham', function (int $id, \Illuminate\Http\Request $request) {
        $client = $request->attributes->get('current_client');
        $item = \App\Models\LibraryItem::where('host_id', $client->host_id)->findOrFail($id);
        return response()
            ->view('ham.client.library_detail', ['item' => $item, 'current_client' => $client])
            ->header('Content-Type', 'text/html');
    });

    // Disconnect this phone (hosts and friends): keeps everything.
    Route::get('/account/disconnect.ham', [AccountController::class, 'disconnectPanel']);

    // Each person's color theme.
    Route::get('/theme.ham', [\App\Http\Controllers\ThemeController::class, 'show']);
    Route::post('/theme', [\App\Http\Controllers\ThemeController::class, 'update']);
    Route::post('/account/disconnect', [AccountController::class, 'disconnect']);
});

// Authenticated client routes — all partials swapped into #main.
Route::middleware('client.required')->group(function () {
    Route::get('/home.ham', [ClientHomeController::class, 'show']);
    Route::get('/home/unread.ham', [ClientUnreadController::class, 'show']);

    Route::get('/requests.ham', [ClientRequestController::class, 'index']);
    Route::get('/requests/new.ham', [ClientRequestController::class, 'create']);
    Route::post('/account/leave', [AccountController::class, 'leave']);
    Route::post('/requests', [ClientRequestController::class, 'store']);
    Route::get('/requests/confirmed/{id}.ham', function (int $id, \Illuminate\Http\Request $request) {
        $client = $request->attributes->get('current_client');
        $req = \App\Models\MediaRequest::where('client_id', $client->id)->findOrFail($id);
        return response()
            ->view('ham.client.request_confirmed', ['media_request' => $req])
            ->header('Content-Type', 'text/html');
    });
});

// Authenticated host routes — partials into #main (host role enforced).
Route::middleware('host.required')->prefix('host')->group(function () {
    Route::get('/home.ham', [HostHomeController::class, 'show']);
    Route::get('/unread.ham', [HostUnreadController::class, 'show']);

    Route::get('/requests.ham', [HostRequestController::class, 'index']);
    Route::post('/requests/{id}/up-next', [HostRequestController::class, 'upNext']);
    Route::post('/requests/{id}/fulfill', [HostRequestController::class, 'fulfill']);
    Route::post('/requests/{id}/decline', [HostRequestController::class, 'decline']);
    Route::post('/requests/{id}/delete', [HostRequestController::class, 'destroy']);

    Route::post('/invite-code', [HostInviteCodeController::class, 'generate']);

    Route::get('/invite-status/{id}.ham', function (int $id, \Illuminate\Http\Request $request) {
        $host = $request->attributes->get('current_client');
        $invite = \App\Models\Invite::where('host_id', $host->host_id)->findOrFail($id);
        return response()
            ->view('ham.host.partials._invite_code', ['invite' => $invite])
            ->header('Content-Type', 'text/html');
    });

    Route::get('/clients.ham', [HostClientController::class, 'index']);
    Route::post('/clients/{id}/revoke', [HostClientController::class, 'revoke']);
    Route::get('/clients/{id}.ham', [HostClientController::class, 'show'])->whereNumber('id');
    Route::get('/clients/{id}/rename.ham', [HostClientController::class, 'renameForm']);
    Route::post('/clients/{id}/rename', [HostClientController::class, 'rename']);
    Route::get('/clients/{id}/row.ham', [HostClientController::class, 'rowView']);

    // Where the library's titles come from: a Google Sheet or a pasted list.
    Route::get('/library-source.ham', [HostLibrarySourceController::class, 'show']);
    Route::post('/library-source', [HostLibrarySourceController::class, 'link']);
    Route::post('/library-sync', [HostLibrarySourceController::class, 'sync']);
    Route::post('/library-mirror', [HostLibrarySourceController::class, 'mirror']);

    // The library's name, renamed from the dashboard heading.
    Route::get('/library-name.ham', [\App\Http\Controllers\Host\LibraryNameController::class, 'show']);
    Route::get('/library-name/edit.ham', [\App\Http\Controllers\Host\LibraryNameController::class, 'edit']);
    Route::post('/library-name', [\App\Http\Controllers\Host\LibraryNameController::class, 'update']);
    Route::post('/library-paste', [HostLibrarySourceController::class, 'paste']);

    Route::post('/delete-library', [AccountController::class, 'deleteLibrary']);

    // TBW: everything friends have asked to watch; keep or remove titles
    // added in the app.
    Route::get('/tbw.ham', [HostTbwController::class, 'index']);
    Route::post('/library/{id}/keep', [HostTbwController::class, 'keep']);
    Route::post('/library/{id}/remove', [HostTbwController::class, 'remove']);

    // Editing a title (library titles in a TBW row or on their page, and
    // pending suggestions in their row), and picking its TMDB match.
    Route::get('/library/{id}/edit.ham', [HostTitleEditController::class, 'editForm']);
    Route::post('/library/{id}/edit', [HostTitleEditController::class, 'update']);
    Route::get('/library/{id}/matches.ham', [HostTitleEditController::class, 'matchList']);
    Route::post('/library/{id}/match', [HostTitleEditController::class, 'match']);
    Route::get('/library/{id}/view.ham', [HostTitleEditController::class, 'view']);
    Route::post('/library/{id}/delete', [HostTitleEditController::class, 'destroy']);
    Route::get('/requests/{id}/edit.ham', [HostTitleEditController::class, 'suggestionForm']);
    Route::post('/requests/{id}/edit', [HostTitleEditController::class, 'updateSuggestion']);
    Route::get('/requests/{id}/row.ham', [HostTitleEditController::class, 'suggestionRowView']);
});

/*
|--------------------------------------------------------------------------
| Local-dev helpers (not registered outside local env)
|--------------------------------------------------------------------------
| These exist ONLY in `APP_ENV=local`. Any request to them in any other
| environment will 404 at the routing layer because the routes aren't
| registered. Used for smoke testing and tinker-style shortcuts.
*/
if (app()->environment('local')) {
    Route::get('/dev/login-as-host', function (\Illuminate\Http\Request $request) {
        $host = \App\Models\Client::where('role', 'host')->firstOrFail();
        $request->session()->put('client_id', $host->id);
        return response('dev: session set to host '.$host->display_name.' (id='.$host->id.')', 200);
    });
}
