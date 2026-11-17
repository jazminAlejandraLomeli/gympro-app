<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\Routine;
use App\Models\RoutineRequest;
use App\Models\User;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWriter;
use Carbon\CarbonImmutable;

/**
 * Módulo B: biblioteca de ejercicios (sin video), dos plantillas
 * semanales, rutinas personales del socio demo (semanal y de un día del
 * coach + una que armó él mismo) y dos solicitudes pendientes.
 * Corre dentro del gimnasio (DatabaseSeeder → runAs).
 */
class RoutineDemoSeeder
{
    /** [nombre, grupo, equipo, dificultad, objetivo, cómo se hace, notas] */
    private const EXERCISES = [
        ['Press de banca con barra', 'pecho', 'barra', 'intermedio', 'Fuerza de pecho y tríceps', 'Baja la barra al pecho controlando y empuja sin despegar la espalda de la banca.', 'Usa siempre un compañero o los topes de seguridad con cargas altas.'],
        ['Aperturas con mancuernas', 'pecho', 'mancuernas', 'principiante', 'Estirar y definir el pecho', 'Codos ligeramente flexionados; abre hasta sentir el estiramiento del pecho.', 'No bajes más allá de la línea de los hombros.'],
        ['Lagartijas', 'pecho', 'peso_corporal', 'principiante', 'Fuerza de empuje sin equipo', 'Manos al ancho de hombros, cuerpo recto, baja hasta casi tocar el piso.', 'Si cuesta, apoya las rodillas.'],
        ['Jalón al pecho en polea', 'espalda', 'polea', 'principiante', 'Espalda ancha y postura', 'Lleva la barra a la parte alta del pecho juntando los omóplatos.', 'No jales con los brazos ni te balancees hacia atrás.'],
        ['Remo sentado en polea', 'espalda', 'polea', 'principiante', 'Grosor de espalda media', 'Espalda recta; jala hacia el abdomen y regresa despacio.', 'Mantén el pecho arriba durante todo el movimiento.'],
        ['Peso muerto rumano', 'espalda', 'barra', 'avanzado', 'Cadena posterior: femorales y glúteos', 'Cadera hacia atrás, barra pegada a las piernas, espalda neutra.', 'Si sientes la espalda baja, reduce el peso.'],
        ['Press militar con mancuernas', 'hombros', 'mancuernas', 'intermedio', 'Fuerza de hombros', 'Sentado, empuja por encima de la cabeza sin arquear la espalda baja.', 'Aprieta el abdomen para proteger la zona lumbar.'],
        ['Elevaciones laterales', 'hombros', 'mancuernas', 'principiante', 'Hombros más anchos', 'Sube las mancuernas hasta la altura de los hombros, sin impulso.', 'Mejor poco peso y buena técnica.'],
        ['Curl de bíceps con barra', 'biceps', 'barra', 'principiante', 'Brazos más fuertes', 'Codos fijos a los costados; sube y baja en 3 segundos.', 'No balancees el torso.'],
        ['Extensión de tríceps en polea', 'triceps', 'polea', 'principiante', 'Definir la parte trasera del brazo', 'Codos pegados al cuerpo; extiende por completo los brazos.', 'Controla la subida, no dejes que la polea te jale.'],
        ['Sentadilla con barra', 'piernas', 'barra', 'intermedio', 'Fuerza de piernas y glúteos', 'Pies al ancho de hombros, baja hasta que los muslos queden paralelos.', 'Rodillas en la dirección de las puntas de los pies.'],
        ['Prensa de piernas', 'piernas', 'maquina', 'principiante', 'Volumen de cuádriceps', 'No bloquees las rodillas al extender; baja hasta 90 grados.', 'Mantén la espalda baja pegada al respaldo.'],
        ['Zancadas con mancuernas', 'piernas', 'mancuernas', 'intermedio', 'Equilibrio y fuerza unilateral', 'Da un paso largo y baja hasta que ambas rodillas formen 90 grados.', 'La rodilla de enfrente no debe pasar la punta del pie.'],
        ['Hip thrust con barra', 'gluteos', 'barra', 'intermedio', 'Glúteos fuertes y firmes', 'Espalda alta en la banca; aprieta glúteos arriba un segundo.', 'Usa un cojín en la barra para no lastimar la cadera.'],
        ['Puente de glúteo con liga', 'gluteos', 'liga', 'principiante', 'Activar glúteos antes de pierna', 'Acostado, liga arriba de las rodillas; sube la cadera abriendo las rodillas.', 'Ideal como calentamiento.'],
        ['Elevación de talones de pie', 'pantorrillas', 'maquina', 'principiante', 'Pantorrillas fuertes', 'Sube lo más alto posible y baja hasta estirar el talón.', 'Haz una pausa arriba en cada repetición.'],
        ['Plancha frontal', 'abdomen', 'peso_corporal', 'principiante', 'Abdomen firme y espalda protegida', 'Cuerpo en línea recta, abdomen y glúteos apretados.', 'No dejes caer la cadera.'],
        ['Swing con kettlebell', 'cuerpo_completo', 'kettlebell', 'avanzado', 'Potencia y cardio', 'Impulsa con la cadera, no con los brazos; la pesa sube a la altura del pecho.', 'Aprende primero el movimiento de bisagra de cadera.'],
        ['Caminadora inclinada', 'cardio', 'maquina', 'principiante', 'Quemar grasa y resistencia', 'Inclinación de 8 a 12 % a paso rápido, sin sujetarte.', 'Hidrátate bien.'],
    ];

