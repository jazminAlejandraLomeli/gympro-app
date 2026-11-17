<?php

/*
| Módulo B — Rutina del socio (prefijo /app, nombre member.,
| ya dentro de tenant + auth + role:member). RoutinePolicy decide qué
| rutina puede ver, editar o volver a usar.
*/

use App\Http\Controllers\Member\RoutineController;
use App\Http\Controllers\Member\RoutineRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/rutina', [RoutineController::class, 'index'])->name('routine');
Route::get('/rutina/nueva', [RoutineController::class, 'create'])->name('routine.create');
Route::post('/rutina', [RoutineController::class, 'store'])->name('routine.store');
Route::get('/rutina/historial', [RoutineController::class, 'history'])->name('routine.history');

Route::post('/rutina/solicitudes', [RoutineRequestController::class, 'store'])->name('routine.requests.store');
Route::delete('/rutina/solicitudes/{routineRequest}', [RoutineRequestController::class, 'destroy'])
    ->whereNumber('routineRequest')->name('routine.requests.destroy');

Route::whereNumber('routine')->group(function () {
    Route::get('/rutina/{routine}', [RoutineController::class, 'show'])->name('routine.show');
    Route::get('/rutina/{routine}/editar', [RoutineController::class, 'edit'])->name('routine.edit');
    Route::put('/rutina/{routine}', [RoutineController::class, 'update'])->name('routine.update');
    Route::delete('/rutina/{routine}', [RoutineController::class, 'destroy'])->name('routine.destroy');
    Route::post('/rutina/{routine}/usar', [RoutineController::class, 'reuse'])->name('routine.reuse');
});
