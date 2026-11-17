<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\Theme;
use Illuminate\Http\Request;

/**
 * The theme picker, for hosts and friends alike: each person's own choice,
 * on their own phone. Pushed from the home screen's "Theme" button.
 */
class ThemeController extends Controller
{
    /** GET /theme.ham */
    public function show(Request $request)
    {
        return $this->ham('ham.theme', ['current' => Theme::key($this->client($request)->theme)]);
    }

    /**
     * POST /theme?name=midnight — save it, then restart the app so the
     * new colors reach the app bar and tabs too (the engine reads <config>
     * once, at launch).
     */
    public function update(Request $request)
    {
        $name = (string) $request->query('name', '');
        if (! isset(Theme::THEMES[$name])) {
            return $this->ham('ham.theme', ['current' => Theme::key($this->client($request)->theme), 'error' => "That theme isn't one of the choices."]);
        }

        $client = $this->client($request);
        $client->forceFill(['theme' => $name === Theme::CLASSIC ? null : $name])->save();

        return $this->ham('ham.partials._theme_applied', ['theme' => Theme::get($name)]);
    }

    private function client(Request $request): Client
    {
        return $request->attributes->get('current_client');
    }

    private function ham(string $view, array $data)
    {
        return response()->view($view, $data)->header('Content-Type', 'text/html');
    }
}
