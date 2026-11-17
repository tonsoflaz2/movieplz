<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Host;
use Illuminate\Http\Request;

/**
 * Renaming the library, from the dashboard's heading. Any name the host
 * likes; "{name}'s library" is only the starting point.
 */
class LibraryNameController extends Controller
{
    /** GET /host/library-name.ham — the heading again (Cancel). */
    public function show(Request $request)
    {
        return $this->heading($this->host($request));
    }

    /** GET /host/library-name/edit.ham — the field, filled in with the current name. */
    public function edit(Request $request)
    {
        $host = $this->host($request);

        return $this->form($host, $host->name);
    }

    /** POST /host/library-name */
    public function update(Request $request)
    {
        $host = $this->host($request);
        $name = Host::cleanName($request->input('name'));

        if ($name === '') {
            return $this->form($host, $name, 'Give your library a name.');
        }
        if (mb_strlen($name) > Host::NAME_MAX) {
            return $this->form($host, $name, 'Library names can be at most '.Host::NAME_MAX.' characters.');
        }

        $host->forceFill(['name' => $name])->save();

        return $this->heading($host);
    }

    private function host(Request $request): Host
    {
        /** @var Client $client */
        $client = $request->attributes->get('current_host');

        return $client->host;
    }

    private function heading(Host $host)
    {
        return response()->view('ham.host.partials._library_name', ['host' => $host])->header('Content-Type', 'text/html');
    }

    private function form(Host $host, string $value, ?string $error = null)
    {
        return response()
            ->view('ham.host.partials._library_name_edit', ['host' => $host, 'value' => $value, 'error' => $error])
            ->header('Content-Type', 'text/html');
    }
}
