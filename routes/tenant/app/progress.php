<?php

use App\Http\Controllers\Member\ProgressController;
use Illuminate\Support\Facades\Route;

/*
| Módulos C y D: racha, meta semanal y evolución física del socio.
| Ya dentro de: tenant + auth + role:member, prefijo /app, nombre member.
*/

Route::get('/progreso', [ProgressController::class, 'show'])->name('progress');
Route::post('/progreso/medidas', [ProgressController::class, 'storeMeasurement'])->name('progress.measurements.store');
Route::put('/progreso/meta', [ProgressController::class, 'updateGoal'])->name('progress.goal.update');
