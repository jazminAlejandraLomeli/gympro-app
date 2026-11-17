<?php

namespace App\Enums;

enum AnnouncementType: string
{
    /** Horarios, mantenimiento, cierres: se muestra sobrio. */
    case Operativo = 'operativo';

    /** Ofertas y promociones: se destaca con el color primario. */
    case Promocion = 'promocion';

    public function label(): string
    {
        return match ($this) {
            self::Operativo => 'Operativo',
            self::Promocion => 'Promoción',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
