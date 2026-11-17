<?php

namespace App\Http\Middleware;

use App\Support\Brand;
use App\Support\Navigation;
use App\Tenancy\TenantContext;
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
     * Props disponibles en todas las páginas de React.
     *
     * La marca también se comparte con el layout Blade (variables CSS en
     * el primer render, sin parpadeo de colores).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $brand = Brand::current();
        view()->share('brand', $brand);

        $user = $request->user();
        $gym = app(TenantContext::class)->gym();

        return [
            ...parent::share($request),
            'brand' => $brand->toArray(),
            'gym' => $gym ? ['slug' => $gym->slug, 'name' => $gym->name] : null,
            // Solo lo mínimo: nunca mandar el modelo completo al navegador
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                ] : null,
            ],
            'nav' => fn () => $user ? Navigation::for($user, $request) : [],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
