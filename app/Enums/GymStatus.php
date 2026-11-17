<?php

namespace App\Enums;

enum GymStatus: string
{
    case Active = 'active';

    /** Licencia SaaS sin pagar: el gimnasio deja de operar. */
    case Suspended = 'suspended';
}
