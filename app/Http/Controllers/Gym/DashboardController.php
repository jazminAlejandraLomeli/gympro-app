<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/** Inicio del personal (gym_admin / trainer). Todo filtrado por el gimnasio activo. */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Gym/Dashboard', [
            'stats' => [
                'members' => User::where('role', Role::Member)->count(),
                'trainers' => User::where('role', Role::Trainer)->count(),
                'checkinsToday' => Attendance::whereDate('scanned_at', today())->count(),
            ],
        ]);
    }
}
