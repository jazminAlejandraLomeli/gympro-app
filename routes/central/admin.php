<?php

/*
| Módulo 0 — Panel del super_admin (dominio central).
| Ya dentro de: middleware central + auth + role:super_admin, prefijo /admin, nombre admin.*
*/

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GymController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\SaasPaymentController;
use App\Http\Controllers\Admin\SystemController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:access-saas-admin')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Gimnasios
    Route::get('/gimnasios', [GymController::class, 'index'])->name('gyms.index');
    Route::post('/gimnasios', [GymController::class, 'store'])->name('gyms.store');
    Route::get('/gimnasios/{gym}', [GymController::class, 'show'])->whereNumber('gym')->name('gyms.show');
    Route::put('/gimnasios/{gym}', [GymController::class, 'update'])->whereNumber('gym')->name('gyms.update');
    Route::post('/gimnasios/{gym}/suspender', [GymController::class, 'suspend'])->whereNumber('gym')->name('gyms.suspend');
    Route::post('/gimnasios/{gym}/activar', [GymController::class, 'activate'])->whereNumber('gym')->name('gyms.activate');
    Route::post('/gimnasios/{gym}/pagos', [SaasPaymentController::class, 'store'])->whereNumber('gym')->name('gyms.payments.store');

    // Finanzas, sistema y errores
    Route::get('/finanzas', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/sistema', [SystemController::class, 'index'])->name('system.index');
    Route::get('/errores', [LogController::class, 'index'])->name('logs.index');
    Route::post('/errores/trabajos/{uuid}/reintentar', [LogController::class, 'retry'])->whereUuid('uuid')->name('logs.failed-jobs.retry');
    Route::delete('/errores/trabajos/{uuid}', [LogController::class, 'forget'])->whereUuid('uuid')->name('logs.failed-jobs.forget');
});
