<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\EnsureAdminAuthenticated::class,
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
            'donor.active' => \App\Http\Middleware\EnsureDonorActive::class,
        ]);

        // Exempt webhook routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'git-deploy-token-734866278',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API/fetch callers receive a stable JSON contract while Laravel's
        // throttle middleware still supplies Retry-After and X-RateLimit-*.
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->expectsJson() && ! $request->ajax() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ], 429, $exception->getHeaders());
        });
    })->create();

$servedPublicPath = dirname(__DIR__).'/..';
$checkedInPublicPath = dirname(__DIR__).'/../public_html';

// Hostinger serves the outer directory after the public asset sync. The
// repository keeps a checked-in public_html copy for local tests and builds,
// so use it when the outer served directory is not present yet.
$app->usePublicPath(
    is_file($servedPublicPath.'/index.php') && is_file($servedPublicPath.'/build/manifest.json')
        ? $servedPublicPath
        : $checkedInPublicPath
);

return $app;
