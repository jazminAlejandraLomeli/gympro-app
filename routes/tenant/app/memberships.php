<?php

/*
| Módulo A: credencial QR del socio.
| Ya está dentro de tenant + auth + role:member, prefijo /app y nombre member.
*/

use App\Http\Controllers\Member\CredentialController;
use Illuminate\Support\Facades\Route;

Route::get('/credencial', CredentialController::class)
    ->middleware('can:use-member-portal')
    ->name('credential');
