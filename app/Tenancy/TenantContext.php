<?php

namespace App\Tenancy;

use App\Models\Gym;
use LogicException;

/**
 * Gimnasio (tenant) del request actual.
 *
 * Se registra como `scoped` en AppServiceProvider: una instancia por
 * request/job, así nunca se "arrastra" un tenant entre peticiones.
 *
 * Estados posibles:
 *  - con gimnasio  → todas las consultas se filtran por su gym_id
 *  - sin gimnasio  → dominio central; solo se ven filas con gym_id NULL
 *                    (es decir: super_admins; datos operativos = nada)
 *  - bypass        → super_admin en el dominio central: sin filtro
 */
class TenantContext
{
    private ?Gym $gym = null;

    private bool $bypass = false;

    /**
     * Modo estricto: prohíbe crear filas sin gimnasio o moverlas de
     * gimnasio. Lo activa ResolveTenant en cada request web; consola
     * (seeders, migraciones) queda en modo libre.
     */
    private bool $strict = false;

    public function set(Gym $gym): void
    {
        $this->gym = $gym;
    }

    public function forget(): void
    {
        $this->gym = null;
        $this->bypass = false;
    }

    public function enforceStrictMode(): void
    {
        $this->strict = true;
    }

    public function strict(): bool
    {
        return $this->strict;
    }

    public function gym(): ?Gym
    {
        return $this->gym;
    }

    public function id(): ?int
    {
        return $this->gym?->getKey();
    }

    public function check(): bool
    {
        return $this->gym !== null;
    }

    /** Desactiva el filtro por gimnasio (solo super_admin). */
    public function enableBypass(): void
    {
        $this->bypass = true;
    }

    public function bypassing(): bool
    {
        return $this->bypass;
    }

    /**
     * Ejecuta código como si el request fuera de ese gimnasio
     * (seeders, comandos, jobs). Restaura el estado anterior al final.
     */
    public function runAs(Gym $gym, callable $callback): mixed
    {
        [$prevGym, $prevBypass] = [$this->gym, $this->bypass];
        $this->gym = $gym;
        $this->bypass = false;

        try {
            return $callback($gym);
        } finally {
            [$this->gym, $this->bypass] = [$prevGym, $prevBypass];
        }
    }

    /** Ejecuta código sin filtro por gimnasio. */
    public function withoutScope(callable $callback): mixed
    {
        $prev = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $prev;
        }
    }

    /**
     * Llave de Redis aislada por gimnasio:
     *   key('member', 15, 'status') → "gym:3:member:15:status"
     */
    public function key(string|int ...$parts): string
    {
        $id = $this->id() ?? throw new LogicException('No hay gimnasio activo para construir la llave de caché.');

        return implode(':', ['gym', $id, ...$parts]);
    }
}
