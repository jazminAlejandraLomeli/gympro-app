<?php

namespace App\Services\Membership;

use App\Models\Membership;
use App\Tenancy\TenantContext;
use Throwable;

/**
 * Búsqueda instantánea de socios con Meilisearch (Scout).
 *
 * El índice `memberships` es compartido: SIEMPRE se filtra por gym_id
 * (filterableAttributes en config/scout.php). Si Meilisearch no responde,
 * se cae a un LIKE en la BD para que recepción no se quede sin buscar.
 */
class MemberSearch
{
    public function __construct(private TenantContext $tenant) {}

    /** @return array<int, int> ids de membresías del gimnasio activo */
    public function ids(string $query, int $limit = 1000): array
    {
        $gymId = (int) $this->tenant->id();

        try {
            return Membership::search($query)
                ->where('gym_id', $gymId)
                ->take($limit)
                ->keys()
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (Throwable $e) {
            report($e);

            return $this->fallback($query, $limit);
        }
    }

    private function fallback(string $query, int $limit): array
    {
        $like = '%'.addcslashes($query, '%_\\').'%';

        return Membership::query()
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where(fn ($q) => $q->where('users.name', 'like', $like)
                ->orWhere('users.email', 'like', $like)
                ->orWhere('memberships.phone', 'like', $like))
            ->limit($limit)
            ->pluck('memberships.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
