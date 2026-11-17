<?php

namespace App\Services\Membership;

use App\Models\Membership;
use Illuminate\Support\Str;

/**
 * Contenido del QR de la credencial:  gp1.{qr_token}.{firma}
 *
 * firma = HMAC-SHA256("{gym_id}|{qr_token}", APP_KEY) en base64url,
 * recortada a 22 caracteres (~132 bits). Como el gym_id va dentro de la
 * firma, un QR de otro gimnasio no valida aquí aunque el token exista.
 * La firma se revisa con hash_equals ANTES de tocar Redis o la BD.
 */
class QrSigner
{
    public const PREFIX = 'gp1';

    public const SIGNATURE_LENGTH = 22;

    public function payloadFor(Membership $membership): string
    {
        return implode('.', [self::PREFIX, $membership->qr_token, $this->sign((int) $membership->gym_id, $membership->qr_token)]);
    }

    public function sign(int $gymId, string $token): string
    {
        $raw = hash_hmac('sha256', "{$gymId}|{$token}", (string) config('app.key'), true);

        return substr(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='), 0, self::SIGNATURE_LENGTH);
    }

    /** Devuelve el qr_token si la firma es válida para ese gimnasio; si no, null. */
    public function verify(string $payload, int $gymId): ?string
    {
        $parts = explode('.', trim($payload));

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        [, $token, $signature] = $parts;

        if (! Str::isUuid($token) || strlen($signature) !== self::SIGNATURE_LENGTH) {
            return null;
        }

        return hash_equals($this->sign($gymId, $token), $signature) ? $token : null;
    }
}
