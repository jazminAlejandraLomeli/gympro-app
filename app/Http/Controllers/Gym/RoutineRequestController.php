<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\FulfillWithTemplateRequest;
use App\Models\Routine;
use App\Models\RoutineRequest;
use App\Services\Training\RoutineAssigner;
use App\Services\Training\RoutineWriter;
use App\Services\Training\TodayRoutine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Solicitudes de los socios: "Asignar existente" y "Descartar". */
class RoutineRequestController extends Controller
{
    /** Duplica la plantilla como rutina personal del socio, la asigna y marca la solicitud como atendida. */
    public function fulfill(FulfillWithTemplateRequest $request, RoutineRequest $routineRequest, RoutineWriter $writer, RoutineAssigner $assigner): RedirectResponse
    {
        if (! $routineRequest->isPending()) {
            return back()->with('error', 'Esa solicitud ya fue atendida.');
        }

        $template = Routine::templates()->findOrFail($request->validated('template_id'));
        $member = $routineRequest->user()->firstOrFail();
        $by = $request->user();
        $date = $request->filled('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->validated('date'), TodayRoutine::TIMEZONE)
            : CarbonImmutable::now(TodayRoutine::TIMEZONE);

        DB::transaction(function () use ($template, $member, $by, $date, $writer, $assigner, $routineRequest) {
            $copy = $writer->duplicate($template, $member, $by);
            $assigner->assign($copy, [$member->id], $date, $by);
            $routineRequest->markDone($copy, $by);
        });

        return redirect('/panel/rutinas?tab=solicitudes')
            ->with('success', "Se asignó «{$template->name}» a {$member->name}.");
    }

    public function dismiss(Request $request, RoutineRequest $routineRequest): RedirectResponse
    {
        if (! $routineRequest->isPending()) {
            return back()->with('error', 'Esa solicitud ya fue atendida.');
        }

        $routineRequest->markCancelled($request->user());

        return back()->with('success', 'Solicitud descartada.');
    }
}
