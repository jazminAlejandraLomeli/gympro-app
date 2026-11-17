<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Services\Saas\SystemHealth;
use Inertia\Inertia;
use Inertia\Response;

/** Salud del sistema: cifras de la red, servicios y almacenamiento. */
class SystemController extends Controller
{
    public function index(SystemHealth $health): Response
    {
        $storage = $health->storageByGym();

        $gyms = Gym::query()->orderBy('name')->get(['id', 'name', 'slug', 'status'])
            ->map(fn (Gym $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'status' => $g->status->value,
                'bytes' => $storage[$g->id] ?? 0,
            ])
            ->sortByDesc('bytes')
            ->values();

        return Inertia::render('Admin/System', [
            'figures' => $health->figures(),
            'checks' => $health->checks(),
            'storage' => $gyms,
            'storageTotal' => array_sum($storage),
            'storageCachedMinutes' => intdiv(SystemHealth::STORAGE_TTL, 60),
            'checkedAt' => now()->toIso8601String(),
        ]);
    }
}
