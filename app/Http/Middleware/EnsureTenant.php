<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rutas que solo existen dentro del subdominio de un gimnasio. Alias: `tenant`. */
class EnsureTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->tenant->check(), 404);

        return $next($request);
    }
}
