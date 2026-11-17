<?php

namespace App\Enums;

enum Role: string
{
    /** Dueño del SaaS: acceso global, sin gym_id. */
    case SuperAdmin = 'super_admin';

    /** Dueño / recepción del gimnasio: control total de su gym_id. */
    case GymAdmin = 'gym_admin';

    /** Entrenador: rutinas, clips y valoraciones de su gimnasio. */
    case Trainer = 'trainer';

    /** Socio: credencial QR, rutina, rachas y evolución. */
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::GymAdmin => 'Administrador del gimnasio',
            self::Trainer => 'Entrenador',
            self::Member => 'Socio',
        };
    }
}
