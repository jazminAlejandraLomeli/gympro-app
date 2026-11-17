<?php

namespace Database\Seeders\Demo;

use App\Enums\AnnouncementType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\User;

/** Módulo E: avisos de ejemplo (vigentes, expirado y programado). */
class AnnouncementDemoSeeder
{
    public function seedGym(Gym $gym): void
    {
        $adminId = User::query()->where('role', Role::GymAdmin)->value('id');
        $now = now();

        $rows = [
            [
                'type' => AnnouncementType::Promocion,
                'title' => 'Hoy 2x1 en proteína',
                'body' => 'Solo hoy: lleva dos scoops de proteína por el precio de uno en el mostrador.',
                'starts_at' => $now->copy()->startOfDay(),
                'expires_at' => $now->copy()->endOfDay(),
                'pinned' => true,
            ],
            [
                'type' => AnnouncementType::Operativo,
                'title' => 'Horario de día festivo',
                'body' => 'El próximo lunes festivo abrimos de 8:00 a 14:00. ¡Te esperamos!',
                'starts_at' => $now->copy()->subDays(2)->setTime(7, 0),
                'expires_at' => $now->copy()->addDays(7)->setTime(23, 59),
                'pinned' => false,
            ],
            [
                'type' => AnnouncementType::Operativo,
                'title' => 'Mantenimiento de regaderas',
                'body' => 'Las regaderas de hombres estuvieron cerradas por mantenimiento.',
                'starts_at' => $now->copy()->subDays(10)->setTime(6, 0),
                'expires_at' => $now->copy()->subDays(8)->setTime(22, 0),
                'pinned' => false,
            ],
            [
                'type' => AnnouncementType::Promocion,
                'title' => 'Inscripción gratis el fin de semana',
                'body' => 'Trae a un amigo este sábado y domingo: su inscripción no tiene costo.',
                'starts_at' => $now->copy()->addDays(3)->setTime(6, 0),
                'expires_at' => $now->copy()->addDays(5)->setTime(22, 0),
                'pinned' => false,
            ],
        ];

        foreach ($rows as $row) {
            $announcement = new Announcement($row);
            $announcement->created_by = $adminId;
            $announcement->save();
        }
    }
}
