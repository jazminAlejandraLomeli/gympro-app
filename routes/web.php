<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Gym\BrandingController;
use App\Http\Controllers\Gym\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Member\HomeController as MemberHomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Estructura de rutas
|--------------------------------------------------------------------------
|
|  Dominio central (localhost / gympro.mx)
|    /                 landing
|    /login            super_admin
|    /admin/*          panel del SaaS            → routes/central/admin.php
|
|  Subdominio del gimnasio (irondemo.localhost)
|    /login            personal y socios de ESE gimnasio
|    /panel/*          gym_admin y trainer       → routes/tenant/panel/*.php
|    /app/*            socio (member)            → routes/tenant/app/*.php
|
| Cada módulo tiene su propio archivo para no pisarse entre sí.
|
*/

Route::get('/', [HomeController::class, 'landing'])->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/inicio', [HomeController::class, 'home'])->name('home');
});

// ── Panel del SaaS (Módulo 0) ────────────────────────────────────────
Route::middleware(['central', 'auth', 'role:super_admin'])
    ->prefix('admin')->name('admin.')
    ->group(base_path('routes/central/admin.php'));

// ── Personal del gimnasio ────────────────────────────────────────────
Route::middleware(['tenant', 'auth', 'role:gym_admin,trainer'])
    ->prefix('panel')->name('gym.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::middleware('can:manage-gym')->group(function () {
            Route::get('/marca', [BrandingController::class, 'edit'])->name('branding.edit');
            Route::post('/marca', [BrandingController::class, 'update'])->name('branding.update');
        });

        foreach (glob(base_path('routes/tenant/panel/*.php')) as $file) {
            require $file;
        }
    });

// ── Portal del socio ─────────────────────────────────────────────────
Route::middleware(['tenant', 'auth', 'role:member'])
    ->prefix('app')->name('member.')
    ->group(function () {
        Route::get('/', MemberHomeController::class)->name('home');

        foreach (glob(base_path('routes/tenant/app/*.php')) as $file) {
            require $file;
        }
    });
