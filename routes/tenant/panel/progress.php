<?php

use App\Http\Controllers\Gym\MeasurementController;
use Illuminate\Support\Facades\Route;

/*
| Módulos C y D: valoraciones físicas de los socios.
| Ya dentro de: tenant + auth + role:gym_admin,trainer, prefijo /panel, nombre gym.
*/

Route::middleware('can:manage-training')->group(function () {
    Route::get('/valoraciones', [MeasurementController::class, 'index'])->name('measurements.index');
    Route::get('/valoraciones/{member}', [MeasurementController::class, 'show'])
        ->whereNumber('member')->name('measurements.show');
    Route::post('/valoraciones/{member}', [MeasurementController::class, 'store'])
        ->whereNumber('member')->name('measurements.store');
});
