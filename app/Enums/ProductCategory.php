<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Suplemento = 'suplemento';
    case Bebida = 'bebida';
    case Accesorio = 'accesorio';

    /** Equipamiento del gimnasio: se controla, pero no se vende. */
    case Equipo = 'equipo';

    public function label(): string
    {
        return match ($this) {
            self::Suplemento => 'Suplemento',
            self::Bebida => 'Bebida',
            self::Accesorio => 'Accesorio',
            self::Equipo => 'Equipo',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
