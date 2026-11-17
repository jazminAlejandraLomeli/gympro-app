<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductCategory;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de productos / equipo. */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    protected function prepareForValidation(): void
    {
        $sku = trim((string) $this->input('sku'));
        $isEquipment = $this->input('category') === ProductCategory::Equipo->value;

        $this->merge([
            'sku' => $sku === '' ? null : mb_strtoupper($sku),
            // El equipamiento del gimnasio nunca se vende
            'is_for_sale' => $isEquipment ? false : $this->boolean('is_for_sale'),
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'price' => $this->input('price') === null || $this->input('price') === '' ? 0 : $this->input('price'),
            'cost' => $this->input('cost') === '' ? null : $this->input('cost'),
        ]);
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $gymId = app(TenantContext::class)->id();

        return [
            'sku' => [
                'nullable', 'string', 'max:40', 'regex:/^[A-Z0-9._-]+$/',
                // unique no pasa por el TenantScope: se filtra a mano
                Rule::unique('products', 'sku')->where('gym_id', $gymId)->ignore($product?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'is_for_sale' => ['boolean'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99', Rule::when($this->boolean('is_for_sale'), 'gt:0')],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Solo al dar de alta: se registra como movimiento de entrada
            'initial_stock' => [Rule::excludeIf($product !== null), 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'Ya tienes un producto con ese SKU.',
            'sku.regex' => 'El SKU solo admite letras, números, punto, guion y guion bajo.',
            'sku.max' => 'El SKU no puede pasar de 40 caracteres.',
            'name.required' => 'Escribe el nombre del producto.',
            'name.max' => 'El nombre no puede pasar de 120 caracteres.',
            'category.required' => 'Elige una categoría.',
            'category.enum' => 'La categoría no es válida.',
            'price.required' => 'Indica el precio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
            'price.gt' => 'Un producto a la venta necesita precio mayor a cero.',
            'price.max' => 'El precio es demasiado alto.',
            'cost.numeric' => 'El costo debe ser un número.',
            'cost.min' => 'El costo no puede ser negativo.',
            'cost.max' => 'El costo es demasiado alto.',
            'min_stock.required' => 'Indica el stock mínimo (puede ser 0).',
            'min_stock.integer' => 'El stock mínimo debe ser un número entero.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'initial_stock.integer' => 'La existencia inicial debe ser un número entero.',
            'initial_stock.min' => 'La existencia inicial no puede ser negativa.',
            'notes.max' => 'Las notas no pueden pasar de 1000 caracteres.',
        ];
    }
}
