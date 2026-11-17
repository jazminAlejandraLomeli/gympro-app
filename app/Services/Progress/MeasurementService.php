<?php

namespace App\Services\Progress;

use App\Models\BodyMeasurement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Evolución física (Módulo D): historial, línea base, logros,
 * series para gráficas y el recordatorio de cada 30 días.
 */
class MeasurementService
{
    /** Días entre valoraciones antes de recordar. */
    public const REMINDER_DAYS = 30;

    /** Diferencias menores a esto se consideran "sin cambio". */
    private const EPSILON = 0.05;

    /** @return Collection<int, BodyMeasurement> de la más antigua a la más reciente */
    public function history(User $user): Collection
    {
        return BodyMeasurement::query()
            ->where('user_id', $user->id)
            ->orderBy('measured_on')
            ->orderBy('id')
            ->get();
    }

    /** Todo lo que necesita la pantalla de progreso / la ficha del socio. */
    public function report(User $user): array
    {
        $history = $this->history($user);
        $baseline = $history->first();
        $latest = $history->last();

        return [
            'baseline' => $baseline ? $this->present($baseline) : null,
            'latest' => $latest ? $this->present($latest) : null,
            'achievements' => $this->achievements($baseline, $latest),
            'chart' => $history->map(fn (BodyMeasurement $m) => [
                'date' => $m->measured_on,
                ...$m->only(BodyMeasurement::METRICS),
            ])->values()->all(),
            'history' => $history->reverse()->map(fn (BodyMeasurement $m) => $this->present($m))->values()->all(),
            'reminder' => $this->reminderFor($latest),
            'metrics' => BodyMetric::options(),
        ];
    }

    public function present(BodyMeasurement $m): array
    {
        return [
            'id' => $m->id,
            'measured_on' => $m->measured_on,
            ...$m->only(BodyMeasurement::METRICS),
            'notes' => $m->notes,
            'recorded_by_self' => $m->recorded_by === null || $m->recorded_by === $m->user_id,
        ];
    }

    /**
     * Logros contra la línea base: una tarjeta por medida que cambió.
     * `positive` = el cambio va en la dirección deseada para esa medida.
     */
    public function achievements(?BodyMeasurement $baseline, ?BodyMeasurement $latest): array
    {
        if (! $baseline || ! $latest || $baseline->is($latest)) {
            return [];
        }

        $cards = [];
        foreach (BodyMetric::cases() as $metric) {
            $from = $baseline->{$metric->value};
            $to = $latest->{$metric->value};

            if ($from === null || $to === null) {
                continue;
            }

            $delta = round($to - $from, 1);
            if (abs($delta) < self::EPSILON) {
                continue;
            }

            $wentDown = $delta < 0;
            $amount = self::number(abs($delta));

            $cards[] = [
                'metric' => $metric->value,
                'label' => $metric->label(),
                'unit' => $metric->unit(),
                'from' => $from,
                'to' => $to,
                'delta' => $delta,
                'deltaLabel' => ($wentDown ? '-' : '+').$amount.' '.$metric->unit(),
                'positive' => $wentDown === $metric->lowerIsBetter(),
                'text' => $metric->sentence($wentDown, $amount),
            ];
        }

        // Primero los logros, luego los demás cambios
        usort($cards, fn ($a, $b) => $b['positive'] <=> $a['positive']);

        return $cards;
    }

    /** Alerta amable si no hay valoraciones o la última tiene 30+ días. */
    public function reminder(User $user): ?array
    {
        $latest = BodyMeasurement::query()
            ->where('user_id', $user->id)
            ->orderByDesc('measured_on')
            ->first();

        return $this->reminderFor($latest);
    }

    public function reminderFor(?BodyMeasurement $latest): ?array
    {
        if (! $latest) {
            return [
                'reason' => 'never',
                'daysSince' => null,
                'lastMeasuredOn' => null,
                'title' => 'Registra tu punto de partida',
                'message' => 'Anota tu peso y medidas de hoy. Será tu línea base para ver cuánto avanzas.',
            ];
        }

        $days = self::daysSince($latest->measured_on);
        if ($days < self::REMINDER_DAYS) {
            return null;
        }

        return [
            'reason' => 'due',
            'daysSince' => $days,
            'lastMeasuredOn' => $latest->measured_on,
            'title' => '¡Es hora de tu valoración mensual!',
            'message' => "Han pasado {$days} días desde tu última medición. Registra tus medidas para ver tu progreso.",
        ];
    }

    public static function daysSince(string $date): int
    {
        $tz = config('app.timezone');

        return (int) CarbonImmutable::parse(substr($date, 0, 10), $tz)
            ->diffInDays(CarbonImmutable::now($tz)->startOfDay());
    }

    /** Fecha a partir de la cual una valoración ya no está "pendiente". */
    public static function pendingCutoff(): string
    {
        return CarbonImmutable::now(config('app.timezone'))->subDays(self::REMINDER_DAYS - 1)->toDateString();
    }

    /** 12.0 → "12", 1.5 → "1.5" */
    public static function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
