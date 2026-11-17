<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\SaveRoutineRequest;
use App\Models\Routine;
use App\Models\RoutineRequest;
use App\Models\User;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWriter;
use App\Services\Training\TodayRoutine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rutinas del panel: pestañas Solicitudes / Plantillas / Historial y el
 * constructor (alta y edición).
 */
class RoutineController extends Controller
{
    public const TABS = ['solicitudes', 'plantillas', 'historial'];

    public function index(Request $request, TodayRoutine $clock): Response
    {
        Gate::authorize('viewAny', Routine::class);

        $filters = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(self::TABS)],
            'q' => ['nullable', 'string', 'max:100'],
            'creador' => ['nullable', 'string', Rule::in(['coach', 'socio'])],
            'tipo' => ['nullable', 'string', Rule::in(array_keys(Routine::TYPES))],
        ]);

        $pendingCount = RoutineRequest::pending()->count();
        $tab = $filters['tab'] ?? ($pendingCount > 0 ? 'solicitudes' : 'plantillas');
        $today = $clock->now();

        return Inertia::render('Gym/Routines/Index', [
            'tab' => $tab,
            'pendingCount' => $pendingCount,
            'today' => $today->toDateString(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'creador' => $filters['creador'] ?? '',
                'tipo' => $filters['tipo'] ?? '',
            ],
            // Se usan en Solicitudes ("Asignar existente") y en Plantillas
            'templates' => $tab === 'historial' ? [] : $this->templates(),
            'requests' => $tab === 'solicitudes' ? $this->pendingRequests() : [],
            'members' => $tab === 'plantillas' ? self::members() : [],
            'history' => $tab === 'historial' ? $this->history($filters, $today) : null,
        ]);
    }

    /** GET /panel/rutinas/nueva?tipo=dia|semana&socio={id}&solicitud={id} */
    public function create(Request $request, TodayRoutine $clock): Response
    {
        Gate::authorize('create', Routine::class);

        $params = $request->validate([
            'tipo' => ['nullable', 'string', Rule::in(['dia', 'semana'])],
            'socio' => ['nullable', 'integer'],
            'solicitud' => ['nullable', 'integer'],
        ]);

        $routineRequest = isset($params['solicitud'])
            ? RoutineRequest::pending()->with('user:id,name')->find($params['solicitud'])
            : null;

        $memberId = $routineRequest?->user_id ?? ($params['socio'] ?? null);
        $member = $memberId ? User::where('role', Role::Member)->find($memberId, ['id', 'name']) : null;

        $type = match ($params['tipo'] ?? null) {
            'dia' => Routine::TYPE_SINGLE,
            'semana' => Routine::TYPE_WEEKLY,
            default => $routineRequest?->kind ?? Routine::TYPE_WEEKLY,
        };

        // Primer nombre real, saltando títulos como "Sr.", "Ms.", "Dra."
        $firstName = $member
            ? collect(preg_split('/\s+/', trim($member->name)))->first(fn ($w) => ! str_ends_with($w, '.')) ?? $member->name
            : null;
        $suggested = $firstName ? ($type === Routine::TYPE_SINGLE ? "Sesión de {$firstName}" : "Rutina de {$firstName}") : '';

        return Inertia::render('Gym/Routines/Builder', [
            'mode' => 'create',
            'routine' => null,
            'initial' => [
                'name' => $suggested,
                'type' => $type,
                'goal' => $routineRequest ? mb_substr($routineRequest->goal, 0, 120) : '',
                'notes' => '',
                'target' => $member ? 'member' : 'template',
                'member_id' => $member?->id,
                'scheduled_for' => $clock->todayDate(),
                'request_id' => $routineRequest?->id,
                'items' => [],
            ],
            'request' => $routineRequest ? [
                ...$routineRequest->toClientArray(),
                'user' => ['id' => $routineRequest->user->id, 'name' => $routineRequest->user->name],
            ] : null,
            'catalog' => ExerciseController::catalog(),
            'muscleGroups' => ExerciseController::muscleGroupOptions(),
            'members' => self::members(),
            'today' => $clock->todayDate(),
        ]);
    }

    public function store(SaveRoutineRequest $request, RoutineWriter $writer, RoutineAssigner $assigner, TodayRoutine $clock): RedirectResponse
    {
        Gate::authorize('create', Routine::class);

        $data = $request->validated();
        $forMember = ($data['target'] ?? 'template') === 'member';
        $user = $request->user();

        $routine = DB::transaction(function () use ($data, $forMember, $user, $writer, $assigner, $clock) {
            $routine = new Routine;
            $routine->forceFill([
                'is_template' => ! $forMember,
                'member_id' => $forMember ? (int) $data['member_id'] : null,
                'created_by' => $user->id,
            ]);
            $writer->save($routine, [
                'name' => $data['name'],
                'type' => $data['type'],
                'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], $data['items'] ?? []);

            if ($forMember) {
                $date = $routine->isSingle() && ! empty($data['scheduled_for'])
                    ? CarbonImmutable::createFromFormat('Y-m-d', $data['scheduled_for'], TodayRoutine::TIMEZONE)
                    : $clock->now();
                $assigner->assign($routine, [$routine->member_id], $date, $user);

                if (! empty($data['request_id'])) {
                    RoutineRequest::pending()->lockForUpdate()->find($data['request_id'])?->markDone($routine, $user);
                }
            }

            return $routine;
        });

        if (! $forMember) {
            return redirect('/panel/rutinas?tab=plantillas')->with('success', "Plantilla «{$routine->name}» guardada.");
        }

        return redirect(! empty($data['request_id']) ? '/panel/rutinas?tab=solicitudes' : '/panel/rutinas?tab=historial')
            ->with('success', "Rutina «{$routine->name}» creada y asignada.");
    }

    public function edit(Routine $routine): Response
    {
        Gate::authorize('update', $routine);

        $routine->load(['items.exercise', 'member:id,name', 'creator:id,name']);

        return Inertia::render('Gym/Routines/Builder', [
            'mode' => 'edit',
            'routine' => [
                'id' => $routine->id,
                'is_template' => $routine->is_template,
                'member' => $routine->member ? ['id' => $routine->member->id, 'name' => $routine->member->name] : null,
                'author' => $routine->authorLabel(),
            ],
            'initial' => [
                'name' => $routine->name,
                'type' => $routine->type,
                'goal' => $routine->goal ?? '',
                'notes' => $routine->notes ?? '',
                'items' => $routine->items->map(fn ($item) => TodayRoutine::itemToArray($item))->values()->all(),
            ],
            'request' => null,
            'catalog' => ExerciseController::catalog(),
            'muscleGroups' => ExerciseController::muscleGroupOptions(),
            'members' => [],
            'today' => now(TodayRoutine::TIMEZONE)->toDateString(),
        ]);
    }

    public function update(SaveRoutineRequest $request, Routine $routine, RoutineWriter $writer): RedirectResponse
    {
        Gate::authorize('update', $routine);

        $writer->save(
            $routine,
            $request->safe()->only(['name', 'goal', 'notes']),
            $request->has('items') ? ($request->validated('items') ?? []) : null,
        );

        return redirect($routine->is_template ? '/panel/rutinas?tab=plantillas' : '/panel/rutinas?tab=historial')
            ->with('success', 'Rutina guardada.');
    }

    public function destroy(Routine $routine): RedirectResponse
    {
        Gate::authorize('delete', $routine);

        $tab = $routine->is_template ? 'plantillas' : 'historial';
        $routine->delete();

        return redirect("/panel/rutinas?tab={$tab}")->with('success', 'Rutina eliminada.');
    }

    /** Copia como nueva plantilla del gimnasio y abre el constructor. */
    public function duplicate(Request $request, Routine $routine, RoutineWriter $writer): RedirectResponse
    {
        Gate::authorize('create', Routine::class);
        Gate::authorize('view', $routine);

        $copy = $writer->duplicate($routine, null, $request->user(), mb_substr($routine->name, 0, 90).' (copia)');

        return redirect("/panel/rutinas/{$copy->id}/editar")->with('success', 'Plantilla duplicada. Ajusta lo que necesites.');
    }

    private function templates(): array
    {
        return Routine::templates()
            ->withCount('items')
            ->with('creator:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'type_label' => $r->typeLabel(),
                'goal' => $r->goal,
                'items_count' => $r->items_count,
                'days_count' => $r->isSingle() ? null : $r->items()->distinct()->count('day_of_week'),
                'creator' => $r->creator?->name,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->all();
    }

    private function pendingRequests(): array
    {
        return RoutineRequest::pending()
            ->with('user:id,name,email')
            ->oldest()
            ->limit(100)
            ->get()
            ->map(fn (RoutineRequest $r) => [
                ...$r->toClientArray(),
                'user' => ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email],
            ])->all();
    }

    private function history(array $filters, CarbonImmutable $today)
    {
        return Routine::personal()
            ->with(['member:id,name', 'creator:id,name'])
            ->withCount('items')
            ->withIsCurrent($today)
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('member', fn ($m) => $m
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when(($filters['creador'] ?? null) === 'socio', fn ($q) => $q->whereColumn('created_by', 'member_id'))
            ->when(($filters['creador'] ?? null) === 'coach', fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('created_by')->orWhereColumn('created_by', '!=', 'member_id')))
            ->when($filters['tipo'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Routine $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'type_label' => $r->typeLabel(),
                'member' => $r->member ? ['id' => $r->member->id, 'name' => $r->member->name] : null,
                'author' => $r->authorLabel(),
                'by_member' => $r->isCreatedByMember(),
                'items_count' => $r->items_count,
                'is_current' => (bool) $r->is_current,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);
    }

    /** Socios del gimnasio para buscadores (filtrados por TenantScope). */
    public static function members(): array
    {
        return User::query()
            ->where('role', Role::Member)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->all();
    }
}
