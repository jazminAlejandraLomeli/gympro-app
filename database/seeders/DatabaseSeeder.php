<?php

namespace Database\Seeders;

use App\Actions\Gyms\CreateGym;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de DESARROLLO: dos gimnasios con marcas distintas para probar
 * el aislamiento y la marca blanca. Contraseña de todos: "password".
 *
 *   http://localhost:8000            → sitio central (super_admin)
 *   http://irondemo.localhost:8000   → Iron Demo (amarillo / morado)
 *   http://titan.localhost:8000      → Titan Fit (rojo / gris)
 */
class DatabaseSeeder extends Seeder
{
    public function run(CreateGym $createGym, TenantContext $tenant): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('El seeder de demo no se corre en producción.');
        }

        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'super@gympro.test',
        ]);

        $iron = $createGym->handle(
            ['name' => 'Iron Demo', 'slug' => 'irondemo', 'primary_color' => '#facc15', 'secondary_color' => '#4c1d95'],
            ['name' => 'Admin Iron', 'email' => 'admin@irondemo.test', 'password' => 'password'],
        );

        $titan = $createGym->handle(
            ['name' => 'Titan Fit', 'slug' => 'titan', 'primary_color' => '#dc2626', 'secondary_color' => '#111827', 'referred_by_gym_id' => $iron->id],
            ['name' => 'Admin Titan', 'email' => 'admin@titan.test', 'password' => 'password'],
        );

        foreach ([$iron, $titan] as $gym) {
            $this->seedStaffAndMembers($gym, $tenant);
        }
    }

    private function seedStaffAndMembers(Gym $gym, TenantContext $tenant): void
    {
        $tenant->runAs($gym, function () use ($gym) {
            User::factory()->role(Role::Trainer)->create([
                'name' => "Coach {$gym->name}",
                'email' => "coach@{$gym->slug}.test",
            ]);

            // Mismo correo de socio en ambos gimnasios: está permitido
            User::factory()->create([
                'name' => 'Socio Demo',
                'email' => 'socio@demo.test',
            ]);

            User::factory()->count(5)->create();
        });
    }
}
