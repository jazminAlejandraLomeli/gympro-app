<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\AssignRoutineRequest;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWriter;
use App\Services\Training\TodayRoutine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Asignar una plantilla a socios: a cada uno se le crea su copia
 * personal (así el historial no cambia si luego se edita la plantilla).
 */
class RoutineAssignmentController extends Controller
{
    public function store(AssignRoutineRequest $request, Routine $routine, RoutineWriter $writer, RoutineAssigner $assigner): RedirectResponse
    {
        Gate::authorize('assign', $routine);

        $date = $request->filled('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->validated('date'), TodayRoutine::TIMEZONE)
            : CarbonImmutable::now(TodayRoutine::TIMEZONE);
        $by = $request->user();

        $members = User::whereIn('id', $request->validated('user_ids'))->get();

        DB::transaction(function () use ($members, $routine, $writer, $assigner, $date, $by) {
            foreach ($members as $member) {
                $copy = $writer->duplicate($routine, $member, $by);
                $assigner->assign($copy, [$member->id], $date, $by);
            }
        });

        $count = $members->count();

        return back()->with('success', $count === 1 ? 'Rutina asignada a 1 socio.' : "Rutina asignada a {$count} socios.");
    }

    public function destroy(Routine $routine, RoutineAssignment $assignment, RoutineAssigner $assigner): RedirectResponse
    {
        Gate::authorize('update', $routine);

        $assigner->deactivate($assignment);

        return back()->with('success', 'Se quitó la rutina al socio.');
    }
}
