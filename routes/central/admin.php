<?php

/*
| Módulo 0 — Panel del super_admin (dominio central).
| Ya dentro de: middleware central + auth + role:super_admin, prefijo /admin, nombre admin.*
*/

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Provisional: el Módulo 0 lo reemplaza con su DashboardController
Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
