<?php

namespace App\Enums;

enum StockMovementType: string
{
    /** Compra a proveedor / reabasto (cantidad positiva). */
    case Entrada = 'entrada';

    /** Merma, uso interno, caducidad (cantidad negativa). */
    case Salida = 'salida';

    /** Conteo físico: la cantidad es la diferencia contra el sistema. */
    case Ajuste = 'ajuste';

    /** Venta en mostrador (cantidad negativa). */
    case Venta = 'venta';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Ajuste => 'Ajuste',
            self::Venta => 'Venta',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
