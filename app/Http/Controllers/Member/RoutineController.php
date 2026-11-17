<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Training\TodayRoutine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Mi rutina": la rutina activa del socio, con hoy seleccionado. */
class RoutineController extends Controller
{
    public function __invoke(Request $request, TodayRoutine $today): Response
    {
        return Inertia::render('Member/Routine', $today->forRoutinePage($request->user()));
    }
}
