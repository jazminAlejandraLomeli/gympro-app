<?php

use App\Http\Controllers\Gym\AnnouncementController;
use Illuminate\Support\Facades\Route;

// Módulo E: tablón de avisos y promociones (dentro de /panel, nombre gym.)
Route::middleware('can:manage-gym')
    ->prefix('avisos')->name('announcements.')
    ->controller(AnnouncementController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/nuevo', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{announcement}/editar', 'edit')->name('edit');
        Route::put('/{announcement}', 'update')->name('update');
        Route::delete('/{announcement}', 'destroy')->name('destroy');
    });
