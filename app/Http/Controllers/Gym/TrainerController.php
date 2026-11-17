<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\TrainerRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Entrenadores del gimnasio (solo gym_admin). */
class TrainerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Gym/Trainers/Index', [
            'trainers' => User::query()
                ->where('role', Role::Trainer)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'created_at' => $u->created_at?->toDateString(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Gym/Trainers/Form', ['trainer' => null]);
    }

    public function store(TrainerRequest $request): RedirectResponse
    {
        $trainer = new User($request->safe()->only(['name', 'email', 'password']));
        $trainer->role = Role::Trainer; // gym_id lo pone BelongsToGym
        $trainer->save();

        return redirect('/panel/entrenadores')->with('success', 'Entrenador agregado.');
    }

    public function edit(User $trainer): Response
    {
        $this->ensureTrainer($trainer);

        return Inertia::render('Gym/Trainers/Form', [
            'trainer' => $trainer->only(['id', 'name', 'email']),
        ]);
    }

    public function update(TrainerRequest $request, User $trainer): RedirectResponse
    {
        $this->ensureTrainer($trainer);

        $data = $request->safe()->only(['name', 'email']);
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }
        $trainer->fill($data)->save();

        return redirect('/panel/entrenadores')->with('success', 'Entrenador actualizado.');
    }

    public function destroy(User $trainer): RedirectResponse
    {
        $this->ensureTrainer($trainer);

        try {
            $trainer->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede dar de baja: tiene información ligada (rutinas, valoraciones…).');
        }

        return redirect('/panel/entrenadores')->with('success', 'Entrenador dado de baja.');
    }

    /** El binding ya filtra por gimnasio; aquí se asegura que sea entrenador. */
    private function ensureTrainer(User $user): void
    {
        abort_unless($user->role === Role::Trainer, 404);
    }
}
