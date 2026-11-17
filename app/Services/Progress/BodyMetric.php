<?php

namespace App\Services\Progress;

/**
 * Medidas corporales: etiqueta, unidad y hacia dónde es "mejor".
 * La redacción de los logros depende de eso (bajar cintura es logro,
 * subir brazo también).
 */
enum BodyMetric: string
{
    case Weight = 'weight_kg';
    case BodyFat = 'body_fat_pct';
    case Waist = 'waist_cm';
    case Chest = 'chest_cm';
    case Arm = 'arm_cm';
    case Leg = 'leg_cm';

    public function label(): string
    {
        return match ($this) {
            self::Weight => 'Peso',
            self::BodyFat => 'Grasa corporal',
            self::Waist => 'Cintura',
            self::Chest => 'Pecho',
            self::Arm => 'Brazo',
            self::Leg => 'Pierna',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Weight => 'kg',
            self::BodyFat => '%',
            default => 'cm',
        };
    }

    /** true: bajar es el progreso esperado (peso, grasa, cintura). */
    public function lowerIsBetter(): bool
    {
        return in_array($this, [self::Weight, self::BodyFat, self::Waist], true);
    }

    /** Rango válido para capturar [mín, máx]. */
    public function range(): array
    {
        return match ($this) {
            self::Weight => [25, 350],
            self::BodyFat => [2, 70],
            self::Waist => [40, 250],
            self::Chest => [50, 250],
            self::Arm => [10, 80],
            self::Leg => [20, 120],
        };
    }

    /**
     * Frase del logro contra la línea base. $amount es el valor absoluto
     * ya formateado ("12", "1.5").
     */
    public function sentence(bool $went_down, string $amount): string
    {
        return match ($this) {
            self::Weight => $went_down
                ? "¡Has bajado {$amount} kg desde tu inicio!"
                : "Has subido {$amount} kg desde tu inicio.",
            self::BodyFat => $went_down
                ? "¡Bajaste {$amount} puntos de grasa corporal!"
                : "Tu grasa corporal subió {$amount} puntos.",
            self::Waist => $went_down
                ? "¡{$amount} cm menos de cintura!"
                : "Tu cintura aumentó {$amount} cm.",
            self::Chest => $went_down
                ? "Tu pecho se redujo {$amount} cm."
                : "¡Ganaste {$amount} cm de pecho!",
            self::Arm => $went_down
                ? "Tu brazo se redujo {$amount} cm."
                : "¡Ganaste {$amount} cm de brazo!",
            self::Leg => $went_down
                ? "Tu pierna se redujo {$amount} cm."
                : "¡Ganaste {$amount} cm de pierna!",
        };
    }

    /** Para selectores y gráficas en el front. */
    public static function options(): array
    {
        return array_map(fn (self $m) => [
            'key' => $m->value,
            'label' => $m->label(),
            'unit' => $m->unit(),
            'min' => $m->range()[0],
            'max' => $m->range()[1],
        ], self::cases());
    }
}
