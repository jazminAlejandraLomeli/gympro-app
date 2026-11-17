<?php

/*
| Módulo A: membresías, socios, entrenadores y control de acceso QR.
| Ya está dentro de tenant + auth + role:gym_admin,trainer, prefijo /panel y nombre gym.
*/

use App\Http\Controllers\Gym\AttendanceController;
use App\Http\Controllers\Gym\MemberController;
use App\Http\Controllers\Gym\MemberPaymentController;
use App\Http\Controllers\Gym\ScannerController;
use App\Http\Controllers\Gym\TrainerController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:operate-front-desk')->group(function () {
    // Escáner y validación de acceso
    Route::get('/escaner', [ScannerController::class, 'show'])->name('scanner');
    Route::get('/escaner/buscar', [ScannerController::class, 'search'])
        ->middleware('throttle:120,1')->name('scanner.search');
    Route::post('/asistencia/escanear', [AttendanceController::class, 'scan'])
        ->middleware('throttle:90,1')->name('attendance.scan');
    Route::post('/asistencia/manual', [AttendanceController::class, 'manual'])
        ->middleware('throttle:60,1')->name('attendance.manual');

    // Socios
    Route::get('/socios', [MemberController::class, 'index'])->name('members.index');
    Route::get('/socios/nuevo', [MemberController::class, 'create'])->name('members.create');
    Route::post('/socios', [MemberController::class, 'store'])->name('members.store');
    Route::get('/socios/{membership}', [MemberController::class, 'show'])->whereNumber('membership')->name('members.show');
    Route::get('/socios/{membership}/editar', [MemberController::class, 'edit'])->whereNumber('membership')->name('members.edit');
    Route::put('/socios/{membership}', [MemberController::class, 'update'])->whereNumber('membership')->name('members.update');
    Route::post('/socios/{membership}/pagos', [MemberPaymentController::class, 'store'])->whereNumber('membership')->name('members.payments.store');
    Route::post('/socios/{membership}/qr', [MemberController::class, 'regenerateQr'])->whereNumber('membership')->name('members.qr.regenerate');
});

Route::middleware('can:manage-gym')->group(function () {
    Route::get('/entrenadores', [TrainerController::class, 'index'])->name('trainers.index');
    Route::get('/entrenadores/nuevo', [TrainerController::class, 'create'])->name('trainers.create');
    Route::post('/entrenadores', [TrainerController::class, 'store'])->name('trainers.store');
    Route::get('/entrenadores/{trainer}/editar', [TrainerController::class, 'edit'])->whereNumber('trainer')->name('trainers.edit');
    Route::put('/entrenadores/{trainer}', [TrainerController::class, 'update'])->whereNumber('trainer')->name('trainers.update');
    Route::delete('/entrenadores/{trainer}', [TrainerController::class, 'destroy'])->whereNumber('trainer')->name('trainers.destroy');
});
