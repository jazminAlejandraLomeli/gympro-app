<?php

use App\Http\Middleware\BindSessionToTenant;
use App\Http\Middleware\EnsureCentral;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Multi-tenant: el gimnasio se resuelve antes de leer la sesión
        $middleware->web(prepend: [ResolveTenant::class]);

        $middleware->web(append: [
            BindSessionToTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Limpia sesiones ajenas y activa el bypass del super_admin antes del route model binding
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: BindSessionToTenant::class,
        );

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => EnsureTenant::class,
            'central' => EnsureCentral::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
