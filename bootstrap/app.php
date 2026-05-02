<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\EnsureAdminAuthenticated::class,
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
        ]);

        // Exempt webhook routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'git-deploy-token-734866278',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
