<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Progress\StoreMemberMeasurementRequest;
use App\Models\BodyMeasurement;
use App\Models\User;
use App\Services\Progress\MeasurementService;
use App\Services\Progress\StreakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Panel: valoraciones físicas de los socios (trainer y gym_admin). */
class MeasurementController extends Controller
{
    public function __construct(private MeasurementService $measurements) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $onlyPending = $request->boolean('pendientes');
        $cutoff = MeasurementService::pendingCutoff();

        $lastMeasured = BodyMeasurement::query()
            ->select('measured_on')
            ->whereColumn('body_measurements.user_id', 'users.id')
            ->orderByDesc('measured_on')
            ->limit(1);

        $members = User::query()
            ->where('role', Role::Member)
            ->select(['users.id', 'users.name', 'users.email'])
            ->addSelect(['last_measured_on' => $lastMeasured])
            ->when($search !== '', fn ($q) => $q->where('users.name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($onlyPending, fn ($q) => $q->whereNotExists(
                BodyMeasurement::query()
                    ->selectRaw('1')
                    ->whereColumn('body_measurements.user_id', 'users.id')
                    ->where('measured_on', '>=', $cutoff),
            ))
            ->orderBy('users.name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'lastMeasuredOn' => $u->last_measured_on ? substr($u->last_measured_on, 0, 10) : null,
                'daysSince' => $u->last_measured_on ? MeasurementService::daysSince($u->last_measured_on) : null,
                'pending' => ! $u->last_measured_on || $u->last_measured_on < $cutoff,
            ]);

        return Inertia::render('Gym/Measurements/Index', [
            'members' => $members,
            'filters' => ['q' => $search, 'pendientes' => $onlyPending],
        ]);
    }

    public function show(User $member, StreakService $streaks): Response
    {
        $this->ensureMember($member);

        $streak = $streaks->forMember($member);

        return Inertia::render('Gym/Measurements/Show', [
            'member' => ['id' => $member->id, 'name' => $member->name, 'email' => $member->email],
            'progress' => $this->measurements->report($member),
            'streak' => [
                'streakWeeks' => $streak['streakWeeks'],
                'attendedThisWeek' => $streak['attendedThisWeek'],
                'goal' => $streak['goal'],
            ],
            'today' => $streak['today'],
        ]);
    }

    public function store(StoreMemberMeasurementRequest $request, User $member): RedirectResponse
    {
        $this->ensureMember($member);

        $measurement = new BodyMeasurement($request->measurementData());
        $measurement->user_id = $member->id;
        $measurement->recorded_by = $request->user()->id;
        $measurement->save();

        return back()->with('success', "Valoración de {$member->name} guardada.");
    }

    /** {member} ya viene filtrado por gimnasio; además debe ser socio. */
    private function ensureMember(User $member): void
    {
        abort_unless($member->role === Role::Member, 404);
    }
}
