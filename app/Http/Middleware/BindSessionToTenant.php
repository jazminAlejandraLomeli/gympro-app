<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corre después de iniciar la sesión y ANTES del route model binding
 * (ver prioridad en bootstrap/app.php).
 *
 * 1. Si la sesión trae un usuario que no existe en este gimnasio (p. ej.
 *    una cookie de otro subdominio), lo borra. Sin esto, auth()->id()
 *    devolvería el id crudo de la sesión aunque user() sea null.
 * 2. En el dominio central, un super_admin autenticado ve todos los
 *    gimnasios (bypass del filtro por gym_id).
 */
class BindSessionToTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $request->user();

        if ($user === null && $request->hasSession() && $request->session()->has($guard->getName())) {
            $request->session()->forget($guard->getName());
            $guard->forgetUser();
        }

        if (! $this->tenant->check() && $user?->isSuperAdmin()) {
            $this->tenant->enableBypass();
        }

        return $next($request);
    }
}
