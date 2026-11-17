<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\ExerciseController;
use App\Http\Requests\Training\SaveOwnRoutineRequest;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineItem;
use App\Models\RoutineRequest;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWriter;
use App\Services\Training\TodayRoutine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal del socio: su rutina de hoy, su semana, su historial y las
 * rutinas que él mismo arma (RoutinePolicy decide qué puede ver/editar).
 */
class RoutineController extends Controller
{
    public function __construct(private TodayRoutine $today) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $pending = RoutineRequest::pending()->where('user_id', $user->id)->latest('id')->first();

        return Inertia::render('Member/Routine', [
            ...$this->today->forRoutinePage($user),
            'pendingRequest' => $pending?->toClientArray(),
            'hasHistory' => RoutineAssignment::where('user_id', $user->id)->exists(),
        ]);
    }

    public function history(Request $request): Response
    {
        $user = $request->user();
        $today = $this->today->todayDate();

        $assignments = RoutineAssignment::query()
            ->where('user_id', $user->id)
            ->with(['routine' => fn ($q) => $q->withCount('items')->with('creator:id,name')])
            ->orderByRaw('COALESCE(scheduled_for, starts_on) DESC')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (RoutineAssignment $a) => [
                'id' => $a->id,
                'date' => $a->scheduled_for ?? $a->starts_on->toDateString(),
                'is_current' => $a->isCurrent($today),
                'routine' => [
                    ...TodayRoutine::summary($a->routine, $user),
                    'items_count' => $a->routine->items_count,
                    'can_edit' => Gate::forUser($user)->allows('update', $a->routine),
                ],
            ]);

        return Inertia::render('Member/RoutineHistory', ['assignments' => $assignments]);
    }

    public function show(Request $request, Routine $routine): Response
    {
        Gate::authorize('view', $routine);

        $user = $request->user();
        $routine->load(['items.exercise', 'creator:id,name']);
        $today = $this->today->todayDate();

        $isCurrent = RoutineAssignment::where('routine_id', $routine->id)
            ->where('user_id', $user->id)
            ->get()
            ->contains(fn (RoutineAssignment $a) => $a->isCurrent($today));

        return Inertia::render('Member/RoutineShow', [
            'routine' => [
                ...TodayRoutine::summary($routine, $user),
                'is_current' => $isCurrent,
                'can_edit' => Gate::allows('update', $routine),
            ],
            'items' => $routine->isSingle() ? $routine->items->map(fn (RoutineItem $i) => TodayRoutine::itemToArray($i))->values()->all() : [],
            'days' => $routine->isSingle() ? [] : TodayRoutine::days($routine),
            'day' => $this->today->today(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Routine::class);

        $type = $request->query('tipo') === 'dia' ? Routine::TYPE_SINGLE : Routine::TYPE_WEEKLY;

        return $this->builder('create', null, [
            'name' => '', 'type' => $type, 'goal' => '', 'notes' => '', 'items' => [],
        ]);
    }

    public function store(SaveOwnRoutineRequest $request, RoutineWriter $writer, RoutineAssigner $assigner): RedirectResponse
    {
        Gate::authorize('create', Routine::class);

        $user = $request->user();
        $data = $request->validated();

        $routine = DB::transaction(function () use ($user, $data, $writer, $assigner) {
            $routine = new Routine;
            $routine->forceFill(['is_template' => false, 'member_id' => $user->id, 'created_by' => $user->id]);
            $writer->save($routine, [
                'name' => $data['name'],
                'type' => $data['type'],
                'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], $data['items'] ?? []);

            // Se le asigna solo: la de un día para hoy, la semanal desde hoy
            $assigner->assign($routine, [$user->id], $this->today->now(), $user);

            return $routine;
        });

        return redirect('/app/rutina')->with('success', $routine->isSingle()
            ? "Listo, «{$routine->name}» es tu rutina de hoy."
            : "Listo, «{$routine->name}» es tu rutina de la semana.");
    }

    public function edit(Routine $routine): Response
    {
        Gate::authorize('update', $routine);

        $routine->load('items.exercise');

        return $this->builder('edit', $routine, [
            'name' => $routine->name,
            'type' => $routine->type,
            'goal' => $routine->goal ?? '',
            'notes' => $routine->notes ?? '',
            'items' => $routine->items->map(fn (RoutineItem $i) => TodayRoutine::itemToArray($i))->values()->all(),
        ]);
    }

    public function update(SaveOwnRoutineRequest $request, Routine $routine, RoutineWriter $writer): RedirectResponse
    {
        Gate::authorize('update', $routine);

        $writer->save(
            $routine,
            $request->safe()->only(['name', 'goal', 'notes']),
            $request->has('items') ? ($request->validated('items') ?? []) : null,
        );

        return redirect("/app/rutina/{$routine->id}")->with('success', 'Rutina guardada.');
    }

    public function destroy(Routine $routine): RedirectResponse
    {
        Gate::authorize('delete', $routine);

        $routine->delete();

        return redirect('/app/rutina/historial')->with('success', 'Rutina eliminada.');
    }

    /** "Volver a usar": la semanal se reactiva desde hoy; la de un día se programa para hoy. */
    public function reuse(Request $request, Routine $routine, RoutineAssigner $assigner): RedirectResponse
    {
        Gate::authorize('reuse', $routine);

        $assigner->assign($routine, [$request->user()->id], $this->today->now(), $request->user());

        return redirect('/app/rutina')->with('success', $routine->isSingle()
            ? "«{$routine->name}» quedó como tu rutina de hoy."
            : "«{$routine->name}» volvió a ser tu rutina de la semana.");
    }

    private function builder(string $mode, ?Routine $routine, array $initial): Response
    {
        return Inertia::render('Member/RoutineBuilder', [
            'mode' => $mode,
            'routine' => $routine ? ['id' => $routine->id] : null,
            'initial' => $initial,
            'catalog' => ExerciseController::catalog(),
            'muscleGroups' => ExerciseController::muscleGroupOptions(),
            'today' => $this->today->todayDate(),
        ]);
    }
}
