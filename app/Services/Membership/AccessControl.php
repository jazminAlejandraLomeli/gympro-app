<?php

namespace App\Services\Membership;

use App\Jobs\RecordAttendance;
use App\Models\Membership;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Validación de entrada (escáner QR o registro manual en recepción).
 *
 *  1. Verifica la firma del QR (sin BD).
 *  2. token → user_id y estado desde Redis (miss → BD → se cachea).
 *  3. Vigente por fecha → pasa. Vencido con créditos → descuenta 1 de
 *     forma atómica (UPDATE ... WHERE credits > 0).
 *  4. Candado de ~2 min (Cache::add) para que un re-escaneo no descuente
 *     ni registre dos veces.
 *  5. La asistencia se inserta en la cola (RecordAttendance).
 */
class AccessControl
{
    /** Segundos en los que un re-escaneo no vuelve a registrar ni descontar. */
    public const LOCK_SECONDS = 120;

    public function __construct(
        private TenantContext $tenant,
        private QrSigner $signer,
        private MemberStatusCache $cache,
    ) {}

    public function scan(string $payload): array
    {
        $token = $this->signer->verify($payload, (int) $this->tenant->id());

        if ($token === null) {
            return $this->invalid();
        }

        $userId = $this->cache->userIdForToken($token);

        return $userId ? $this->checkIn($userId, 'qr') : $this->invalid();
    }

    /** @param  'qr'|'manual'  $method */
    public function checkIn(int $userId, string $method): array
    {
        $status = $this->cache->status($userId);

        if ($status === null) {
            return $this->invalid('Este socio no tiene membresía registrada.');
        }

        $byDate = $status['expires_on'] !== null && $status['expires_on'] >= today()->toDateString();

        if (! $byDate && $status['credits'] <= 0) {
            return $this->denied($status);
        }

        $lockKey = $this->cache->lockKey($userId);

        if (! Cache::add($lockKey, now()->timestamp, self::LOCK_SECONDS)) {
            return $this->granted($status, 'Ya registrado hace un momento.', alreadyRegistered: true);
        }

        $creditsUsed = 0;

        if (! $byDate) {
            // Atómico: dos recepciones a la vez nunca dejan créditos negativos
            $affected = Membership::query()
                ->whereKey($status['membership_id'])
                ->where('credits', '>', 0)
                ->decrement('credits');

            $this->cache->forget($userId);

            if ($affected === 0) {
                Cache::forget($lockKey);

                return $this->denied([...$status, 'credits' => 0]);
            }

            $creditsUsed = 1;
            $status['credits']--;
        }

        RecordAttendance::dispatch($userId, now()->toIso8601String(), $method, $creditsUsed);

        $message = $creditsUsed
            ? ($status['credits'] === 1 ? 'Entrada con crédito. Le queda 1.' : "Entrada con crédito. Le quedan {$status['credits']}.")
            : '¡Bienvenido(a)!';

        return $this->granted($status, $message, creditsUsed: $creditsUsed);
    }

    private function granted(array $status, string $message, bool $alreadyRegistered = false, int $creditsUsed = 0): array
    {
        return $this->response('granted', $message, $status) + [
            'already_registered' => $alreadyRegistered,
            'credits_used' => $creditsUsed,
        ];
    }

    private function denied(array $status): array
    {
        return $this->response('denied', 'ACCESO DENEGADO - MEMBRESÍA VENCIDA', $status);
    }

    private function invalid(string $message = 'Código QR inválido.'): array
    {
        return $this->response('invalid', $message, null);
    }

    private function response(string $result, string $message, ?array $status): array
    {
        return [
            'status' => $result,
            'message' => $message,
            'member' => $status ? [
                'name' => $status['name'],
                'initial' => mb_strtoupper(mb_substr($status['name'], 0, 1)),
            ] : null,
            'expires_on' => $status['expires_on'] ?? null,
            'credits' => $status['credits'] ?? null,
        ];
    }
}
