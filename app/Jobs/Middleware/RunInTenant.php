<?php

namespace App\Jobs\Middleware;

use App\Models\Gym;
use App\Tenancy\TenantContext;
use Closure;

/** Ejecuta el job dentro del gimnasio que lo despachó (ver InteractsWithTenant). */
class RunInTenant
{
    public function handle(object $job, Closure $next): mixed
    {
        $gym = Gym::findOrFail($job->gymId);

        $context = app(TenantContext::class);
        $context->enforceStrictMode();

        return $context->runAs($gym, fn () => $next($job));
    }
}
