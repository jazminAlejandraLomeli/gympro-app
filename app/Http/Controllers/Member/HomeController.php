<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Announcements\VisibleAnnouncements;
use App\Services\Membership\MembershipSummary;
use App\Services\Progress\ProgressSummary;
use App\Services\Training\TodayRoutine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio del socio: una tarjeta por módulo. Cada servicio ya filtra por
 * el gimnasio activo y usa caché en Redis donde aplica.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, TodayRoutine $todayRoutine): Response
    {
        $user = $request->user();

        return Inertia::render('Member/Home', [
            'membership' => MembershipSummary::forMemberHome($user),          // Módulo A
            'todayRoutine' => $todayRoutine->forMemberHome($user),            // Módulo B
            'progress' => ProgressSummary::forMemberHome($user),              // Módulos C y D
            'announcementsCard' => VisibleAnnouncements::forMemberHome($user), // Módulo E
        ]);
    }
}
