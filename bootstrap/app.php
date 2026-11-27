<?php

use App\Http\Middleware\IdleTimeout;
use App\Http\Middleware\RecentAuth;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->web(append: [SecurityHeaders::class, IdleTimeout::class, SetLocale::class]);
        $middleware->alias([
            'mfa' => RequireMfa::class,
            'reauth' => RecentAuth::class,
            'role' => RequireRole::class,
        ]);
        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            IdleTimeout::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            RequireMfa::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'mfa_code', 'code', 'recovery_code', 'document']);
        $exceptions->render(function (\App\Domain\InvalidTransition $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => 'That change is not allowed in the current status.'], 409)
                : back()->withErrors(['status' => 'That change is not allowed in the current status.']);
        });
    })->create();
