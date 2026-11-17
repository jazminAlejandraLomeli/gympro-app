<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrada, salida o ajuste manual de existencias de un producto. */
class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    public function rules(): array
    {
        $manual = [StockMovementType::Entrada, StockMovementType::Salida, StockMovementType::Ajuste];
        $isAdjustment = $this->input('type') === StockMovementType::Ajuste->value;

        return [
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, $manual))],
            'quantity' => ['required', 'integer', $isAdjustment ? 'min:0' : 'min:1', 'max:100000'],
            // Salidas y ajustes siempre llevan motivo
            'note' => [Rule::requiredIf(in_array($this->input('type'), ['salida', 'ajuste'], true)), 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Elige el tipo de movimiento.',
            'type.in' => 'El tipo de movimiento no es válido.',
            'quantity.required' => 'Indica la cantidad.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos :min.',
            'quantity.max' => 'La cantidad es demasiado alta.',
            'note.required' => 'Escribe el motivo (por ejemplo: merma, caducidad, conteo físico).',
            'note.max' => 'La nota no puede pasar de 255 caracteres.',
        ];
    }

    public function movementType(): StockMovementType
    {
        return StockMovementType::from($this->validated('type'));
    }
}
