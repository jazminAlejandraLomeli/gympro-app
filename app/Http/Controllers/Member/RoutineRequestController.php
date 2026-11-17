<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\AskForRoutineRequest;
use App\Models\Routine;
use App\Models\RoutineRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** El socio pide una rutina a los coaches o cancela su solicitud pendiente. */
class RoutineRequestController extends Controller
{
    public function store(AskForRoutineRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $created = DB::transaction(function () use ($user, $data) {
            // Revisión final dentro de la transacción (doble clic)
            if (RoutineRequest::pending()->where('user_id', $user->id)->lockForUpdate()->exists()) {
                return false;
            }

            $routineRequest = new RoutineRequest([
                'kind' => $data['kind'],
                'goal' => trim($data['goal']),
                'days_per_week' => $data['kind'] === Routine::TYPE_WEEKLY ? ($data['days_per_week'] ?? null) : null,
            ]);
            $routineRequest->user_id = $user->id;
            $routineRequest->save();

            return true;
        });

        return $created
            ? back()->with('success', 'Listo, tu coach ya recibió tu solicitud.')
            : back()->with('error', 'Ya tienes una solicitud pendiente.');
    }

    public function destroy(Request $request, RoutineRequest $routineRequest): RedirectResponse
    {
        abort_unless($routineRequest->user_id === $request->user()->id, 404);

        if (! $routineRequest->isPending()) {
            return back()->with('error', 'Esa solicitud ya fue atendida.');
        }

        $routineRequest->markCancelled($request->user());

        return back()->with('success', 'Cancelaste tu solicitud.');
    }
}
