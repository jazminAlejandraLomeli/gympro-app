<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\StoreMemberRequest;
use App\Http\Requests\Membership\UpdateMemberRequest;
use App\Models\Attendance;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Services\Membership\MemberRegistration;
use App\Services\Membership\MemberSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Socios del gimnasio (recepción). Todo pasa por el filtro del gimnasio activo. */
class MemberController extends Controller
{
    public function index(Request $request, MemberSearch $search): Response
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 80, '');
        $status = in_array($request->query('estado'), array_keys(Membership::STATUSES), true) ? $request->query('estado') : null;

        $query = Membership::query()
            ->select('memberships.*')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->with('user:id,name,email')
            ->orderBy('users.name');

        if ($q !== '') {
            $query->whereIn('memberships.id', $search->ids($q));
        }

        if ($status) {
            $query->withStatus($status);
        }

        return Inertia::render('Gym/Members/Index', [
            'members' => $query->paginate(20)->withQueryString()->through(fn (Membership $m) => self::row($m)),
            'filters' => ['q' => $q, 'estado' => $status],
            'counts' => [
                'all' => Membership::query()->count(),
                'active' => Membership::query()->withStatus('active')->count(),
                'expiring' => Membership::query()->withStatus('expiring')->count(),
                'expired' => Membership::query()->withStatus('expired')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Gym/Members/Create', [
            'methods' => MemberPayment::METHODS,
        ]);
    }

    public function store(StoreMemberRequest $request, MemberRegistration $registration): RedirectResponse
    {
        [$membership, $password] = $registration->register($request->validated(), $request->user());

        // La contraseña se muestra UNA vez (no se guarda en claro en ningún lado)
        return redirect("/panel/socios/{$membership->id}")
            ->with('success', 'Socio registrado.')
            ->with('new_member_credentials', [
                'membership_id' => $membership->id,
                'email' => $membership->user->email,
                'password' => $password,
            ]);
    }

    public function show(Request $request, Membership $membership): Response
    {
        $membership->load('user:id,name,email,created_at');

        $credentials = $request->session()->get('new_member_credentials');
        if (($credentials['membership_id'] ?? null) !== $membership->id) {
            $credentials = null;
        }

        return Inertia::render('Gym/Members/Show', [
            'member' => [
                ...self::row($membership),
                'plan_name' => $membership->plan_name,
                'member_since' => $membership->user->created_at?->toDateString(),
            ],
            'credentials' => $credentials ? ['email' => $credentials['email'], 'password' => $credentials['password']] : null,
            'payments' => $membership->payments()
                ->with('recorder:id,name')
                ->latest('paid_at')
                ->limit(30)
                ->get()
                ->map(fn (MemberPayment $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'concept' => $p->concept,
                    'method' => MemberPayment::METHODS[$p->method] ?? $p->method,
                    'months_added' => $p->months_added,
                    'credits_added' => $p->credits_added,
                    'paid_at' => $p->paid_at->toIso8601String(),
                    'recorded_by' => $p->recorder?->name,
                ]),
            'attendances' => $membership->attendances()
                ->latest('scanned_at')
                ->limit(30)
                ->get(['id', 'scanned_at', 'method', 'credits_used'])
                ->map(fn (Attendance $a) => [
                    'id' => $a->id,
                    'scanned_at' => $a->scanned_at->toIso8601String(),
                    'method' => $a->method === 'manual' ? 'Manual' : 'QR',
                    'credits_used' => $a->credits_used,
                ]),
            'attendancesThisMonth' => $membership->attendances()->where('scanned_at', '>=', now()->startOfMonth())->count(),
            'methods' => MemberPayment::METHODS,
        ]);
    }

    public function edit(Membership $membership): Response
    {
        $membership->load('user:id,name,email');

        return Inertia::render('Gym/Members/Edit', [
            'member' => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'phone' => $membership->phone,
                'plan_name' => $membership->plan_name,
                'expires_on' => $membership->expires_on?->toDateString(),
                'credits' => $membership->credits,
            ],
        ]);
    }

    public function update(UpdateMemberRequest $request, Membership $membership): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($membership, $data) {
            $membership->user->fill(['name' => $data['name'], 'email' => $data['email']])->save();

            $membership->fill([
                'phone' => $data['phone'] ?? null,
                'plan_name' => $data['plan_name'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'credits' => $data['credits'],
            ]);
            $membership->save();   // invalida Redis
            $membership->searchable(); // nombre/correo pudieron cambiar
        });

        return redirect("/panel/socios/{$membership->id}")->with('success', 'Datos actualizados.');
    }

    /** Por si el socio compartió una captura de su QR o perdió el celular. */
    public function regenerateQr(Membership $membership): RedirectResponse
    {
        $membership->regenerateQrToken();
        $membership->save(); // invalida el token anterior en Redis

        return back()->with('success', 'Se generó un QR nuevo. El anterior ya no funciona.');
    }

    /** Fila de listado (también la usa el escáner para la búsqueda manual). */
    public static function row(Membership $m): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user?->name,
            'email' => $m->user?->email,
            'phone' => $m->phone,
            'plan_name' => $m->plan_name,
            'status' => $m->status(),
            'status_label' => $m->statusLabel(),
            'expires_on' => $m->expires_on?->toDateString(),
            'days_remaining' => $m->daysRemaining(),
            'credits' => $m->credits,
        ];
    }
}
