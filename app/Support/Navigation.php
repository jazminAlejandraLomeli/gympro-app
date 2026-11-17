<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Menú por rol. Se arma en el servidor para que el navegador solo
 * reciba las opciones que el usuario realmente puede usar.
 *
 * Cada entrada aparece solo si su ruta existe (Route::has) y el
 * usuario pasa el Gate indicado. `icon` es un nombre de lucide-react.
 */
class Navigation
{
    /** @return array<int, array{label: string, route: string, icon: string, can?: string}> */
    private static function definition(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => [
                ['label' => 'Resumen', 'route' => 'admin.dashboard', 'icon' => 'LayoutDashboard'],
                ['label' => 'Gimnasios', 'route' => 'admin.gyms.index', 'icon' => 'Building2'],
                ['label' => 'Finanzas', 'route' => 'admin.finance.index', 'icon' => 'Wallet'],
                ['label' => 'Sistema', 'route' => 'admin.system.index', 'icon' => 'Activity'],
                ['label' => 'Errores', 'route' => 'admin.logs.index', 'icon' => 'Bug'],
            ],
            Role::GymAdmin, Role::Trainer => [
                ['label' => 'Inicio', 'route' => 'gym.dashboard', 'icon' => 'House'],
                ['label' => 'Escáner QR', 'route' => 'gym.scanner', 'icon' => 'ScanLine', 'can' => 'operate-front-desk'],
                ['label' => 'Socios', 'route' => 'gym.members.index', 'icon' => 'Users', 'can' => 'operate-front-desk'],
                ['label' => 'Entrenadores', 'route' => 'gym.trainers.index', 'icon' => 'UserCog', 'can' => 'manage-gym'],
                ['label' => 'Rutinas', 'route' => 'gym.routines.index', 'icon' => 'ClipboardList', 'can' => 'manage-training'],
                ['label' => 'Ejercicios', 'route' => 'gym.exercises.index', 'icon' => 'Clapperboard', 'can' => 'manage-training'],
                ['label' => 'Valoraciones', 'route' => 'gym.measurements.index', 'icon' => 'Ruler', 'can' => 'manage-training'],
                ['label' => 'Avisos', 'route' => 'gym.announcements.index', 'icon' => 'Megaphone', 'can' => 'manage-gym'],
                ['label' => 'Inventario', 'route' => 'gym.inventory.index', 'icon' => 'Package', 'can' => 'operate-front-desk'],
                ['label' => 'Mi marca', 'route' => 'gym.branding.edit', 'icon' => 'Palette', 'can' => 'manage-gym'],
            ],
            Role::Member => [
                ['label' => 'Inicio', 'route' => 'member.home', 'icon' => 'House'],
                ['label' => 'Credencial', 'route' => 'member.credential', 'icon' => 'QrCode'],
                ['label' => 'Rutina', 'route' => 'member.routine', 'icon' => 'Dumbbell'],
                ['label' => 'Progreso', 'route' => 'member.progress', 'icon' => 'TrendingUp'],
            ],
        };
    }

    /** @return array<int, array{label: string, href: string, icon: string, active: bool}> */
    public static function for(User $user, Request $request): array
    {
        $items = [];

        foreach (self::definition($user->role) as $item) {
            if (! Route::has($item['route'])) {
                continue;
            }
            if (isset($item['can']) && Gate::forUser($user)->denies($item['can'])) {
                continue;
            }

            // gym.members.index → activo en cualquier gym.members.*
            $section = preg_replace('/\.(index|edit|show)$/', '', $item['route']);

            $items[] = [
                'label' => $item['label'],
                'href' => route($item['route'], absolute: false),
                'icon' => $item['icon'],
                'active' => $request->routeIs($item['route'], $section.'.*'),
            ];
        }

        return $items;
    }
}
