<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Progress\StreakService;
use App\Tenancy\InteractsWithTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Inserta la asistencia fuera del request del escáner, para que la
 * respuesta en recepción sea inmediata. Corre dentro del gimnasio que
 * la despachó (InteractsWithTenant → RunInTenant).
 */
class RecordAttendance implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 5;

    public function __construct(
        public int $userId,
        public string $scannedAt,
        public string $method = 'qr',
        public int $creditsUsed = 0,
    ) {
        $this->captureTenant();
    }

    public function handle(StreakService $streaks): void
    {
        Attendance::create([
            'user_id' => $this->userId,
            'scanned_at' => $this->scannedAt,
            'method' => $this->method,
            'credits_used' => $this->creditsUsed,
        ]);

        // El calendario y la racha del socio se actualizan al instante
        if ($user = User::find($this->userId)) {
            $streaks->forget($user);
        }
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
