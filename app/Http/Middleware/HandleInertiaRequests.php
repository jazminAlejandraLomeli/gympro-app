<?php

namespace App\Http\Middleware;

use App\Support\Brand;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Layout raíz que se carga en la primera visita.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Comparte la marca con el layout Blade (variables CSS en el
     * primer render, sin parpadeo de colores) y con React como prop.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // TODO módulo multi-tenant: resolver la marca del gimnasio actual
        $brand = Brand::default();

        view()->share('brand', $brand);

        return [
            ...parent::share($request),
            'brand' => $brand->toArray(),
        ];
    }
}
