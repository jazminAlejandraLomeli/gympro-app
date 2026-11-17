<?php

use App\Http\Controllers\Gym\CounterSaleController;
use App\Http\Controllers\Gym\ProductController;
use App\Http\Controllers\Gym\StockMovementController;
use Illuminate\Support\Facades\Route;

// Módulo F: inventario y mostrador (dentro de /panel, nombre gym.)
Route::middleware('can:operate-front-desk')
    ->prefix('inventario')->name('inventory.')
    ->group(function () {
        // Venta rápida (pantalla principal)
        Route::get('/', [CounterSaleController::class, 'index'])->name('index');
        Route::post('/ventas', [CounterSaleController::class, 'store'])->name('sales.store');
        Route::get('/socios', [CounterSaleController::class, 'members'])->name('members.search');

        // Catálogo
        Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
        Route::get('/productos/nuevo', [ProductController::class, 'create'])->name('products.create');
        Route::post('/productos', [ProductController::class, 'store'])->name('products.store');
        Route::get('/productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/productos/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/productos/{product}/activo', [ProductController::class, 'toggle'])->name('products.toggle');

        // Existencias
        Route::get('/movimientos', [StockMovementController::class, 'index'])->name('movements.index');
        Route::post('/productos/{product}/movimientos', [StockMovementController::class, 'store'])->name('movements.store');
    });
