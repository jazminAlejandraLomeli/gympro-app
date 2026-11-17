<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una instancia por request / job (seguro también con Octane)
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        $this->defineGates();
    }

    /**
     * Permisos de alto nivel por rol. Los permisos finos por registro
     * (¿este entrenador puede editar ESTA rutina?) irán en Policies.
     */
    private function defineGates(): void
    {
        // super_admin puede todo
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Panel del SaaS: solo super_admin (lo resuelve Gate::before)
        Gate::define('access-saas-admin', fn (User $user) => false);

        // Marca blanca, entrenadores, avisos, configuración del gimnasio
        Gate::define('manage-gym', fn (User $user) => $user->hasRole(Role::GymAdmin));

        // Recepción: escáner QR, pagos manuales, socios, inventario
        Gate::define('operate-front-desk', fn (User $user) => $user->hasRole(Role::GymAdmin));

        // Rutinas, clips de ejercicios y valoraciones físicas
        Gate::define('manage-training', fn (User $user) => $user->hasRole(Role::GymAdmin, Role::Trainer));

        // Portal del socio (credencial QR, rutina, rachas, evolución)
        Gate::define('use-member-portal', fn (User $user) => $user->hasRole(Role::Member));
    }
}
