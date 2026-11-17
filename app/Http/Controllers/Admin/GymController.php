<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Gyms\CreateGym;
use App\Enums\GymStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGymRequest;
use App\Http\Requests\Admin\UpdateGymRequest;
use App\Models\Gym;
use App\Models\SaasPayment;
use App\Services\Saas\SaasMetrics;
use App\Services\Saas\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Gimnasios de la red: listado, alta rápida, detalle, edición y suspensión. */
class GymController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(GymStatus::class)],
            'billing' => ['nullable', Rule::in(['vencidos', 'por_vencer', 'al_dia'])],
        ]);

        $query = Gym::query()
            ->withCount(['users as members_count' => fn ($q) => $q->where('role', Role::Member)])
            ->with('settings:id,gym_id,primary_color,secondary_color,logo_url');

        if ($search = trim($filters['q'] ?? '')) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('slug', 'like', $like));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        SaasMetrics::applyBillingFilter($query, $filters['billing'] ?? null);

        $gyms = $query->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (Gym $gym) => $this->summary($gym) + [
                'members_count' => $gym->members_count,
                'primary_color' => $gym->settings?->primary_color,
                'logo_url' => $gym->settings?->logo_url,
            ]);

        return Inertia::render('Admin/Gyms/Index', [
            'gyms' => $gyms,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'billing' => $filters['billing'] ?? '',
            ],
            'baseDomain' => config('gympro.base_domain'),
            'reserved' => config('gympro.reserved_subdomains'),
            'referrerOptions' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'defaultFee' => (float) config('gympro.saas_monthly_fee'),
        ]);
    }

    public function store(StoreGymRequest $request, CreateGym $createGym): RedirectResponse
    {
        $data = $request->validated();

        $gym = $createGym->handle(
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'primary_color' => strtolower($data['primary_color']),
                'secondary_color' => strtolower($data['secondary_color']),
                'referred_by_gym_id' => $data['referred_by_gym_id'] ?? null,
            ],
            [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ],
        );

        return redirect()->route('admin.gyms.show', $gym)
            ->with('success', "Gimnasio «{$gym->name}» creado. Registra su primer pago para activarle la licencia.");
    }

    public function show(Gym $gym, SystemHealth $health): Response
    {
        $gym->load('settings');
        // Gym no define la relación inversa; se busca directo
        $referrer = $gym->referred_by_gym_id
            ? Gym::query()->find($gym->referred_by_gym_id, ['id', 'name', 'slug'])
            : null;

        $payments = SaasPayment::query()
            ->where('gym_id', $gym->id)
            ->with('recorder:id,name')
            ->latest('paid_at')->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (SaasPayment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'months' => $p->months,
                'paid_at' => $p->paid_at->toDateString(),
                'covers_until' => $p->covers_until->toDateString(),
                'method' => SaasPayment::METHODS[$p->method] ?? $p->method,
                'notes' => $p->notes,
                'recorded_by' => $p->recorder?->name,
            ]);

        $referrals = Gym::query()->where('referred_by_gym_id', $gym->id)->orderBy('name')->get(['id', 'name', 'slug', 'status']);

        $admins = $gym->users()->where('role', Role::GymAdmin)->orderBy('name')->get(['id', 'name', 'email']);

        $membersCount = $gym->users()->where('role', Role::Member)->count();
        $trainersCount = $gym->users()->where('role', Role::Trainer)->count();

        return Inertia::render('Admin/Gyms/Show', [
            'gym' => $this->summary($gym) + [
                'saas_monthly_fee' => (float) $gym->saas_monthly_fee,
                'saas_discount' => (float) $gym->saas_discount,
                'referred_by_gym_id' => $gym->referred_by_gym_id,
                'referrer' => $referrer?->only(['id', 'name', 'slug']),
                'suspended_at' => $gym->suspended_at?->toIso8601String(),
                'created_at' => $gym->created_at?->toIso8601String(),
                'primary_color' => $gym->settings?->primary_color,
                'secondary_color' => $gym->settings?->secondary_color,
                'logo_url' => $gym->settings?->logo_url,
                'members_count' => $membersCount,
                'trainers_count' => $trainersCount,
                'storage_bytes' => $health->storageByGym()[$gym->id] ?? 0,
            ],
            'admins' => $admins,
            'payments' => $payments,
            'referrals' => $referrals,
            'referrerOptions' => Gym::query()->whereKeyNot($gym->id)->orderBy('name')->get(['id', 'name']),
            'methods' => collect(SaasPayment::METHODS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'baseDomain' => config('gympro.base_domain'),
        ]);
    }

    public function update(UpdateGymRequest $request, Gym $gym): RedirectResponse
    {
        $gym->fill($request->safe()->only(['name', 'saas_monthly_fee', 'saas_discount', 'referred_by_gym_id']));
        $gym->save();

        return back()->with('success', 'Datos del gimnasio actualizados.');
    }

    public function suspend(Request $request, Gym $gym): RedirectResponse
    {
        Gate::authorize('access-saas-admin');

        if ($gym->status !== GymStatus::Suspended) {
            $gym->status = GymStatus::Suspended;
            $gym->suspended_at = now();
            $gym->save(); // invalida el caché del subdominio → 403 inmediato
        }

        return back()->with('success', "«{$gym->name}» quedó suspendido. Su subdominio ya no da servicio.");
    }

    public function activate(Request $request, Gym $gym): RedirectResponse
    {
        Gate::authorize('access-saas-admin');

        if ($gym->status !== GymStatus::Active) {
            $gym->status = GymStatus::Active;
            $gym->suspended_at = null;
            $gym->save();
        }

        return back()->with('success', "«{$gym->name}» está activo de nuevo.");
    }

    /** Campos comunes del listado y del detalle. */
    private function summary(Gym $gym): array
    {
        return [
            'id' => $gym->id,
            'name' => $gym->name,
            'slug' => $gym->slug,
            'host' => $gym->slug.'.'.config('gympro.base_domain'),
            'status' => $gym->status->value,
            'saas_paid_until' => $gym->saas_paid_until?->toDateString(),
            'billing' => SaasMetrics::billingState($gym),
            'net_fee' => SaasMetrics::netFee($gym),
        ];
    }
}
