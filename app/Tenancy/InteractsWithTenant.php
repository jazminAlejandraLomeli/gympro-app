<?php

namespace App\Tenancy;

use App\Jobs\Middleware\RunInTenant;

/**
 * Para jobs en cola que tocan datos de un gimnasio. El worker no tiene
 * subdominio, así que el job guarda el gym_id al despacharse y lo
 * restaura al ejecutarse.
 *
 *   class CompressExerciseClip implements ShouldQueue {
 *       use Queueable, InteractsWithTenant;
 *       public function __construct(public int $clipId) { $this->captureTenant(); }
 *   }
 *
 * Si el job define su propio middleware(), incluya `new RunInTenant`.
 */
trait InteractsWithTenant
{
    public ?int $gymId = null;

    public function captureTenant(): static
    {
        $this->gymId = app(TenantContext::class)->id()
            ?? throw new \LogicException('Despacha este job dentro del contexto de un gimnasio.');

        return $this;
    }

    public function middleware(): array
    {
        return [new RunInTenant];
    }
}
