<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\ExerciseRequest;
use App\Models\Exercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biblioteca de ejercicios del gimnasio. El alta y la edición abren un
 * panel lateral (?editar={id}); tras crear, el mismo panel queda abierto
 * para subir el video de muestra.
 */
class ExerciseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'grupo' => ['nullable', 'string', Rule::in(array_keys(Exercise::MUSCLE_GROUPS))],
            'equipo' => ['nullable', 'string', Rule::in(array_keys(Exercise::EQUIPMENT))],
            'q' => ['nullable', 'string', 'max:100'],
            'editar' => ['nullable', 'integer'],
        ]);

        $exercises = Exercise::query()
            ->when($filters['grupo'] ?? null, fn ($q, $group) => $q->where('muscle_group', $group))
            ->when($filters['equipo'] ?? null, fn ($q, $equipment) => $q->where('equipment', $equipment))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Exercise $exercise) => $exercise->toClientArray());

        $editing = isset($filters['editar']) ? Exercise::find($filters['editar']) : null;

        return Inertia::render('Gym/Exercises/Index', [
            'exercises' => $exercises,
            'filters' => [
                'grupo' => $filters['grupo'] ?? '',
                'equipo' => $filters['equipo'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
            'editing' => $editing?->toClientArray(),
            'muscleGroups' => Exercise::options(Exercise::MUSCLE_GROUPS),
            'equipment' => Exercise::options(Exercise::EQUIPMENT),
            'difficulties' => Exercise::options(Exercise::DIFFICULTIES),
        ]);
    }

    public function store(ExerciseRequest $request): RedirectResponse
    {
        $exercise = new Exercise($request->validated());
        $exercise->created_by = $request->user()->id;
        $exercise->save();

        // El panel sigue abierto con el ejercicio creado para subir su video
        return redirect("/panel/ejercicios?editar={$exercise->id}")
            ->with('success', "Ejercicio «{$exercise->name}» guardado. Ahora sube su video de muestra.");
    }

    public function update(ExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $exercise->update($request->validated());

        return back()->with('success', 'Ejercicio actualizado.');
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $clip = $exercise->clip_path;
        $exercise->delete(); // sus renglones en rutinas se borran en cascada

        if ($clip) {
            Storage::disk('public')->delete($clip);
        }

        return redirect('/panel/ejercicios')->with('success', 'Ejercicio eliminado.');
    }

    /** @return list<array{value: string, label: string}> */
    public static function muscleGroupOptions(): array
    {
        return Exercise::options(Exercise::MUSCLE_GROUPS);
    }

    /** Biblioteca para el selector del constructor de rutinas. */
    public static function catalog(): array
    {
        return Exercise::query()->orderBy('name')->limit(1000)->get()
            ->map(fn (Exercise $e) => $e->toClientArray())->all();
    }
}
