<?php

namespace App\Http\Requests\Progress;

use App\Enums\Role;
use App\Models\User;

/**
 * Entrenador / gym_admin registra la valoración de un socio. El socio
 * llega por route model binding (ya filtrado por gimnasio: otro gym = 404).
 */
class StoreMemberMeasurementRequest extends MeasurementRequest
{
    public function authorize(): bool
    {
        // Solo socios: cualquier otro usuario se trata como inexistente
        abort_unless($this->member()->role === Role::Member, 404);

        return $this->user()->can('manage-training');
    }

    public function member(): User
    {
        return $this->route('member');
    }
}
