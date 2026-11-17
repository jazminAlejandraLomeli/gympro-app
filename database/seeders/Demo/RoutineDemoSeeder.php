<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Routine;
use App\Models\User;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWeekWriter;
use Carbon\CarbonImmutable;

/**
 * Módulo B: catálogo de ejercicios (sin clip), dos rutinas completas y
 * sus asignaciones. Corre dentro del gimnasio (DatabaseSeeder → runAs).
 */
class RoutineDemoSeeder
{
    private const EXERCISES = [
        ['Press de banca con barra', 'pecho', 'Baja la barra al pecho controlando y empuja sin despegar la espalda de la banca.'],
        ['Aperturas con mancuernas', 'pecho', 'Codos ligeramente flexionados; abre hasta sentir el estiramiento del pecho.'],
        ['Jalón al pecho en polea', 'espalda', 'Lleva la barra a la parte alta del pecho juntando los omóplatos.'],
        ['Remo sentado en polea', 'espalda', 'Espalda recta; jala hacia el abdomen y regresa despacio.'],
        ['Peso muerto rumano', 'espalda', 'Cadera hacia atrás, barra pegada a las piernas, espalda neutra.'],
        ['Press militar con mancuernas', 'hombros', 'Sentado, empuja por encima de la cabeza sin arquear la espalda baja.'],
        ['Elevaciones laterales', 'hombros', 'Sube las mancuernas hasta la altura de los hombros, sin impulso.'],
        ['Curl de bíceps con barra', 'biceps', 'Codos fijos a los costados; sube y baja en 3 segundos.'],
        ['Extensión de tríceps en polea', 'triceps', 'Codos pegados al cuerpo; extiende por completo los brazos.'],
        ['Sentadilla con barra', 'piernas', 'Pies al ancho de hombros, baja hasta que los muslos queden paralelos.'],
        ['Prensa de piernas', 'piernas', 'No bloquees las rodillas al extender; baja hasta 90 grados.'],
        ['Hip thrust con barra', 'gluteos', 'Espalda alta en la banca; aprieta glúteos arriba un segundo.'],
        ['Elevación de talones de pie', 'pantorrillas', 'Sube lo más alto posible y baja hasta estirar el talón.'],
        ['Plancha frontal', 'abdomen', 'Cuerpo en línea recta, abdomen y glúteos apretados.'],
        ['Caminadora inclinada', 'cardio', 'Inclinación de 8 a 12 % a paso rápido, sin sujetarte.'],
    ];

    public function seedGym(Gym $gym): void
    {
        $coach = User::where('role', Role::Trainer)->orderBy('id')->first();

        $exercises = [];
        foreach (self::EXERCISES as [$name, $group, $instructions]) {
            $exercise = new Exercise(['name' => $name, 'muscle_group' => $group, 'instructions' => $instructions]);
            $exercise->created_by = $coach?->id;
            $exercise->save();
            $exercises[$name] = $exercise->id;
        }

        $item = fn (int $day, string $name, int $sets, string $reps, ?float $kg, int $rest) => [
            'day_of_week' => $day, 'exercise_id' => $exercises[$name], 'sets' => $sets,
            'reps' => $reps, 'weight_kg' => $kg, 'rest_seconds' => $rest,
        ];

        $writer = app(RoutineWeekWriter::class);

        // Rutina 1: cuerpo completo, 3 días (L, X, V)
        $fullBody = $this->routine('Cuerpo completo principiante', 'Acondicionamiento general', 'Calienta 10 minutos antes de empezar.', $coach);
        $writer->save($fullBody, [], [
            $item(1, 'Sentadilla con barra', 3, '10-12', 30, 90),
            $item(1, 'Press de banca con barra', 3, '10-12', 25, 90),
            $item(1, 'Jalón al pecho en polea', 3, '12', 30, 60),
            $item(1, 'Plancha frontal', 3, '30 s', null, 45),
            $item(3, 'Prensa de piernas', 3, '12', 60, 90),
            $item(3, 'Press militar con mancuernas', 3, '10', 8, 75),
            $item(3, 'Remo sentado en polea', 3, '12', 30, 60),
            $item(3, 'Caminadora inclinada', 1, '15 min', null, 0),
            $item(5, 'Peso muerto rumano', 3, '10', 30, 90),
            $item(5, 'Aperturas con mancuernas', 3, '12', 8, 60),
            $item(5, 'Curl de bíceps con barra', 3, '12', 15, 60),
            $item(5, 'Extensión de tríceps en polea', 3, '12', 15, 60),
        ]);

        // Rutina 2: hipertrofia dividida, 5 días (L a V)
        $split = $this->routine('Hipertrofia 5 días', 'Ganar masa muscular', 'Sube el peso cuando completes todas las repeticiones.', $coach);
        $writer->save($split, [], [
            $item(1, 'Press de banca con barra', 4, '8-10', 50, 120),
            $item(1, 'Aperturas con mancuernas', 3, '12', 12, 60),
            $item(1, 'Extensión de tríceps en polea', 3, '12-15', 20, 60),
            $item(2, 'Jalón al pecho en polea', 4, '10', 45, 90),
            $item(2, 'Remo sentado en polea', 4, '10', 45, 90),
            $item(2, 'Curl de bíceps con barra', 3, '10-12', 20, 60),
            $item(3, 'Sentadilla con barra', 4, '8', 60, 120),
            $item(3, 'Prensa de piernas', 3, '12', 120, 90),
            $item(3, 'Elevación de talones de pie', 4, '15', 40, 45),
            $item(4, 'Press militar con mancuernas', 4, '10', 14, 90),
            $item(4, 'Elevaciones laterales', 3, '15', 6, 45),
            $item(4, 'Plancha frontal', 3, '45 s', null, 45),
            $item(5, 'Peso muerto rumano', 4, '8', 60, 120),
            $item(5, 'Hip thrust con barra', 4, '10', 60, 90),
            $item(5, 'Caminadora inclinada', 1, '20 min', null, 0),
        ]);

        $assigner = app(RoutineAssigner::class);
        $today = CarbonImmutable::now('America/Mexico_City');

        $demo = User::where('role', Role::Member)->where('email', 'socio@demo.test')->first();
        $others = User::where('role', Role::Member)->where('email', '!=', 'socio@demo.test')->orderBy('id')->limit(3)->pluck('id')->all();

        if ($demo) {
            $assigner->assign($split, [$demo->id], $today, $coach);
        }
        if ($others) {
            $assigner->assign($fullBody, $others, $today, $coach);
        }
    }

    private function routine(string $name, string $goal, string $notes, ?User $coach): Routine
    {
        $routine = new Routine(['name' => $name, 'goal' => $goal, 'notes' => $notes]);
        $routine->created_by = $coach?->id;
        $routine->save();

        return $routine;
    }
}
