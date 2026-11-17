<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Saas\LogReader;
use App\Services\Saas\SaasMetrics;
use App\Services\Saas\SystemHealth;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Resumen ejecutivo del SaaS: cifras clave, MRR y alertas. */
class DashboardController extends Controller
{
    public function __invoke(SaasMetrics $metrics, SystemHealth $health, LogReader $logs): Response
    {
        $mrr = $metrics->mrr();
        $overdue = $metrics->overdue();
        $expiring = $metrics->expiringSoon();
        $failedJobs = $health->failedJobsCount();

        $since = now()->subDay();
        $recentErrors = collect($logs->latest())
            ->filter(fn ($e) => in_array($e['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true))
            ->filter(fn ($e) => $e['date'] && Carbon::parse($e['date'])->gte($since))
            ->count();

        $alerts = [];
        if ($overdue->isNotEmpty()) {
            $alerts[] = ['tone' => 'red', 'title' => "{$overdue->count()} gimnasio(s) con licencia vencida", 'href' => '/admin/gimnasios?billing=vencidos'];
        }
        if ($expiring->isNotEmpty()) {
            $alerts[] = ['tone' => 'amber', 'title' => "{$expiring->count()} gimnasio(s) vencen en los próximos 7 días", 'href' => '/admin/gimnasios?billing=por_vencer'];
        }
        if ($failedJobs > 0) {
            $alerts[] = ['tone' => 'red', 'title' => "{$failedJobs} trabajo(s) en cola fallidos", 'href' => '/admin/errores'];
        }
        if ($recentErrors > 0) {
            $alerts[] = ['tone' => 'amber', 'title' => "{$recentErrors} error(es) registrados en las últimas 24 h", 'href' => '/admin/errores'];
        }

        $gymRow = fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'slug' => $g->slug,
            'saas_paid_until' => $g->saas_paid_until?->toDateString(),
            'net_fee' => SaasMetrics::netFee($g),
        ];

        return Inertia::render('Admin/Dashboard', [
            'figures' => $health->figures() + [
                'mrr' => $mrr,
                'collected_this_month' => $metrics->collectedThisMonth(),
                'failed_jobs' => $failedJobs,
                'recent_errors' => $recentErrors,
            ],
            'alerts' => $alerts,
            'overdue' => $overdue->take(8)->map($gymRow)->values(),
            'expiring' => $expiring->take(8)->map($gymRow)->values(),
            'revenue' => array_slice($metrics->monthlyRevenue(), -6),
        ]);
    }
}
