<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Support\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Recolors HAM screens in the signed-in person's theme (App\Support\Theme).
 * Only responses rendered from a `ham.*` view are touched, so the website
 * pages and anything else go out as they are; Classic (no theme picked)
 * goes out byte for byte as before.
 */
class ApplyTheme
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $response instanceof Response || ! $response->original instanceof View
            || ! str_starts_with($response->original->name(), 'ham.')) {
            return $response;
        }

        $client = $request->attributes->get('current_client');
        if (! $client && ($id = $request->session()->get('client_id'))) {
            $client = Client::query()->whereNull('revoked_at')->find($id);
        }
        $theme = $client?->theme;
        if (Theme::key($theme) === Theme::CLASSIC) {
            return $response;
        }

        return $response->setContent(Theme::apply((string) $response->getContent(), $theme));
    }
}
