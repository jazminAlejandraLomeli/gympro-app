<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detecta el gimnasio por subdominio:  irondemo.gympro.mx → slug "irondemo".
 *
 * Corre al inicio del grupo web, antes de que se cargue el usuario de
 * la sesión, para que esa búsqueda ya vaya filtrada por gimnasio.
 */
class ResolveTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Estado limpio en cada request (importante con Octane / workers)
        $this->tenant->forget();
        $this->tenant->enforceStrictMode();

        $slug = $this->subdomain($request->getHost());

        if ($slug === null) {
            return $next($request); // dominio central (landing / super_admin)
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            abort(404);
        }

        $gym = Gym::findCachedBySlug($slug) ?? abort(404);

        abort_unless($gym->isActive(), 403, 'El servicio de este gimnasio está suspendido temporalmente.');

        $this->tenant->set($gym);

        return $next($request);
    }

    private function subdomain(string $host): ?string
    {
        $base = strtolower(config('gympro.base_domain'));
        $host = strtolower($host);

        if (! str_ends_with($host, '.'.$base)) {
            return null;
        }

        $sub = substr($host, 0, -strlen('.'.$base));

        if (str_contains($sub, '.')) {
            abort(404); // a.b.gympro.mx no es un gimnasio válido
        }

        return in_array($sub, config('gympro.reserved_subdomains'), true) ? null : $sub;
    }
}
