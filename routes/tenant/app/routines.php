<?php

/*
| Módulo B — Rutina del socio (prefijo /app, nombre member.,
| ya dentro de tenant + auth + role:member).
*/

use App\Http\Controllers\Member\RoutineController;
use Illuminate\Support\Facades\Route;

Route::get('/rutina', RoutineController::class)->name('routine');
