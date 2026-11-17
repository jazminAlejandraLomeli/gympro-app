<?php

namespace App\Actions\Gyms;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta rápida de un gimnasio (Módulo 0, super_admin):
 * crea el gym, su marca inicial y su usuario gym_admin en una sola
 * transacción. Si algo falla, no queda nada a medias.
 *
 * La validación de entrada (slug libre, colores, correo) va en el
 * FormRequest del panel; aquí se asume que ya viene validada.
 */
class CreateGym
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array{name: string, slug?: string, primary_color?: string, secondary_color?: string, logo_url?: string|null, referred_by_gym_id?: int|null}  $gym
     * @param  array{name: string, email: string, password: string}  $admin
     */
    public function handle(array $gym, array $admin): Gym
    {
        return DB::transaction(function () use ($gym, $admin) {
            $model = Gym::create([
                'name' => $gym['name'],
                'slug' => Str::slug($gym['slug'] ?? $gym['name']),
                'saas_monthly_fee' => config('gympro.saas_monthly_fee'),
                'referred_by_gym_id' => $gym['referred_by_gym_id'] ?? null,
            ]);

            // Dentro del contexto del gimnasio nuevo, gym_id se asigna solo
            $this->tenant->runAs($model, function () use ($model, $gym, $admin) {
                $default = config('gympro.default_brand');

                $model->settings()->create([
                    'brand_name' => $gym['name'],
                    'logo_url' => $gym['logo_url'] ?? null,
                    'primary_color' => $gym['primary_color'] ?? $default['primary_color'],
                    'secondary_color' => $gym['secondary_color'] ?? $default['secondary_color'],
                ]);

                $user = new User([
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'password' => $admin['password'],
                ]);
                $user->role = Role::GymAdmin;
                $user->save();
            });

            return $model;
        });
    }
}
