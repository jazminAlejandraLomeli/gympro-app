<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaasPayment;
use App\Services\Saas\SaasMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Finanzas del SaaS: MRR, historial de pagos, ingresos por mes y proyección. */
class FinanceController extends Controller
{
    public function index(Request $request, SaasMetrics $metrics): Response
    {
        $mrr = $metrics->mrr();

        $gymRow = fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'slug' => $g->slug,
            'saas_paid_until' => $g->saas_paid_until?->toDateString(),
            'net_fee' => SaasMetrics::netFee($g),
        ];

        $payments = SaasPayment::query()
            ->with(['gym:id,name,slug', 'recorder:id,name'])
            ->latest('paid_at')->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SaasPayment $p) => [
                'id' => $p->id,
                'gym' => $p->gym?->only(['id', 'name', 'slug']),
                'amount' => (float) $p->amount,
                'months' => $p->months,
                'paid_at' => $p->paid_at->toDateString(),
                'covers_until' => $p->covers_until->toDateString(),
                'method' => SaasPayment::METHODS[$p->method] ?? $p->method,
                'notes' => $p->notes,
                'recorded_by' => $p->recorder?->name,
            ]);

        return Inertia::render('Admin/Finance', [
            'mrr' => $mrr,
            'arr' => round($mrr * 12, 2),
            'collectedThisMonth' => $metrics->collectedThisMonth(),
            'revenue' => $metrics->monthlyRevenue(12),
            'projection' => $metrics->projection($mrr),
            'expiring' => $metrics->expiringSoon()->map($gymRow)->values(),
            'overdue' => $metrics->overdue()->map($gymRow)->values(),
            'payments' => $payments,
        ]);
    }
}
