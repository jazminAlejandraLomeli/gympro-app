<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    /** "/" — landing en el dominio central; en un gimnasio, a su login/inicio. */
    public function landing(Request $request, TenantContext $tenant): Response
    {
        if ($tenant->check()) {
            return redirect()->route($request->user() ? 'home' : 'login');
        }

        return Inertia::render('Welcome')->toResponse($request);
    }

    /** "/inicio" — cada rol aterriza en su pantalla principal. */
    public function home(Request $request): RedirectResponse
    {
        return redirect()->route(match ($request->user()->role) {
            Role::SuperAdmin => 'admin.dashboard',
            Role::GymAdmin, Role::Trainer => 'gym.dashboard',
            Role::Member => 'member.home',
        });
    }
}
