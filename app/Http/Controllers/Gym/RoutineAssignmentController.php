<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\AssignRoutineRequest;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\TodayRoutine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/** Asignar la rutina a socios (una sola activa por socio). */
class RoutineAssignmentController extends Controller
{
    public function store(AssignRoutineRequest $request, Routine $routine, RoutineAssigner $assigner): RedirectResponse
    {
        $startsOn = $request->filled('starts_on')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->validated('starts_on'), TodayRoutine::TIMEZONE)
            : CarbonImmutable::now(TodayRoutine::TIMEZONE);

        $count = $assigner->assign($routine, $request->validated('user_ids'), $startsOn, $request->user());

        return back()->with('success', $count === 1 ? 'Rutina asignada a 1 socio.' : "Rutina asignada a {$count} socios.");
    }

    public function destroy(Routine $routine, RoutineAssignment $assignment, RoutineAssigner $assigner): RedirectResponse
    {
        $assigner->deactivate($assignment);

        return back()->with('success', 'Se quitó la rutina al socio.');
    }
}
