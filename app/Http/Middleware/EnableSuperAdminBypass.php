<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En el dominio central, un super_admin autenticado ve todos los
 * gimnasios. Se ordena antes de SubstituteBindings (ver bootstrap/app.php)
 * para que el route model binding también vea sin filtro.
 */
class EnableSuperAdminBypass
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenant->check() && $request->user()?->isSuperAdmin()) {
            $this->tenant->enableBypass();
        }

        return $next($request);
    }
}
