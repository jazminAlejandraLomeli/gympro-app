<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        // Datos de ejemplo (lunes, miércoles, viernes) hasta tener asistencias reales
        'attendedDays' => [0, 2, 4],
    ]);
});
