<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\RoutineRequest;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;
use App\Services\Training\RoutineWeekWriter;
use App\Services\Training\TodayRoutine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Rutinas semanales: listado, alta y constructor por día. */
class RoutineController extends Controller
{
    public function index(): Response
    {
        $routines = Routine::query()
            ->withCount(['items', 'activeAssignments'])
            ->with('creator:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'goal' => $routine->goal,
                'items_count' => $routine->items_count,
                'members_count' => $routine->active_assignments_count,
                'creator' => $routine->creator?->name,
                'updated_at' => $routine->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Gym/Routines/Index', ['routines' => $routines]);
    }

    public function store(RoutineRequest $request): RedirectResponse
    {
        $routine = new Routine($request->safe()->only(['name', 'goal', 'notes']));
        $routine->created_by = $request->user()->id;
        $routine->save();

        return redirect("/panel/rutinas/{$routine->id}")
            ->with('success', 'Rutina creada. Agrega los ejercicios de cada día.');
    }

    public function edit(Request $request, Routine $routine): Response
    {
        $routine->load('items.exercise');

        $assignments = $routine->activeAssignments()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn (RoutineAssignment $a) => [
                'id' => $a->id,
                'user' => ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email],
                'starts_on' => $a->starts_on->toDateString(),
            ]);

        // Rutina activa actual de cada socio (para avisar que se reemplaza)
        $current = RoutineAssignment::query()->active()
            ->join('routines', 'routines.id', '=', 'routine_assignments.routine_id')
            ->pluck('routines.name', 'routine_assignments.user_id');

        $members = User::query()
            ->where('role', Role::Member)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'current_routine' => $current[$u->id] ?? null,
            ]);

        return Inertia::render('Gym/Routines/Edit', [
            'routine' => [
                'id' => $routine->id,
                'name' => $routine->name,
                'goal' => $routine->goal,
                'notes' => $routine->notes,
                'items' => $routine->items->map(fn ($item) => TodayRoutine::itemToArray($item))->values(),
            ],
            'catalog' => Exercise::query()->orderBy('name')->get()->map(fn (Exercise $e) => $e->toClientArray()),
            'muscleGroups' => ExerciseController::muscleGroupOptions(),
            'assignments' => $assignments,
            'members' => $members,
            'today' => now(TodayRoutine::TIMEZONE)->toDateString(),
        ]);
    }

    public function update(RoutineRequest $request, Routine $routine, RoutineWeekWriter $writer): RedirectResponse
    {
        $writer->save(
            $routine,
            $request->safe()->only(['name', 'goal', 'notes']),
            $request->has('items') ? $request->validated('items', []) : null,
        );

        return back()->with('success', 'Rutina guardada.');
    }

    public function destroy(Routine $routine): RedirectResponse
    {
        $routine->delete();

        return redirect('/panel/rutinas')->with('success', 'Rutina eliminada.');
    }
}
