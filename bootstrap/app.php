<?php

use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\ScannerApiMiddleware;
use App\Http\Middleware\ScannerAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            RequirePasswordChange::class,
        ]);

        $middleware->alias([
            'resolve-org' => ResolveOrganization::class,
            'scanner-auth' => ScannerAuthMiddleware::class,
            'scanner-api' => ScannerApiMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->routeIs('guest.pass.show')) {
                return null;
            }

            return response()->view('public.guest-pass', [
                'entry' => null,
                'message' => 'This guest pass link is invalid or has expired.',
                'title' => 'Guest Pass Unavailable',
            ], 403, [
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
        });
    })->create();
