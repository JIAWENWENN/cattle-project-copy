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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\LogPageVisit::class,
        ]);
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckModulePermission::class,
            'csrf.except' => \App\Http\Middleware\ExceptCsrf::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Validation/auth failures must redirect for Inertia — not return plain JSON.
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                return false;
            }

            return $request->expectsJson();
        });
    })->create();
