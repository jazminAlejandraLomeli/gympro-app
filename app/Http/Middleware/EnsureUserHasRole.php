<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas:  ->middleware('role:gym_admin,trainer')
 * El super_admin siempre pasa.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? abort(401);

        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        abort_unless($user->isSuperAdmin() || $user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