    public function seedGym(Gym $gym): void
    {
        $coach = User::where('role', Role::Trainer)->orderBy('id')->first();

        $exercises = [];
        foreach (self::EXERCISES as [$name, $group, $equipment, $difficulty, $objective, $instructions, $notes]) {
            $exercise = new Exercise([
                'name' => $name, 'muscle_group' => $group, 'equipment' => $equipment, 'difficulty' => $difficulty,
                'objective' => $objective, 'instructions' => $instructions, 'notes' => $notes,
            ]);
            $exercise->created_by = $coach?->id;
            $exercise->save();
            $exercises[$name] = $exercise->id;
        }

        $item = fn (?int $day, string $name, int $sets, string $reps, ?float $kg, int $rest, ?string $notes = null) => [
            'day_of_week' => $day, 'exercise_id' => $exercises[$name], 'sets' => $sets,
            'reps' => $reps, 'weight_kg' => $kg, 'rest_seconds' => $rest, 'notes' => $notes,
        ];

        $writer = app(RoutineWriter::class);
        $assigner = app(RoutineAssigner::class);
        $today = CarbonImmutable::now('America/Mexico_City');

        // Plantilla 1: cuerpo completo, 3 días (L, X, V)
        $fullBody = $this->routine(Routine::TYPE_WEEKLY, 'Cuerpo completo principiante', 'Acondicionamiento general', 'Calienta 10 minutos antes de empezar.', $coach, template: true);
        $writer->save($fullBody, [], [
            $item(1, 'Sentadilla con barra', 3, '10-12', 30, 90, 'Empieza solo con la barra si es tu primera vez.'),
            $item(1, 'Press de banca con barra', 3, '10-12', 25, 90),
            $item(1, 'Jalón al pecho en polea', 3, '12', 30, 60),
            $item(1, 'Plancha frontal', 3, '30 s', null, 45),
            $item(3, 'Prensa de piernas', 3, '12', 60, 90),
            $item(3, 'Press militar con mancuernas', 3, '10', 8, 75),
            $item(3, 'Remo sentado en polea', 3, '12', 30, 60),
            $item(3, 'Caminadora inclinada', 1, '15 min', null, 0),
            $item(5, 'Peso muerto rumano', 3, '10', 30, 90, 'Prioriza la técnica sobre el peso.'),
            $item(5, 'Aperturas con mancuernas', 3, '12', 8, 60),
            $item(5, 'Curl de bíceps con barra', 3, '12', 15, 60),
            $item(5, 'Extensión de tríceps en polea', 3, '12', 15, 60),
        ]);

        // Plantilla 2: hipertrofia dividida, 5 días (L a V)
        $split = $this->routine(Routine::TYPE_WEEKLY, 'Hipertrofia 5 días', 'Ganar masa muscular', 'Sube el peso cuando completes todas las repeticiones.', $coach, template: true);
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

        $demo = User::where('role', Role::Member)->where('email', 'socio@demo.test')->first();
        $others = User::where('role', Role::Member)->where('email', '!=', 'socio@demo.test')->orderBy('id')->limit(4)->get();

        if ($demo) {
            // Semanal personal (copia de la plantilla, hecha por el coach)
            $weekly = $writer->duplicate($split, $demo, $coach, 'Hipertrofia 5 días · '.strtok($demo->name, ' '));
            $assigner->assignWeekly($weekly, [$demo->id], $today->subDays(14), $coach);

            // De un día para hoy, hecha por el coach (tiene prioridad sobre la semanal)
            $session = $this->routine(Routine::TYPE_SINGLE, 'Sesión de piernas y glúteo', 'Reforzar tren inferior', 'Hoy enfócate en la técnica, no en el peso.', $coach, member: $demo);
            $writer->save($session, [], [
                $item(null, 'Puente de glúteo con liga', 2, '15', null, 30, 'Calentamiento.'),
                $item(null, 'Sentadilla con barra', 4, '8-10', 40, 120),
                $item(null, 'Zancadas con mancuernas', 3, '10 por pierna', 10, 90),
                $item(null, 'Hip thrust con barra', 4, '10', 50, 90, 'Pausa de 1 segundo arriba.'),
                $item(null, 'Elevación de talones de pie', 3, '15', 30, 45),
            ]);
            $assigner->assignForDay($session, [$demo->id], $today, $coach);

            // Una que armó él mismo (hace unos días)
            $own = $this->routine(Routine::TYPE_SINGLE, 'Mi cardio rápido', 'Sudar en 30 minutos', null, $demo, member: $demo);
            $writer->save($own, [], [
                $item(null, 'Swing con kettlebell', 4, '15', 12, 45),
                $item(null, 'Lagartijas', 3, '12', null, 45),
                $item(null, 'Caminadora inclinada', 1, '15 min', null, 0),
            ]);
            $assigner->assignForDay($own, [$demo->id], $today->subDays(3), $demo);
        }

        // Otros socios: dos con rutina del coach y dos solicitudes pendientes
        foreach ($others->slice(0, 2) as $member) {
            $copy = $writer->duplicate($fullBody, $member, $coach);
            $assigner->assignWeekly($copy, [$member->id], $today->subDays(7), $coach);
        }

        $requests = [
            [Routine::TYPE_WEEKLY, 'Quiero bajar de peso y tonificar, puedo venir 4 días.', 4],
            [Routine::TYPE_SINGLE, 'Hoy solo tengo 40 minutos, quiero trabajar espalda.', null],
        ];
        foreach ($others->slice(2, 2)->values() as $i => $member) {
            [$kind, $goal, $days] = $requests[$i];
            $request = new RoutineRequest(['kind' => $kind, 'goal' => $goal, 'days_per_week' => $days]);
            $request->user_id = $member->id;
            $request->created_at = now()->subHours(3 + $i * 20);
            $request->save();
        }
    }

    private function routine(string $type, string $name, ?string $goal, ?string $notes, ?User $author, bool $template = false, ?User $member = null): Routine
    {
        $routine = new Routine(['name' => $name, 'type' => $type, 'goal' => $goal, 'notes' => $notes]);
        $routine->forceFill([
            'is_template' => $template,
            'member_id' => $template ? null : $member?->id,
            'created_by' => $author?->id,
        ])->save();

        return $routine;
    }
}
