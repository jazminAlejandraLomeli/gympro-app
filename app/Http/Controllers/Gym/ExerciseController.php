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

/** Catálogo de ejercicios del gimnasio (con filtro por grupo muscular). */
class ExerciseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'grupo' => ['nullable', 'string', Rule::in(array_keys(Exercise::MUSCLE_GROUPS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $exercises = Exercise::query()
            ->when($filters['grupo'] ?? null, fn ($q, $group) => $q->where('muscle_group', $group))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Exercise $exercise) => $exercise->toClientArray());

        return Inertia::render('Gym/Exercises/Index', [
            'exercises' => $exercises,
            'filters' => ['grupo' => $filters['grupo'] ?? '', 'q' => $filters['q'] ?? ''],
            'muscleGroups' => self::muscleGroupOptions(),
        ]);
    }

    public function store(ExerciseRequest $request): RedirectResponse
    {
        $exercise = new Exercise($request->validated());
        $exercise->created_by = $request->user()->id;
        $exercise->save();

        return back()->with('success', "Ejercicio «{$exercise->name}» creado. Ya puedes grabar su clip.");
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

        return back()->with('success', 'Ejercicio eliminado.');
    }

    /** @return list<array{value: string, label: string}> */
    public static function muscleGroupOptions(): array
    {
        return collect(Exercise::MUSCLE_GROUPS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
