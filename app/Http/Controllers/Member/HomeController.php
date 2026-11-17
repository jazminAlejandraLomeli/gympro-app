<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** Inicio del socio. Reúne las tarjetas de cada módulo. */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Member/Home');
    }
}
