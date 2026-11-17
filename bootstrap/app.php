<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client.required' => \App\Http\Middleware\RequireClient::class,
            'host.required' => \App\Http\Middleware\RequireHost::class,
            'guest.client' => \App\Http\Middleware\RequireGuestClient::class,
            'auth.session' => \App\Http\Middleware\RequireAuth::class,
        ]);

        // Each person's color theme, applied to every HAM screen on the way out.
        $middleware->web(append: \App\Http\Middleware\ApplyTheme::class);

        // The mobile shell is the only caller of HAM routes. It's not a
        // browser, so CSRF tokens don't apply — the threat model that
        // CSRF defends against (cross-site form submission) doesn't
        // exist here. Auth still rides on session cookies, which the
        // shell's secure-storage cookie jar persists across launches.
        $middleware->validateCsrfTokens(except: [
            'onboarding/*',
            'host/*',
            'requests',
            'requests/*',
            'api/*',
            'account/*',
            'theme',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The hypeapps mobile shell treats any non-2xx response as a
        // generic framework-level error, discarding the body (see
        // hypeapps/lib/src/controls/network_manager.dart:617–639). So
        // HAM routes must always return 200, even on failure. This
        // renderer catches any uncaught exception on a HAM route and
        // returns a clean HAM error block at status 200 instead of
        // Laravel's debug page.
        $exceptions->render(function (\Throwable $e, Request $request) {
            $path = $request->path();
            $isHamRoute = str_ends_with($path, '.ham')
                || str_starts_with($path, 'onboarding')
                || str_starts_with($path, 'host')
                || str_starts_with($path, 'requests')
                || str_starts_with($path, 'library')
                || str_starts_with($path, 'account')
                || $path === 'home.ham';

            if (! $isHamRoute) {
                return null; // let Laravel handle non-HAM paths normally
            }

            Log::error('HAM route exception', [
                'path' => $path,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            $message = config('app.debug')
                ? ($e->getMessage() ?: class_basename($e))
                : 'Something went wrong. Please try again.';

            return response()
                ->view('ham.partials.error', ['message' => $message], 200)
                ->header('Content-Type', 'text/html');
        });
    })->create();
