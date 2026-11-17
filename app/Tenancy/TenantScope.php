<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtro automático por gym_id. Falla "cerrado": si no hay gimnasio
 * activo filtra por gym_id IS NULL, que en tablas operativas (gym_id
 * NOT NULL) devuelve cero filas en lugar de datos de todos.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->bypassing()) {
            return;
        }

        $column = $model->qualifyColumn('gym_id');

        if ($context->check()) {
            $builder->where($column, $context->id());
        } else {
            $builder->whereNull($column);
        }
    }
}
