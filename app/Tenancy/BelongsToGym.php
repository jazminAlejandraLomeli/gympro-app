<?php

namespace App\Tenancy;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Úsalo en TODO modelo con columna gym_id.
 *
 *  - Aplica TenantScope en cada consulta (incluye route model binding,
 *    así que /socios/{id} de otro gimnasio responde 404: anti-IDOR).
 *  - Al crear, rellena gym_id con el gimnasio activo.
 *  - Impide crear o mover filas a otro gimnasio desde un request web.
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            $context = app(TenantContext::class);

            if ($context->check()) {
                $model->gym_id ??= $context->id();

                if ((int) $model->gym_id !== $context->id()) {
                    throw new TenantViolation('No se puede crear un registro para otro gimnasio.');
                }

                return;
            }

            // Sin gimnasio activo, en web solo el super_admin (bypass).
            // Consola (seeders, comandos) no está en modo estricto.
            if ($context->strict() && ! $context->bypassing()) {
                throw new TenantViolation('No hay gimnasio activo para crear '.class_basename($model).'.');
            }
        });

        static::updating(function (Model $model) {
            $context = app(TenantContext::class);

            if ($model->isDirty('gym_id') && $context->strict() && ! $context->bypassing()) {
                throw new TenantViolation('No se puede mover un registro a otro gimnasio.');
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
