<?php

namespace App\Http\Requests\Progress;

use App\Models\User;

/** El socio registra sus propias medidas. */
class StoreOwnMeasurementRequest extends MeasurementRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('use-member-portal');
    }

    public function member(): User
    {
        return $this->user();
    }
}
