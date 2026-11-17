<?php

/*
| Módulo B — Rutinas y ejercicios (prefijo /panel, nombre gym.,
| ya dentro de tenant + auth + role:gym_admin,trainer).
*/

use App\Http\Controllers\Gym\ExerciseClipController;
use App\Http\Controllers\Gym\ExerciseController;
use App\Http\Controllers\Gym\RoutineAssignmentController;
use App\Http\Controllers\Gym\RoutineController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage-training')->group(function () {
    Route::get('/rutinas', [RoutineController::class, 'index'])->name('routines.index');
    Route::post('/rutinas', [RoutineController::class, 'store'])->name('routines.store');
    Route::get('/rutinas/{routine}', [RoutineController::class, 'edit'])->name('routines.edit');
    Route::put('/rutinas/{routine}', [RoutineController::class, 'update'])->name('routines.update');
    Route::delete('/rutinas/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');

    Route::post('/rutinas/{routine}/asignaciones', [RoutineAssignmentController::class, 'store'])
        ->name('routines.assignments.store');
    Route::delete('/rutinas/{routine}/asignaciones/{assignment}', [RoutineAssignmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('routines.assignments.destroy');

    Route::get('/ejercicios', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('/ejercicios', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('/ejercicios/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('/ejercicios/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');

    Route::post('/ejercicios/{exercise}/clip', [ExerciseClipController::class, 'store'])->name('exercises.clip.store');
    Route::delete('/ejercicios/{exercise}/clip', [ExerciseClipController::class, 'destroy'])->name('exercises.clip.destroy');
});
