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
        $brand = Brand::current();

        view()->share('brand', $brand);

        $user = $request->user();

        return [
            ...parent::share($request),
            'brand' => $brand->toArray(),
            // Solo lo mínimo: nunca mandar el modelo completo al navegador
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                ] : null,
            ],
        ];
    }
}
