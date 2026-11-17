<?php

/*
| Módulo B — Rutinas, solicitudes y biblioteca de ejercicios (prefijo
| /panel, nombre gym., ya dentro de tenant + auth + role:gym_admin,trainer).
*/

use App\Http\Controllers\Gym\ExerciseClipController;
use App\Http\Controllers\Gym\ExerciseController;
use App\Http\Controllers\Gym\RoutineAssignmentController;
use App\Http\Controllers\Gym\RoutineController;
use App\Http\Controllers\Gym\RoutineRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-training')->group(function () {
    Route::get('/rutinas', [RoutineController::class, 'index'])->name('routines.index');
    Route::get('/rutinas/nueva', [RoutineController::class, 'create'])->name('routines.create');
    Route::post('/rutinas', [RoutineController::class, 'store'])->name('routines.store');

    Route::post('/rutinas/solicitudes/{routineRequest}/asignar', [RoutineRequestController::class, 'fulfill'])
        ->whereNumber('routineRequest')->name('routines.requests.fulfill');
    Route::post('/rutinas/solicitudes/{routineRequest}/descartar', [RoutineRequestController::class, 'dismiss'])
        ->whereNumber('routineRequest')->name('routines.requests.dismiss');

    Route::whereNumber('routine')->group(function () {
        Route::get('/rutinas/{routine}/editar', [RoutineController::class, 'edit'])->name('routines.edit');
        Route::put('/rutinas/{routine}', [RoutineController::class, 'update'])->name('routines.update');
        Route::delete('/rutinas/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');
        Route::post('/rutinas/{routine}/duplicar', [RoutineController::class, 'duplicate'])->name('routines.duplicate');

        Route::post('/rutinas/{routine}/asignaciones', [RoutineAssignmentController::class, 'store'])
            ->name('routines.assignments.store');
        Route::delete('/rutinas/{routine}/asignaciones/{assignment}', [RoutineAssignmentController::class, 'destroy'])
            ->scopeBindings()
            ->name('routines.assignments.destroy');
    });

    Route::get('/ejercicios', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('/ejercicios', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('/ejercicios/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('/ejercicios/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');

    Route::post('/ejercicios/{exercise}/clip', [ExerciseClipController::class, 'store'])->name('exercises.clip.store');
    Route::delete('/ejercicios/{exercise}/clip', [ExerciseClipController::class, 'destroy'])->name('exercises.clip.destroy');
});
