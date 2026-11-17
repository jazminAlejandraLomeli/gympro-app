<?php

namespace Tests\Feature\Progress;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\BodyMeasurement;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

trait ProgressTestHelpers
{
    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function memberOf(Gym $gym, array $attributes = []): User
    {
        return User::factory()->forGym($gym)->role(Role::Member)->create($attributes);
    }

    /** Crea escaneos (fechas 'Y-m-d H:i' en la zona horaria de la app). */
    protected function scans(Gym $gym, User $user, string ...$moments): void
    {
        $this->tenant()->runAs($gym, function () use ($user, $moments) {
            foreach ($moments as $at) {
                Attendance::create(['user_id' => $user->id, 'scanned_at' => $at, 'method' => 'qr', 'credits_used' => 1]);
            }
        });
    }

    /** Tres días (lun, mié, vie) en la semana que empieza en $monday. */
    protected function fullWeek(Gym $gym, User $user, string $monday, int $days = 3): void
    {
        $base = CarbonImmutable::parse($monday);
        $moments = [];
        for ($i = 0; $i < $days; $i++) {
            $moments[] = $base->addDays($i * 2 % 7)->setTime(8, 0)->format('Y-m-d H:i:s');
        }
        $this->scans($gym, $user, ...$moments);
    }

    protected function measurement(Gym $gym, User $user, string $date, array $values): BodyMeasurement
    {
        return $this->tenant()->runAs($gym, function () use ($user, $date, $values) {
            $m = new BodyMeasurement(['measured_on' => $date, ...$values]);
            $m->user_id = $user->id;
            $m->recorded_by = $user->id;
            $m->save();

            return $m;
        });
    }
}
