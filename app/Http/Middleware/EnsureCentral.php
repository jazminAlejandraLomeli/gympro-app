<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rutas que solo existen en el dominio central (panel del SaaS). Alias: `central`. */
class EnsureCentral
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->tenant->check(), 404);

        return $next($request);
    }
}
