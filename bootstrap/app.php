<?php

use App\Http\Middleware\BlockWhileImpersonating;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            return $request->is('admin*') ? route('admin.login') : route('login');
        });
        // A logout form left open in another tab may carry an old CSRF token;
        // signing out is harmless, so don't answer it with 419 Page Expired.
        $middleware->validateCsrfTokens(except: ['logout', 'admin/logout']);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'not-impersonating' => BlockWhileImpersonating::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
